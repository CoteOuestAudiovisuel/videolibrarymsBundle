<?php
namespace Coa\VideolibraryBundle\Service;
use Coa\MessengerBundle\Messenger\Message\DefaulfMessage;
use Coa\VideolibraryBundle\Entity\Client;
use Coa\VideolibraryBundle\Entity\Video;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Twig\Environment;


class CoaVideolibraryService
{
    private ContainerBagInterface $container;
    private EntityManagerInterface $em;
    private MediaConvertService $mediaConvert;
    private S3Service $s3Service;
    private Packages $packages;
    private RequestStack $requestStack;
    private HttpClientInterface $httpClient;
    private Environment $twig;
    private Security $security;
    private ClientService $clientService;
    private MessageBusInterface $bus;

    public function __construct(ContainerBagInterface $container,
                                EntityManagerInterface $em, MediaConvertService $mediaConvert,
                                S3Service $s3Service,Packages $packages, RequestStack $requestStack,
                                HttpClientInterface $httpClient, Environment $twig, Security $security, ClientService $clientService,
                                MessageBusInterface $bus){
        $this->container = $container;
        $this->em = $em;
        $this->mediaConvert = $mediaConvert;
        $this->s3Service = $s3Service;
        $this->packages = $packages;
        $this->requestStack = $requestStack;
        $this->httpClient = $httpClient->withOptions([
            'verify_peer' => false,
            'verify_host' => false
        ]);
        $this->twig = $twig;
        $this->security = $security;
        $this->clientService = $clientService;
        $this->bus = $bus;
    }

    public function transcode(Video $video,string $video_baseurl, string $hls_key_baseurl){
        $videosPath = $this->container->get('kernel.project_dir') . "/public/coa_videolibrary_upload";

        $code = $video->getCode();
        $withEncryption = $video->getEncrypted();
        $input_path = $videosPath."/".$code.'.mp4';
        if(!file_exists($input_path)) return;

        $inputfile = $video_baseurl.'/'.$code.'.mp4';
        $keyfilename = $code;
        $bucket = $this->container->get("coa_videolibrary.s3_bucket");
        $region = $this->container->get("coa_videolibrary.aws_region");
        $keyurl = $hls_key_baseurl . "/" . $keyfilename;

        try {
            $job = $this->mediaConvert->createJob($inputfile,$keyfilename,$keyurl,$bucket,$withEncryption, $video);
            $video->setJobRef($job["data"]["id"]);
            $video->setState("SUBMITTED");
        }catch (\Exception $e){
            throw $e;
        }

        $video->setBucket($bucket);
        $video->setRegion($region);
        $video->setJobPercent(0);
        $this->em->persist($video);
        $this->em->flush();
    }

    /**
     * synchonise le dossier ftp en transformant les fichiers videos en entité video
     * ensuite deplace le fichier du dossier ftp au dossier de transcoding
     */
    public function FtpSync(){
        $video_entity = $this->container->get('coa_videolibrary.video_entity');
        $rep = $this->em->getRepository($video_entity);
        $ftpPath = $this->container->get('kernel.project_dir') . "/coa_videolibrary_ftp";
        $destPath = $this->container->get('kernel.project_dir') . "/public/coa_videolibrary_upload";
        $cRep = $this->em->getRepository(Client::class);

        if(!file_exists($ftpPath)){
            mkdir($ftpPath);
        }

        if(!file_exists($destPath)){
            mkdir($destPath);
        }

        foreach (glob(sprintf("%s/**/*.mp4",$ftpPath)) as $filename) {
            $basename =  basename($filename);
            $client_name = explode("/",$filename);
            $fname = array_pop($client_name);
            $cname = array_pop($client_name);

            if(!($client = $cRep->findOneBy(["clientId"=>$cname]))){
                // tout ce qui ne correspond pas a un client est supprimé
                unlink($filename);
                continue;
            }

            $code = substr(trim(base64_encode(bin2hex(openssl_random_pseudo_bytes(32,$ok))),"="),0,32);
            $file_length = filesize($filename);
            $newFilename = $destPath . "/" . $code . ".mp4";
            $usefor = "episode";
            $encrypted = true;

            if(preg_match("#(film|episode|clip)_(.+)#i",$basename,$m)){
                $usefor = strtolower($m[1]);
                $basename = $m[2];
                if($usefor == "clip"){
                    $encrypted = false;
                }
            }

            $video = new $video_entity();
            $video->setCode($code);
            $video->setOriginalFilename($basename);
            $video->setFileSize($file_length);
            $video->setState("pending");
            $video->setIsTranscoded(false);
            $video->setEncrypted($encrypted);
            $video->setUseFor($usefor);
            $video->setCreatedAt(new \DateTimeImmutable());
            $video->setClient($client);
            $this->em->persist($video);
            $this->em->flush();
            rename($filename, $newFilename);
        }
    }

    /**
     * @param int $maxResults
     * @return array
     * @throws \Exception
     *
     * met a jour les entités videos en instance de transcodage
     */
    public function getStatus(int $maxResults){

        $video_entity = $this->container->get('coa_videolibrary.video_entity');
        $rep = $this->em->getRepository($video_entity);
        $basedir = $this->container->get('kernel.project_dir') . "/public/coa_videolibrary_upload";
        $result = ["payload"=>[]];

        $clients = $this->em->getRepository(Client::class)->findBy(['isEnabled' => true]);

        foreach ($clients as $client) {

            if(!($videos = $rep->findBy(["state"=>["PROGRESSING","SUBMITTED"], "client" => $client],["id"=>"ASC"],$maxResults))) {
                continue;
            }

            foreach ($videos as $video){
                if(!$video->getJobRef()) continue;
                $r = $this->mediaConvert->getJob($video->getJobRef());
                if(!$r["status"]) continue;

                $job = @$r["data"];
                if(isset($job["status"]) && $job["status"] != $video->getState()){
                    $video->setState($job["status"]);
                }

                if(isset($job["duration"]) && $job["duration"] != $video->getDuration()){
                    $video->setDuration($job["duration"]);
                }

                if($job["status"] == "COMPLETE") {
                    $video->setJobPercent(100);
                }
                else{
                    $video->setJobPercent($job["jobPercent"]);
                }

                if (isset($job["startTime"]) && $job["startTime"]) {
                    $video->setjobStartTime(new \DateTimeImmutable($job["startTime"]));
                }

                if (isset($job["submitTime"]) && $job["submitTime"]) {
                    $video->setjobSubmitTime(new \DateTimeImmutable($job["submitTime"]));
                }

                if (isset($job["finishTime"]) && $job["finishTime"]) {
                    $video->setjobFinishTime(new \DateTimeImmutable($job["finishTime"]));
                }

                if($job["status"] == "COMPLETE"){
                    $bucket = $video->getBucket(); //@$job["bucket"];
                    $prefix = $video->getCode()."/"; //@$job["prefix"];
                    $job["resources"] = $this->mediaConvert->getResources($bucket,$prefix);
                }

                if (isset($job["resources"]) && count($job["resources"])) {
                    #fix bug #045 not enough images on getstatus
                    $video->setDownload(@$job["resources"]["download"][0]);
                    $video->setPoster($job["resources"]["thumnails"][0]);
                    $video->setScreenshots($job["resources"]["thumnails"]);
                    # add random poster selecttion
                    if(count(@$job["resources"]["thumnails"]) > 1){
                        $index = random_int(1,count($job["resources"]["thumnails"])-1);
                        $video->setPoster($job["resources"]["thumnails"][$index]);
                    }

                    $video->setWebvtt($job["resources"]["webvtt"]);
                    $video->setManifest($job["resources"]["manifests"][0]);
                    $video->setVariants(array_slice($job["resources"]["manifests"], 1));
                }

                $this->em->persist($video);
                $this->em->flush();
                $result["payload"][] = $job;

                if(in_array($job["status"],["COMPLETE","ERROR","CANCELED"])){
                    // supprimer les fichiers source mp4
                    $filename = $basedir . "/" .$video->getCode().".mp4";
                    // fichier video a supprimer
                    if(file_exists($filename)){
                        @unlink($filename);
                    }
                }

                //Traitement pour notifier au client le resultat
                $datas = [];
                $datas['payload'] = [
                    "code" => $video->getCode(),
                    "jobRef" => $video->getJobRef(),
                    "jobPercent" => $video->getJobPercent(),
                    "state" => $video->getState(),
                    "duration" => $video->getDuration(),
                    "startTime" => $video->getJobStartTime() ? $video->getJobStartTime()->getTimestamp() : null,
                    "submitTime" => $video->getJobSubmitTime() ? $video->getJobSubmitTime()->getTimestamp() : null,
                    "finishTime" => $video->getJobFinishTime() ?  $video->getJobFinishTime()->getTimestamp() : null,
                    "download" => $video->getDownload(),
                    "poster" => $video->getPoster(),
                    "screenshots" => $video->getScreenshots(),
                    "webvtt" => $video->getWebvtt(),
                    "manifest" => $video->getManifest(),
                    "variants" => $video->getVariants(),
                    "bucket" => $video->getBucket(),
                    "region" => $video->getRegion()
                ];

                $attributes = [
                    "content_type"=>"application/json",
                    "delivery_mode"=>2,
                    "correlation_id"=>uniqid(),
                ];
                
                $action = "mc.transcoding.status." . $client->getRoutingSuffix();
                
                $this->bus->dispatch(new DefaulfMessage([
                    "action"=>$action,
                    "payload"=>$datas['payload'],
                ]),[
                    new AmqpStamp($action, AMQP_NOPARAM, $attributes),
                ]);

                $job["html"] = $this->twig->render("@CoaVideolibrary/home/item-render.html.twig",["videos"=>[$video]]);
            }
        }

        return $result;
    }

    public function search(){
        $request = $this->requestStack->getCurrentRequest();
        $video_entity = $this->container->get('coa_videolibrary.video_entity');
        $limit = $request->query->get("limit",20);
        $offset = $request->query->get("offset",0);
        $term = trim($request->query->get("q"));
        $code = trim($request->query->get("code"));

        $qb = $this->em->createQueryBuilder()
            ->from($video_entity,'v')
            ->select('v');

        if($term){
            $qb
                ->andWhere($qb->expr()->like("v.originalFilename",':q'))
                ->setParameter('q',"%".$term."%");
        }

        if($request->query->get("__source") == "modal-search"){
            $qb
                ->andWhere("v.state = :state")
                ->setParameter("state","COMPLETE");
        }

        if($code){
            $qb
                ->andWhere("v.code = :code")
                ->setParameter("code",$code);
        }

        return $qb
            ->orderBy("v.id","DESC")
            ->getQuery()
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getResult();
    }

    public function searchWithJsonResult(){
        $data = $this->search();
        $result = [];

        foreach ($data as $el){
            $result["payload"][] = [
                "id"=>$el->getId(),
                "authorId"=>$el->getAuthor() ? $el->getAuthor()->getId() : null,
                "code"=>$el->getCode(),
                "originalFilename"=>$el->getOriginalFilename(),
                "fileSize"=>$el->getFileSize(),
                "state"=>$el->getState(),
                "isTranscoded"=>$el->getIsTranscoded(),
                "poster"=>$el->getPoster(),
                "download"=>$el->getDownload(),
                "screenshots"=>$el->getScreenshots(),
                "webvtt"=>$el->getWebvtt(),
                "manifest"=>$el->getManifest(),
                "duration"=>$el->getDuration(),
                "createdAt"=>$el->getCreatedAt() ? $el->getCreatedAt()->getTimestamp() : null,
                "jobRef"=>$el->getJobRef(),
                "variants"=>$el->getVariants(),
                "jobStartTime"=>$el->getJobStartTime() ? $el->getJobStartTime()->getTimestamp() : null,
                "jobSubmitTime"=>$el->getJobSubmitTime() ? $el->getJobSubmitTime()->getTimestamp() : null,
                "jobFinishTime"=> $el->getJobFinishTime() ? $el->getJobFinishTime()->getTimestamp() : null,
                "bucket"=>$el->getBucket(),
                "region"=>$el->getRegion(),
                "jobPercent"=>$el->getJobPercent(),
                "encrypted"=>$el->getEncrypted(),
                "useFor"=>$el->getUseFor(),
                "provider"=>$el->getProvider()
            ];
        }
        return $result;
    }

    public function searchInConstellation(string $service, array $params = []){
        $data = [];
        $constellation = $this->container->get("coa_videolibrary.constellation");
        $connections = @$constellation["connections"];
        $video_entity = $this->container->get('coa_videolibrary.video_entity');
        $xuser = @$constellation["id"];
        $xtoken = @$constellation["token"];

        if($connections && array_key_exists($service,$connections)){
            $connection = $connections[$service];
            $response = $this->httpClient->request('GET',
                sprintf("%s%s",$connection["domain"],$connection["endpoint"]),
                [
                    'query' => array_merge($this->requestStack->getCurrentRequest()->query->all(),$params),
                    'headers' => [
                        'X-User' => $xuser,
                        'X-Token' => $xtoken
                    ]
                ]
            );

            if($response->getStatusCode() == 200){
                $output = json_decode($response->getContent(),true);

                if(@$output["payload"]){
                    $output = $output["payload"];
                    foreach ($output as $el){

                        $item = new $video_entity();
                        foreach ($el as $k=>$v){
                            if(in_array($k,["authorId","id"])) continue;
                            elseif ($k == "encrypted"){
                                $v = $v??true;
                            }
                            elseif(in_array($k,["createdAt","jobStartTime","jobSubmitTime","jobFinishTime"])){
                                $v = \DateTimeImmutable::createFromMutable((new \DateTime())->setTimestamp($v));
                            }

                            $method = "set".ucfirst($k);
                            $item->$method($v);
                        }
                        $data[] = $item;
                    }
                }
            }
        }
        return $data;
    }

    static function generateVideoPayload(Video $el): array
    {
        return [
            "id"=>$el->getId(),
            "authorId"=>$el->getAuthor() ? $el->getAuthor()->getId() : null,
            "code"=>$el->getCode(),
            "originalFilename"=>$el->getOriginalFilename(),
            "fileSize"=>$el->getFileSize(),
            "state"=>$el->getState(),
            "isTranscoded"=>$el->getIsTranscoded(),
            "poster"=>$el->getPoster(),
            "download"=>$el->getDownload(),
            "screenshots"=>$el->getScreenshots(),
            "webvtt"=>$el->getWebvtt(),
            "manifest"=>$el->getManifest(),
            "duration"=>$el->getDuration(),
            "createdAt"=>$el->getCreatedAt() ? $el->getCreatedAt()->getTimestamp() : null,
            "jobRef"=>$el->getJobRef(),
            "variants"=>$el->getVariants(),
            "jobStartTime"=>$el->getJobStartTime() ? $el->getJobStartTime()->getTimestamp() : null,
            "jobSubmitTime"=>$el->getJobSubmitTime() ? $el->getJobSubmitTime()->getTimestamp() : null,
            "jobFinishTime"=> $el->getJobFinishTime() ? $el->getJobFinishTime()->getTimestamp() : null,
            "bucket"=>$el->getBucket(),
            "region"=>$el->getRegion(),
            "jobPercent"=>$el->getJobPercent(),
            "encrypted"=>$el->getEncrypted(),
            "useFor"=>$el->getUseFor(),
            "provider"=>$el->getProvider()
        ];

    }


    private  function  getTargetDirectory(){
        $basedir = $this->container->get('kernel.project_dir')."/public/coa_videolibrary_upload";
        if(!file_exists($basedir)){
            mkdir($basedir);
        }
        return $basedir;
    }

    /**
     * upload par petit chunck avec reconstitution de fichier à la fin de l'opération
     *
     * @return string[]
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function upload(Client $client){
        $request = $this->requestStack->getMainRequest();
        $em = $this->em;
        $video_entity = $this->container->get("coa_videolibrary.video_entity");
        $rep = $em->getRepository($video_entity);
        $encrypted = filter_var($request->request->get('encryption',true),FILTER_VALIDATE_BOOLEAN);
        $usefor = strtolower($request->request->get('usefor',''));
        $usefor = in_array($usefor,["film","episode","clip"]) ? $usefor : "episode";

        $targetDirectory = $this->getTargetDirectory();

        $result = [
            "status" => "fails",
        ];
        $file = $request->files->get("file");
        $video_id = $request->request->get("video_id");

        $content_range = $request->headers->get("content-range");
        list($chunk_range, $total_size) = explode("/", substr($content_range, 5));
        list($chunk_range_start, $chunk_range_end) = explode("-", $chunk_range);
        $total_size = intval($total_size);
        $chunk_range_start = intval($chunk_range_start);
        $chunk_range_end = intval($chunk_range_end);
        $is_end = ($chunk_range_end + 1 == $total_size);

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $file_length = $file->getSize();


        if ($video_id) {

            if (!($video = $rep->findOneBy(["code"=>$video_id]))) {
                $result['logs'] = "impossible de traiter cette requete";
                $result['code'] = 404;
                return $result;
            }

            $code = $video->getCode();
            $chunk = $file->getContent();
            $filepath = sprintf($targetDirectory . "/%s.partial", $code);
            file_put_contents($filepath, $chunk, FILE_APPEND);
            $video->setFileSize($video->getFileSize() + $file_length);
            $video->setEncrypted($encrypted);
            $video->setUseFor($usefor);

            if($is_end) {
                $video->setState("pending");
                // renomme le fichier temporaire en fichier d'extension final
                $newfilepath = sprintf($targetDirectory . "/%s.mp4", $code);
                rename($filepath,$newfilepath);
            }

            $result["video_id"] = $video->getCode();
            $result["status"] = "downloading";

            if ($is_end) {
                $result['status'] = "success";
                $baseurl = $this->container->get('coa_videolibrary.inputfile_baseurl');
                $client = $video->getClient();
                $key_baseurl = $client->getHlsKeyBaseurl();
                $this->transcode($video,$baseurl,$key_baseurl);
                $result["html"] = $this->twig->render("@CoaVideolibrary/home/item-render.html.twig",["videos"=>[$video]]);
            }

            $em->persist($video);
            $em->flush();
        }
        else{

            if ($file->getMimeType() !== "video/mp4") {
                $result['log'] = sprintf("Veuillez utiliser un fichier mp4, %s n'est pas un fichier valide", $originalFilename);
                $result["code"] = 400;
                return $result;
            }

            $code = substr(trim(base64_encode(bin2hex(openssl_random_pseudo_bytes(32,$ok))),"="),0,32);
            if(($code_prefix = $this->container->get("coa_videolibrary.prefix"))){
                $code = sprintf("%s_%s",$code_prefix,$code);
            }

            $chunk = $file->getContent();
            $filepath = sprintf($targetDirectory . "/%s.partial", $code);
            file_put_contents($filepath, $chunk, FILE_APPEND);

            $video = new $video_entity();
            $video->setCode($code);
            $video->setOriginalFilename($originalFilename);
            $video->setFileSize($file_length);
            $video->setState("downloading");
            $video->setIsTranscoded(false);
            $video->setPoster(null);
            $video->setScreenshots(null);
            $video->setWebvtt(null);
            $video->setManifest(null);
            $video->setDuration(null);
            $video->setCreatedAt(new \DateTimeImmutable());
            $video->setAuthor(null);
            $video->setEncrypted($encrypted);
            $video->setUseFor($usefor);
            $video->setClient($client);

            $em->persist($video);
            $em->flush();
            $result["video_id"] = $video->getCode();
            $result['status'] = "start";
        }

        return $result;
    }

    public function multicastMessage(string $action, array $payload, ?array $attributes = [])
    {
        $clients = $this->em->getRepository(Client::class)->findBy(['isEnabled' => true]);


        $attributes = array_merge([
            "content_type"=>"application/json",
            "delivery_mode"=>2
        ], $attributes);
        
        foreach ($clients as $client) {
            if($client->getRoutingSuffix()) {

                $action .= "." . $client->getRoutingSuffix();

                $this->bus->dispatch(new DefaulfMessage([
                    "action"=>$action,
                    "payload"=>$payload,
                ]),[
                    new AmqpStamp($action, AMQP_NOPARAM, $attributes),
                ]);

            }
        }
        
    }
}
