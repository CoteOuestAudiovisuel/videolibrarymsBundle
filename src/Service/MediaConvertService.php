<?php
namespace Coa\VideolibraryBundle\Service;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Aws\MediaConvert\MediaConvertClient;
use Aws\Exception\AwsException;
use Aws\Result;
use Aws\S3\S3Client;

class MediaConvertService
{
    private static MediaConvertClient $client;
    private ContainerInterface $container;


    public function __construct(ContainerInterface $container){
        $this->container = $container;

        $env = getenv();
        if(!isset($env["AWS_ACCESS_KEY_ID"])){
            putenv(sprintf("%s=%s","AWS_ACCESS_KEY_ID",$container->getParameter("coa_videolibrary.aws_access_key_id")));
        }

        if(!isset($env["AWS_SECRET_ACCESS_KEY"])){
            putenv(sprintf("%s=%s","AWS_SECRET_ACCESS_KEY",$container->getParameter("coa_videolibrary.aws_secret_access_key")));
        }
    }

    /**
     * @param $job
     * @return array
     *
     * format le resultat d'un job
     */
    public function formatJob($job){

        $jobid = $job["Id"];
        $status = $job["Status"];
        $finishTime = null;
        $startTime = null;
        $submitTime = null;
        $duration = 0;

        if(isset($job["OutputGroupDetails"])){
            $duration = $job["OutputGroupDetails"][0]["OutputDetails"][0]["DurationInMs"]/1000;
        }

        if(isset($job["Timing"])){
            $timing = $job["Timing"];
            $finishTime = @$timing["FinishTime"];
            $startTime = @$timing["StartTime"];
            $submitTime = @$timing["SubmitTime"];
        }


        $item = [
            "id"=>$jobid,
            "status"=>$status,
            "startTime"=>$startTime,
            "finishTime"=>$finishTime,
            "submitTime"=>$submitTime,
            "duration"=>$duration,
            "duration_formated"=> gmdate('H:i:s', $duration),
            "jobPercent"=>0
        ];

        if($status == "PROGRESSING"){
            if(isset($job["JobPercentComplete"])){
                $jobPercent = intval(@$job["JobPercentComplete"]);
                $item["jobPercent"] = $jobPercent;
            }

            if(isset($job["CurrentPhase"])){
                $currentPhase = @$job["CurrentPhase"];
                $item["currentPhase"] = $currentPhase;
            }
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

    /**
     * @return mixed
     * recupere l'account enddoint du mediaconvert
     */
    public  function getEndpoints(){

        $client = new MediaConvertClient([
            'version' => "lastest",
            'region' => $this->container->getParameter("coa_videolibrary.aws_region"),
        ]);

        $result = $client->describeEndpoints([]);
        return $result['Endpoints'][0]['Url'];
    }

    /**
     * @return MediaConvertClient
     * creer le singleton du client mediaconvert
     */
    public  function buildClient() : MediaConvertClient{
        if(isset(self::$client)){
            return self::$client;
        }

        $client = new MediaConvertClient([
            'version' => "latest",
            'region' => $this->container->getParameter("coa_videolibrary.aws_region"),
            'endpoint' => $this->container->getParameter("coa_videolibrary.mediaconvert_endpoint")
        ]);
        self::$client = $client;

        return $client;
    }

    /**
     * @param int $maxResults
     * @param string $orderBy
     * @param string|null $status
     * @param string|null $nextToken
     * @return false[]
     *
     * liste les N dernières tâches puis les format avec formatJob
     */
    public function listJobs(int $maxResults = 20, string $orderBy = "DESCENDING", ?string $status = "SUBMITTED", string $nextToken=null):array{

        $client = $this->buildClient();
        $result = ["payload"=>[]];

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
            $result["nextToken"] = $jobs["NextToken"];

            foreach ($jobs["Jobs"] as $job){
                $result["payload"][] = $this->formatJob($job);
            }
        } catch (AwsException $e) {
            $result["error"] = $e->getMessage();
        }
        return $result;
    }

    /**
     * @param string $jobId
     * @return false[]
     * retourne le status d'une tâche
     */
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

    /**
     * @param string $inputfile
     * @param string $keyfilename
     * @param string $keyurl
     * @param string $bucket
     * @return false[]
     * @throws \Exception
     *
     * creer une tache de transcodage
     */
    public function createJob(string $inputfile, string $keyfilename, string $keyurl, string $bucket, bool $withEncryption = true){

        $result = ["status"=>false];

        try {
            $client = $this->buildClient();
            $payload = file_get_contents(__DIR__ . '/../Resources/views/job.json');
            $keys_folder = $this->container->getParameter('kernel.project_dir')."/coa_videolibrary_keys";

            $timecodes = [20,30,45,50,60,90];
            $timecode = $timecodes[random_int(0,count($timecodes)-1)];
            $outputfile = "s3://$bucket/$keyfilename/manifest";

            // lorsque l'encrption est activé
            if($withEncryption) {
                $keyval = random_bytes(16);
                $iv = random_bytes(16);

                if (!file_exists($keys_folder)) {
                    mkdir($keys_folder);
                }
                file_put_contents($keys_folder . "/" . $keyfilename, $keyval);

                $payload = str_replace("__KEYVAL__", bin2hex($keyval), $payload);
                $payload = str_replace("__KEYURL__", $keyurl, $payload);
                $payload = str_replace("__IV__", bin2hex($iv), $payload);
            }
            else{
                // lorsque l'encrption est desactivé
                unset($payload["Settings"]["OutputGroups"][0]["OutputGroupSettings"]["HlsGroupSettings"]["Encryption"]);
            }

            $payload = str_replace("__SCREENSHOT_TC__",$timecode,$payload);
            $payload = str_replace("__INPUTFILE__",$inputfile,$payload);
            $payload = str_replace("__OUTPUTFILE__",$outputfile,$payload);

            $jobSetting = json_decode($payload,true);

            $p = array_merge($jobSetting,[
                "Role" => $this->container->getParameter("coa_videolibrary.mediaconvert_role_arn"),
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

    /**
     * @param string $bucket
     * @param string $prefix
     * @return array
     *
     */
    public function getResources(string $bucket,string $prefix){

        $result = [];
        $s3 = new S3Client([
            'region' => $this->container->getParameter("coa_videolibrary.aws_region"),
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
