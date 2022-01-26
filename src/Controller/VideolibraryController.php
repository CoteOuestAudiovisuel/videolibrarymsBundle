<?php

namespace Coa\VideolibraryBundle\Controller;

use Coa\VideolibraryBundle\Entity\Video;
use Coa\VideolibraryBundle\Service\MediaConvertService;
use Coa\VideolibraryBundle\Service\S3Service;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/videolibrary", name="coa_videolibrary_")
 * @IsGranted("ROLE_MANAGER")
 * Class VideolibraryController
 * @package App\Controller
 */
class VideolibraryController extends AbstractController
{

    /**
     * @Route("/test", name="test")
     */
    public function monIp(Request $request): Response
    {
        dd($this->getParameter('coa_videolibrary'));
        return new Response("<h1>Titre du test</h1><div> ${$data}</div>");
    }
    private  function  getTargetDirectory(){

        $basedir = $this->getParameter('coa_videolibrary.upload_folder')."/coa_videolibrary";
        if(!file_exists($basedir)){
            mkdir($basedir);
        }
        return $basedir;
    }
    /**
     * @Route("/", name="index")
     */
    public function index(Request $request): Response
    {
        $em = $this->getDoctrine()->getManager();
        $rep = $em->getRepository(Video::class);

        $limit = $request->query->get("limit",20);
        $offset = $request->query->get("offset",0);

        $data = $rep->findBy([],["id"=>"DESC"],$limit,$offset);
        $view = '@CoaVideolibrary/home/index.html.twig';


        if($request->isXmlHttpRequest()){
            $acceptHeader = AcceptHeader::fromString($request->headers->get('Accept'));
            $view = '@CoaVideolibrary/home/item-render.html.twig';
        }

        return $this->render($view, [
            'videos' => $data
        ]);
    }

    /**
     * @Route("/{code}/view", name="show_video")
     *
     * affichage une entité video
     */
    public function showVideo(Request $request, Video $video): Response
    {
        $em = $this->getDoctrine()->getManager();
        $rep = $em->getRepository(Video::class);

        return $this->render("", [
            'video' => $video
        ]);
    }

    /**
     * @Route("/{code}/delete", name="delete_video", methods={"POST"})
     * @IsGranted("ROLE_ADMIN")
     *
     * supprimer une entité video
     */
    public function deleteVideo(Request $request, S3Service $s3Service, Video $video): Response
    {
        $em = $this->getDoctrine()->getManager();
        $result = ["status"=>false];

        $prefix = $video->getCode()."/";
        $bucket = $video->getBucket();

        $basedir = $this->getTargetDirectory();

        // supprimer les fichiers source mp4
        $filename = $basedir . "/" .$video->getCode().".mp4";
        // fichier video a supprimer
        if(file_exists($filename)){
            @unlink($filename);
        }

        $result["status"] = true;
        $em->remove($video);
        $em->flush();

        switch ($video->getState()){
            // supprimer les fichiers chez amazon S3
            case "COMPLETE":
                $r = $s3Service->deleteObject($bucket,$prefix);
                $result["payload"] = $r;
                break;

            // annulation de la tache de transcodage dans mediaconvert
            case "SUBMITTED":
            case "pending":
            case "PROGRESSING":
                $this->forward("@CoaVideolibrary/Controller/VideolibraryController::cancelJob",["code"=>$video->getCode()]);
                break;
        }

        return $this->json($result);
    }

    /**
     * @Route("/{code}/cancel-job", name="cancel_job", methods={"POST"})
     * @IsGranted("ROLE_ADMIN")
     *
     * annulation d'un tâche de transcodage
     */
    public function cancelJob(Request $request, MediaConvertService $mediaConvert, Video $video): Response
    {
        $em = $this->getDoctrine()->getManager();
        $result = ["status"=>false];
        $jobId = $video->getJobRef();

        $basedir = $this->getTargetDirectory();

        $filename = $basedir . "/" .$video->getCode().".mp4";
        // fichier video a supprimer
        if(file_exists($filename)){
            @unlink($filename);
        }

        $video->setState("CANCELED");
        $em->flush();

        if($jobId){
            $job = $mediaConvert->getJob($jobId);
            // on annule une tâche, quand celle-ci a l'un status suivant
            if(in_array($job["data"]["status"],["SUBMITTED","PROGRESSING","pending"])){
                $r = $mediaConvert->cancelJob($video->getJobRef());
            }
        }
        return $this->json($result);
    }

    /**
     * @Route("/{code}/screenshots", name="show_screenshots", methods={"GET"})
     *
     * affichage des vignettes d'une video
     */
    public function getScreenshot(Request $request, Video $video): Response
    {
        $response = $this->render("@CoaVideolibrary/home/screenshot-item-render.html.twig", ["video"=>$video]);
        $response->headers->set("Cache-Control","public, max-age=3600");
        return  $response;
    }

    /**
     * @Route("/{code}/update-screenshot", name="update_screenshot", methods={"POST"})
     * modification de la vignette d'une video
     */
    public function setScreenshot(Request $request, Video $video): Response
    {
        $result = ["status"=>false];
        $key = $request->request->get("key");

        if(in_array($key,$video->getScreenshots())){
            $em = $this->getDoctrine()->getManager();
            $video->setPoster($key);
            $em->persist($video);
            $em->flush();

            $result["status"] = true;
            $result["url"] = $this->getParameter("coa_videolibrary.cloudfront_distrib") . "/" . $key;
        }
        return  $this->json($result);
    }

    /**
     * @Route("/getStatus", name="getstatus")
     */
    public function getStatus(Request $request, MediaConvertService $mediaConvert): Response
    {
        $em = $this->getDoctrine()->getManager();
        $rep = $em->getRepository(Video::class);
        $status = $request->query->get("status","PROGRESSING");
        $maxResults = $request->query->get("maxResults",5);
        $result = $mediaConvert->listJobs($maxResults,'DESCENDING', null);

        $basedir = $this->getTargetDirectory();


        if($result["status"] && isset($result["jobs"])){
            foreach ($result["jobs"] as &$job){

                if($job["status"] == "COMPLETE"){
                    $bucket = $job["bucket"];
                    $prefix = $job["prefix"];
                    $job["ressources"] = $mediaConvert->getRessources($bucket,$prefix);
                }

                if(($video = $rep->findOneBy(["jobRef"=>$job["id"]]))) {

                    if(!in_array($video->getState(),["PROGRESSING","pending","SUBMITTED"])){
                        continue;
                    }

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

                    if (isset($job["ressources"]) && count($job["ressources"])) {
                        $video->setPoster(array_slice($job["ressources"]["thumnails"],1)[0]);
                        $video->setScreenshots($job["ressources"]["thumnails"]);

                        $video->setWebvtt($job["ressources"]["webvtt"]);
                        $video->setManifest($job["ressources"]["manifests"][0]);
                        $video->setVariants(array_slice($job["ressources"]["manifests"], 1));
                    }

                    $em->persist($video);
                    $em->flush();

                    if(in_array($job["status"],["COMPLETE","ERROR","CANCELED"])){
                        // supprimer les fichiers source mp4
                        $filename = $basedir . "/" .$video->getCode().".mp4";
                        // fichier video a supprimer
                        if(file_exists($filename)){
                            @unlink($filename);
                        }
                    }
                    $job["html"] = $this->renderView("@CoaVideolibrary/home/item-render.html.twig",["videos"=>[$video]]);
                }
            }
            unset($job);
        }
        return  $this->json($result);
    }

    /**
     * @Route("/upload", name="upload")
     */
    public function upload(Request $request, MediaConvertService $mediaConvert, Packages $packages): Response
    {
        $em = $this->getDoctrine()->getManager();
        $rep = $em->getRepository(Video::class);

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
                return $this->json($result,404);
            }

            $code = $video->getCode();
            $chunk = $file->getContent();
            $filepath = sprintf($targetDirectory . "/%s.mp4", $code);
            file_put_contents($filepath, $chunk, FILE_APPEND);
            $video->setFileSize($video->getFileSize() + $file_length);

            if($is_end) {
                $video->setState("pending");
            }

            $result["video_id"] = $video->getCode();
            $result["status"] = "downloading";

            if ($is_end) {
                $result['status'] = "success";
                $baseurl = $request->getScheme() . '://' . $request->getHttpHost() . $request->getBasePath();
                $key_baseurl = $this->getParameter("coa_videolibrary.hls_key_baseurl");
                if($key_baseurl){
                    $baseurl = $key_baseurl;
                }

                // c'est ici qu'on lance le processus de transcodage sur AWS
                $inputfile = $baseurl.$packages->getUrl('/coa_videolibrary/'.$code.'.mp4');
                $keyfilename = $code;
                $bucket = $this->getParameter("coa_videolibrary.s3_bucket");
                $region = $this->getParameter("coa_videolibrary.aws_region");
                $keyurl = $baseurl.$this->getParameter("coa_videolibrary.keys_route") . "/" . $keyfilename;

                $result['inputfile'] = $inputfile;
                try {
                    $job = $mediaConvert->createJob($inputfile,$keyfilename,$keyurl,$bucket);
                    $video->setJobRef($job["data"]["id"]);
                    $video->setState("SUBMITTED");
                }catch (\Exception $e){
                    throw $e;
                }

                $video->setBucket($bucket);
                $video->setRegion($region);
                $video->setJobPercent(0);
                $result["html"] = $this->renderView("@CoaVideolibrary/home/item-render.html.twig",["videos"=>[$video]]);
            }

            $em->persist($video);
            $em->flush();

        }
        else{

            if ($file->getMimeType() !== "video/mp4") {
                $result['log'] = sprintf("Veuillez utiliser un fichier mp4, %s n'est pas un fichier valide", $originalFilename);
                return $this->json($result,400);
            }

            $code = substr(trim(base64_encode(bin2hex(openssl_random_pseudo_bytes(32,$ok))),"="),0,32);
            $chunk = $file->getContent();
            $filepath = sprintf($targetDirectory . "/%s.mp4", $code);
            file_put_contents($filepath, $chunk, FILE_APPEND);

            $video = new Video();
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
            $video->setAuthor($this->getUser());

            $em->persist($video);
            $em->flush();
            $result["video_id"] = $video->getCode();
            $result['status'] = "start";
        }
        return $this->json($result);
    }
}
