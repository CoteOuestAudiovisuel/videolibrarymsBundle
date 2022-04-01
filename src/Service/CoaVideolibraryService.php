<?php
namespace Coa\VideolibraryBundle\Service;
use Coa\VideolibraryBundle\Entity\Video;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Doctrine\ORM\EntityManagerInterface;


class CoaVideolibraryService
{
    private ContainerInterface $container;
    private EntityManagerInterface $em;
    private MediaConvertService $mediaConvert;
    private S3Service $s3Service;
    private Packages $packages;


    public function __construct(ContainerInterface $container,
                                EntityManagerInterface $em, MediaConvertService $mediaConvert,
                                S3Service $s3Service,Packages $packages){
        $this->container = $container;
        $this->em = $em;
        $this->mediaConvert = $mediaConvert;
        $this->s3Service = $s3Service;
        $this->packages = $packages;
    }

    public function transcode(Video $video,string $video_baseurl, string $hls_key_baseurl){
        $videosPath = $this->container->getParameter('kernel.project_dir') . "/public/coa_videolibrary_upload";

        $code = $video->getCode();
        $withEncryption = $video->getEncrypted();
        $input_path = $videosPath."/".$code.'.mp4';
        if(!file_exists($input_path)) return;

        $inputfile = $video_baseurl.$this->packages->getUrl('/coa_videolibrary_upload/'.$code.'.mp4');
        $keyfilename = $code;
        $bucket = $this->container->getParameter("coa_videolibrary.s3_bucket");
        $region = $this->container->getParameter("coa_videolibrary.aws_region");
        $keyurl = $hls_key_baseurl.$this->container->getParameter("coa_videolibrary.keys_route") . "/" . $keyfilename;

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
        $video_entity = $this->container->getParameter('coa_videolibrary.video_entity');
        $rep = $this->em->getRepository($video_entity);
        $ftpPath = $this->container->getParameter('kernel.project_dir') . "/coa_videolibrary_ftp";
        $destPath = $this->container->getParameter('kernel.project_dir') . "/public/coa_videolibrary_upload";

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

        $video_entity = $this->container->getParameter('coa_videolibrary.video_entity');
        $rep = $this->em->getRepository($video_entity);
        $basedir = $this->container->getParameter('kernel.project_dir') . "/public/coa_videolibrary_upload";
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
                $job["html"] = $this->container->get("twig")->render("@CoaVideolibrary/home/item-render.html.twig",["videos"=>[$video]]);
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
//                    $job["html"] = $this->container->get("twig")->render("@CoaVideolibrary/home/item-render.html.twig",["videos"=>[$video]]);
//
//                }
//            }
//            unset($job);
//        }
        return $result;
    }
}
