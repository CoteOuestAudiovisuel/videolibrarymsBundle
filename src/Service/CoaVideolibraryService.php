<?php
namespace Coa\VideolibraryBundle\Service;
use Coa\VideolibraryBundle\Entity\Client;
use Coa\VideolibraryBundle\Entity\Video;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\RequestStack;
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

    public function __construct(ContainerBagInterface $container,
                                EntityManagerInterface $em, MediaConvertService $mediaConvert,
                                S3Service $s3Service,Packages $packages, RequestStack $requestStack,
                                HttpClientInterface $httpClient, Environment $twig){
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
    }

    public function transcode(Video $video,string $video_baseurl, string $hls_key_baseurl){
        $videosPath = $this->container->get('kernel.project_dir') . "/public/coa_videolibrary_upload";

        $code = $video->getCode();
        $withEncryption = $video->getEncrypted();
        $input_path = $videosPath."/".$code.'.mp4';
        if(!file_exists($input_path)) return;

        $inputfile = $video_baseurl.'/coa_videolibrary_upload/'.$code.'.mp4';
        $keyfilename = $code;
        $bucket = $this->container->get("coa_videolibrary.s3_bucket");
        $region = $this->container->get("coa_videolibrary.aws_region");
        $keyurl = $hls_key_baseurl.$this->container->get("coa_videolibrary.keys_route") . "/" . $keyfilename;

        try {
            $job = $this->mediaConvert->createJob($inputfile,$keyfilename,$keyurl,$bucket,$withEncryption);
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

        if(!file_exists($ftpPath)){
            mkdir($ftpPath);
        }

        if(!file_exists($destPath)){
            mkdir($destPath);
        }

        foreach (glob(sprintf("%s/*.mp4",$ftpPath)) as $filename) {
            $basename =  basename($filename);

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

        if(($videos = $rep->findBy(["state"=>["PROGRESSING","SUBMITTED"]],["id"=>"ASC"],$maxResults))) {

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

                    //Traitement pour notifier au client le resultat
                    $datas = [];
                    $datas['payload'][] = $this->generateVideoPayload($video);

                    $this->postBackProcess($video, $datas);

                } elseif ($job["status"] == "PROGRESSING") {
                    //Traitement pour notifier au client le resultat
                    $datas = [];
                    $datas['payload'][] = [
                        "code"=>$video->getCode(),
                        "originalFilename"=>$video->getOriginalFilename(),
                        "fileSize"=>$video->getFileSize(),
                        "state"=>$video->getState(),
                        "createdAt"=>$video->getCreatedAt() ? $video->getCreatedAt()->getTimestamp() : null,
                        "jobStartTime"=>$video->getJobStartTime() ? $video->getJobStartTime()->getTimestamp() : null,
                        "jobSubmitTime"=>$video->getJobSubmitTime() ? $video->getJobSubmitTime()->getTimestamp() : null,
                        "jobFinishTime"=> $video->getJobFinishTime() ? $video->getJobFinishTime()->getTimestamp() : null,
                        "jobPercent"=>$video->getJobPercent()
                    ];
                    $this->postBackProcess($video, $datas);
                }
                $job["html"] = $this->twig->render("@CoaVideolibrary/home/item-render.html.twig",["videos"=>[$video]]);
            }
        }


//        if(!$rep->count(["state"=>["PROGRESSING","SUBMITTED"]])) {
//            return ["payload"=>[]];
//        }
//
//        $result = $this->mediaConvert->listJobs($maxResults,'DESCENDING', null);
//
//        if(isset($result["payload"])){
//            foreach ($result["payload"] as &$job){
//
//                if(($video = $rep->findOneBy(["jobRef"=>$job["id"]]))) {
//
//                    if(!in_array($video->getState(),["PROGRESSING","pending","SUBMITTED"])){
//                        continue;
//                    }
//
//                    if(isset($job["status"]) && $job["status"] != $video->getState()){
//                        $video->setState($job["status"]);
//                    }
//
//                    if(isset($job["duration"]) && $job["duration"] != $video->getDuration()){
//                        $video->setDuration($job["duration"]);
//                    }
//
//                    if($job["status"] == "COMPLETE") {
//                        $video->setJobPercent(100);
//                    }
//                    else{
//                        $video->setJobPercent($job["jobPercent"]);
//                    }
//
//                    if (isset($job["startTime"]) && $job["startTime"]) {
//                        $video->setjobStartTime(new \DateTimeImmutable($job["startTime"]));
//                    }
//
//                    if (isset($job["submitTime"]) && $job["submitTime"]) {
//                        $video->setjobSubmitTime(new \DateTimeImmutable($job["submitTime"]));
//                    }
//
//                    if (isset($job["finishTime"]) && $job["finishTime"]) {
//                        $video->setjobFinishTime(new \DateTimeImmutable($job["finishTime"]));
//                    }
//
//                    if($job["status"] == "COMPLETE"){
//                        $bucket = $job["bucket"];
//                        $prefix = $job["prefix"];
//                        $job["resources"] = $this->mediaConvert->getResources($bucket,$prefix);
//                    }
//
//                    if (isset($job["resources"]) && count($job["resources"])) {
//                        #fix bug #045 not enough images on getstatus
//                        $video->setPoster($job["resources"]["thumnails"][0]);
//                        $video->setScreenshots($job["resources"]["thumnails"]);
//
//                        $video->setWebvtt($job["resources"]["webvtt"]);
//                        $video->setManifest($job["resources"]["manifests"][0]);
//                        $video->setVariants(array_slice($job["resources"]["manifests"], 1));
//                    }
//
//                    $this->em->persist($video);
//                    $this->em->flush();
//
//                    if(in_array($job["status"],["COMPLETE","ERROR","CANCELED"])){
//                        // supprimer les fichiers source mp4
//                        $filename = $basedir . "/" .$video->getCode().".mp4";
//                        // fichier video a supprimer
//                        if(file_exists($filename)){
//                            @unlink($filename);
//                        }
//                    }
//                    $job["html"] = $this->twig->render("@CoaVideolibrary/home/item-render.html.twig",["videos"=>[$video]]);
//
//                }
//            }
//            unset($job);
//        }
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

    private function generateVideoPayload(Video $el): array
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

    /*
     * Permet de lancer le process pour informer le client
     */
    private function postBackProcess(Video $video, array $datas)
    {
        /** @var Client $client */
        if($client = $video->getClient()) {
            if($postBackUrl = $client->getPostbackUrl()) {

                $this->httpClient->request("POST", $postBackUrl, [
                    'headers' => [
                        'Content-Type' => 'application/json'
                    ],
                    'json' => $datas
                ]);
            }
        }
    }
}
