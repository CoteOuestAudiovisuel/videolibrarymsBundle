<?php
namespace Coa\VideolibraryBundle\Service;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Aws\MediaConvert\MediaConvertClient;
use Aws\Exception\AwsException;
use Aws\Result;
use Aws\S3\S3Client;

class MediaConvertService
{
    protected string $region;
    protected string $version;
    protected string $endpoint;
    private static MediaConvertClient $client;


    public function __construct(string $endpoint, string $region, string $version){
        $this->region = $region;
        $this->version = $version;
        $this->endpoint = $endpoint;


        $env = getenv();
        foreach ($_ENV as $k=>$v){
            if(!str_starts_with($k,"AWS_")) continue;
            if(!isset($env[$k])){
                putenv(sprintf("%s=%s",$k,$v));
            }

        }
    }

    public function formatJob($job){

        $jobid = $job["Id"];
        $status = $job["Status"];
        $timing = $job["Timing"];
        $finishTime = @$timing["FinishTime"];
        $startTime = @$timing["StartTime"];
        $submitTime = @$timing["SubmitTime"];
        $duration = @$job["OutputGroupDetails"][0]["OutputDetails"][0]["DurationInMs"]/1000;


        $item = [
            "id"=>$jobid,
            "status"=>$status,
            "startTime"=>$startTime,
            "finishTime"=>$finishTime,
            "submitTime"=>$submitTime,
            "duration"=>$duration,
            "duration_formated"=> gmdate('H:i:s', $duration),
        ];

        if($status == "PROGRESSING"){
            $jobPercent = intval(@$job["JobPercentComplete"]);
            $currentPhase = @$job["CurrentPhase"];

            $item["jobPercent"] = $jobPercent;
            $item["currentPhase"] = $currentPhase;
        }
        else if($status == "ERROR"){
            $item["errorCode"] = $job["ErrorCode"];
            $item["errorMessage"] = $job["ErrorMessage"];
        }

        foreach ($job["Settings"]["OutputGroups"] as $i=>$el){
            if(!isset($el["CustomName"])) continue;

            if($el["CustomName"] == "variants"){
                $item["destination"] = $el["OutputGroupSettings"]["HlsGroupSettings"]["Destination"];
                $split = explode("/",$item["destination"]);
                $item["bucket"] = $split[2];
                $item["prefix"] = array_reverse($split)[1]."/";
            }
        }

        return $item;
    }

    public  function getEndpoints(){

        $client = new MediaConvertClient([
            //'profile' => 'default',
            'version' => $this->version,
            'region' => $this->region,
        ]);

        $result = $client->describeEndpoints([]);
        return $result['Endpoints'][0]['Url'];
    }

    public  function buildClient() : MediaConvertClient{
        if(isset(self::$client)){
            return self::$client;
        }

        $client = new MediaConvertClient([
            'version' => $this->version,
            'region' => $this->region,
            'endpoint' => $this->endpoint
        ]);
        self::$client = $client;

        return $client;
    }

    public function listJobs(int $maxResults = 20, string $orderBy = "DESCENDING", ?string $status = "SUBMITTED", string $nextToken=null):array{

        $client = $this->buildClient();
        $result = ["status"=>false];

        $params = [
            'MaxResults' => $maxResults,
            'Order' => $orderBy,
            //'Status' => $status,
            // 'NextToken' => '<string>', //OPTIONAL To retrieve the twenty next most recent jobs
        ];

        if($status){
            $params['Status'] = $status;
        }

        if($nextToken){
            $params["NextToken"] = $nextToken;
        }

        try {
            $jobs = $client->listJobs($params);
            //dd($jobs);
            //$jobs = $jobs->toArray();
            $result["status"] = true;
            $result["nextToken"] = $jobs["NextToken"];

            foreach ($jobs["Jobs"] as $job){
                $result["jobs"][] = $this->formatJob($job);
            }
        } catch (AwsException $e) {
            $result["error"] = $e->getMessage();
        }
        return $result;
    }

    public  function getJob(string $jobId): array{
        $client = $this->buildClient();
        $job = $client->getJob(["Id"=>$jobId]);
        $result = ["status"=>false];
        $statusCode = $job["@metadata"]["statusCode"];

        if($statusCode == 200){
            $result["status"] = true;
            $result["data"] = $this->formatJob($job["Job"]);
        }
        return $result;
    }

    public function createJob(string $inputfile, string $keyfilename, string $keyurl, string $bucket){

        $result = ["status"=>false];

        try {
            $client = $this->buildClient();
            $payload = file_get_contents(__DIR__.'/../../templates/mediaconvert/job.json');

            $keyval = random_bytes(16);
            $iv = random_bytes(16);
            $timecodes = [20,30,45,50,60,90];
            $timecode = $timecodes[random_int(0,count($timecodes)-1)];
            $outputfile = "s3://$bucket/$keyfilename/manifest";

            file_put_contents(__DIR__."/../../keys/$keyfilename.key",$keyval);
            file_put_contents(__DIR__."/../../keys/$keyfilename.iv",$iv);

            $payload = str_replace("__INPUTFILE__",$inputfile,$payload);
            $payload = str_replace("__OUTPUTFILE__",$outputfile,$payload);
            $payload = str_replace("__KEYVAL__",bin2hex($keyval),$payload);
            $payload = str_replace("__KEYURL__",$keyurl,$payload);
            $payload = str_replace("__IV__",bin2hex($iv),$payload);
            $payload = str_replace("__SCREENSHOT_TC__",$timecode,$payload);


            $jobSetting = json_decode($payload,true);

            $p = array_merge($jobSetting,[
                "Role" => "arn:aws:iam::211301172288:role/service-role/MediaConvert_Default_Role",
                "UserMetadata" => [
                    "Customer" => "Kiwi",
                    "Env" => "dev"
                ],
            ]);

            $job = $client->createJob($p);

            $result["data"] = $this->formatJob($job["Job"]);
            $result["status"] = true;
            return $result;

        } catch (AwsException $e) {
            $result["error"] = $e->getMessage();
        }

        return $result;
    }

    public function getRessources(string $bucket,string $prefix){

        $result = [];
        $s3 = new S3Client([
            'region' => $this->region,
            'version' => 'latest',
        ]);

        $objects = $s3->getPaginator('ListObjects', [
            'Bucket' => $bucket,
            'Delimiter' => '/',
            "Prefix"=>$prefix
        ]);

        try {
            $manifests = $objects->search("Contents[? ends_with(Key,'.m3u8') ].Key | [? !ends_with(@,'I-Frame.m3u8') ] | sort(@)");

            foreach ($manifests as $el){
                $result["manifests"][] = $el;
            }
        }catch (\Exception $e){

        }

        try {
            $thumnails = $objects->search("Contents[?ends_with(Key, '.jpg')].Key | [? starts_with(@,'${prefix}manifest') ] | sort(@)");
            foreach ($thumnails as $el){
                $result["thumnails"][] = $el;
            }
        }catch (\Exception $e){

        }

        try {
            $webvtt = $objects->search("Contents[? ends_with(Key,'.m3u8') ].Key | [? ends_with(@,'I-Frame.m3u8') ] | sort(@)");
            foreach ($webvtt as $el){
                $result["webvtt"] = $el;
            }
        }catch (\Exception $e){

        }

        return $result;
    }

    /**
     * @param string $jobId
     * @return array|Result
     *
     * annuler une tache de transcodage
     */
    public function cancelJob(string $jobId){
        $result = [];
        try {
            $client = $this->buildClient();
            $result = $client->cancelJob([
                'Id' => $jobId,
            ]);
        } catch (AwsException $e) {
            $result["error"] = $e->getMessage();
        }
        return $result;
    }

}
