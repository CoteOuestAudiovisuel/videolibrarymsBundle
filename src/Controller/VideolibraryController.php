<?php

namespace Coa\VideolibraryBundle\Controller;

use Coa\VideolibraryBundle\Extensions\Twig\AwsS3Url;
use Coa\VideolibraryBundle\Service\CoaVideolibraryService;
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

    private function getVideo(string $code){
        $entity_class = $this->getParameter("coa_videolibrary.video_entity");
        $em = $this->getDoctrine()->getManager();
        $rep = $em->getRepository($entity_class);

        if(!($video = $rep->findOneBy(["code"=>$code]))){
            throw $this->createNotFoundException();
        }
        return $video;
    }

    private  function  getTargetDirectory(){
        $basedir = $this->getParameter('kernel.project_dir')."/public/coa_videolibrary_upload";
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
        $entity_class = $this->getParameter("coa_videolibrary.video_entity");
        $em = $this->getDoctrine()->getManager();
        $rep = $em->getRepository($entity_class);

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
    public function showVideo(Request $request, string $code): Response
    {
        $video = $this->getVideo($code);
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
    public function deleteVideo(Request $request, S3Service $s3Service, string $code): Response
    {
        $em = $this->getDoctrine()->getManager();
        $video = $this->getVideo($code);
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
    public function cancelJob(Request $request, MediaConvertService $mediaConvert, string $code): Response
    {
        $em = $this->getDoctrine()->getManager();
        $video = $this->getVideo($code);
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
    public function getScreenshot(Request $request, string $code): Response
    {
        $video = $this->getVideo($code);
        $response = $this->render("@CoaVideolibrary/home/screenshot-item-render.html.twig", ["video"=>$video]);
        $response->headers->set("Cache-Control","public, max-age=3600");
        return  $response;
    }

    /**
     * @Route("/{code}/update-screenshot", name="update_screenshot", methods={"POST"})
     * modification de la vignette d'une video
     */
    public function setScreenshot(Request $request, AwsS3Url $awsS3Url, string $code): Response
    {
        $video = $this->getVideo($code);
        $result = ["status"=>false];
        $key = $request->request->get("key");

        if(in_array($key,$video->getScreenshots())){
            $em = $this->getDoctrine()->getManager();
            $video->setPoster($key);
            $em->persist($video);
            $em->flush();
            $result["status"] = true;
            $result["url"] = $awsS3Url->urlBasename($key,$video);
        }
        return  $this->json($result);
    }

    /**
     * @Route("/getStatus", name="getstatus")
     */
    public function getStatus(Request $request, MediaConvertService $mediaConvert, CoaVideolibraryService $coaVideolibrary): Response
    {
        $em = $this->getDoctrine()->getManager();
        $rep = $em->getRepository($this->getParameter("coa_videolibrary.video_entity"));
        $maxResults = $request->query->get("maxResults",20);
        $result = [];

        if($_ENV["APP_ENV"] == "dev"){
            $result = $coaVideolibrary->getStatus($maxResults);
        }
        else{
            $videos = $rep->findBy([],["id"=>"DESC"],$maxResults);
            $result["payload"] = array_map(function ($el){
                $item = [
                    "id"=>$el->getJobRef(),
                    "status"=>$el->getState(),
                    "startTime"=>$el->getStartTime(),
                    "finishTime"=>$el->getFinishTime(),
                    "submitTime"=>$el->getSubmitTime(),
                    "duration"=>$el->getDuration(),
                    "duration_formated"=> gmdate('H:i:s', $el->getDuration()),
                    "jobPercent"=>$el->getJobPercent(),
                    "html"=>$this->renderView("@CoaVideolibrary/home/item-render.html.twig",["videos"=>[$el]])
                ];
            },$videos);
        }
        return  $this->json($result);
    }

    /**
     * @Route("/upload", name="upload")
     */
    public function upload(Request $request, MediaConvertService $mediaConvert,
                           Packages $packages, CoaVideolibraryService $coaVideolibrary): Response
    {
        $em = $this->getDoctrine()->getManager();
        $video_entity = $this->getParameter("coa_videolibrary.video_entity");
        $rep = $em->getRepository($video_entity);

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
                $coaVideolibrary->transcode($video,$baseurl,$key_baseurl);
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
            $video->setAuthor($this->getUser());

            $em->persist($video);
            $em->flush();
            $result["video_id"] = $video->getCode();
            $result['status'] = "start";
        }
        return $this->json($result);
    }
}
