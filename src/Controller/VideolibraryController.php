<?php

namespace Coa\VideolibraryBundle\Controller;

use Coa\MessengerBundle\Messenger\Message\DefaulfMessage;
use Coa\VideolibraryBundle\Entity\Client;
use Coa\VideolibraryBundle\Entity\Video;
use Coa\VideolibraryBundle\Extensions\Twig\AwsS3Url;
use Coa\VideolibraryBundle\Form\ScreenshotType;
use Coa\VideolibraryBundle\Service\CoaVideolibraryService;
use Coa\VideolibraryBundle\Service\MediaConvertService;
use Coa\VideolibraryBundle\Service\S3Service;

use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function Doctrine\ORM\QueryBuilder;


/**
 * @Route("/videolibrary", name="coa_videolibrary_")
 * @IsGranted("ROLE_MANAGER")
 * Class VideolibraryController
 * @package App\Controller
 */
class VideolibraryController extends AbstractController
{
    private MessageBusInterface $bus;
    private EntityManagerInterface $em;
    private S3Service $s3Service;
    private CoaVideolibraryService $coaVideolibrary;

    public function __construct(MessageBusInterface $bus, EntityManagerInterface $em, S3Service $s3Service, CoaVideolibraryService $coaVideolibrary)
    {
        $this->bus = $bus;
        $this->em = $em;
        $this->s3Service = $s3Service;
        $this->coaVideolibrary = $coaVideolibrary;
    }

    private function getVideo(string $code){
        $entity_class = $this->getParameter("coa_videolibrary.video_entity");
        $rep = $this->em->getRepository($entity_class);
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
     * @Route("/upload/{clientId}", name="upload")
     * @IsGranted("ROLE_VIDEOLIBRARY_UPLOAD")
     */
    public function upload(CoaVideolibraryService $coaVideolibrary, Client $client): Response{
        $result = $coaVideolibrary->upload($client);
        return $this->json($result);
    }


    /**
     * @Route("/", name="index")
     */
    public function index(Request $request, EntityManagerInterface $em, CoaVideolibraryService $coaVideolibrary,HttpClientInterface $httpClient): Response
    {
        $data = [];
        $service = $request->query->get("service");
        if($service){
            $data = $coaVideolibrary->searchInConstellation($service);
        }
        else{
            $data = $coaVideolibrary->search();
        }

        $view = '@CoaVideolibrary/home/index.html.twig';

        if($request->isXmlHttpRequest()){
            $acceptHeader = AcceptHeader::fromString($request->headers->get('Accept'));
            $view = '@CoaVideolibrary/home/item-render.html.twig';

            if($request->query->get("__source") == "modal-search"){
                $view = '@CoaVideolibrary/home/modal-video-item.html.twig';
            }
        }

        return $this->render($view, [
            'videos' => $data,
            "service" => $service,
            "apiClients" => $this->em->getRepository(Client::class)->findAll()
        ]);
    }

    /**
     * @Route("/{code}/view", name="show_video")
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
     * @IsGranted("ROLE_VIDEOLIBRARY_DELETE")
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

                $payload = [
                    "code"=>$video->getCode()
                ];

                $this->coaVideolibrary->multicastMessage('mc.video.remove', $payload);

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
     * @IsGranted("ROLE_VIDEOLIBRARY_DELETE")
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
                $attributes = [
                    "content_type"=>"application/json",
                    "delivery_mode"=>2,
                    "correlation_id"=>uniqid(),
                ];

                $action = "mc.transcoding.canceled." . $video->getClient()->getRoutingSuffix();

                $this->bus->dispatch(new DefaulfMessage([
                    "action"=>$action,
                    "payload"=>[
                        "code"=>$video->getCode(),
                        "jobRef"=>$video->getJobRef(),
                    ],
                ]),[
                    new AmqpStamp($action, AMQP_NOPARAM, $attributes),
                ]);
            }
        }
        return $this->json($result);
    }

    /**
     * @Route("/{code}/screenshots", name="show_screenshots", methods={"GET"})
     * affichage des vignettes d'une video
     */
    public function getScreenshot(Request $request, string $code): Response
    {
        $video = $this->getVideo($code);

        $form = $this->createForm(ScreenshotType::class);
        $form->handleRequest($request);

        $response = $this->render("@CoaVideolibrary/home/screenshot-item-render.html.twig", ["video"=>$video, "form" => $form->createView()]);
        $response->headers->set("Cache-Control","public, max-age=3600");
        return  $response;
    }



    /**
     * @Route("/{code}/save-duration", name="save_media_duration", methods={"POST"})
     * modification de la durée d'un media
     */
    public function saveMediaDuration(Request $request, AwsS3Url $awsS3Url, string $code): Response
    {
        $video = $this->getVideo($code);
        $em = $this->getDoctrine()->getManager();

        $result = ["status"=>false];

        $duree = explode(":",trim($request->request->get("duration")));
        $h = 0;
        $m = 0;
        $s = 0;
        if(count($duree) == 3){
            list($h,$m,$s) = $duree;
        }
        else if(count($duree) == 2){
            list($m,$s) = $duree;
        }
        else{
            return $this->json([
                "status"=>false,
                "code" => 400,
                "message" => "veuiller envoyer une durée valide hh:mm:ss"
            ],200);
        }

        $duration = new \DateInterval(sprintf('PT%sH%sM%sS',$h,$m,$s));
        $seconds = $duration->days*86400 + $duration->h*3600
            + $duration->i*60 + $duration->s;

        $video->setDuration($seconds);
        $em->persist($video);
        $em->flush();
        $result["status"] = true;
        $result["message"] = "Durée modifiée avec succès";

        $payload = [
            "code"=>$video->getCode(),
            "duration"=>$seconds,
        ];

        $this->coaVideolibrary->multicastMessage('mc.video.duration', $payload);

        return  $this->json($result);
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

        $payload = [
            "code"=>$video->getCode(),
            "poster"=>$key
        ];
        
        $this->coaVideolibrary->multicastMessage('mc.video.poster', $payload);
        
        return  $this->json($result);
    }

    /**
     * Ajout de screenshot
     * @Route("/upload-screenshots/{code}", name="upload_screenshots")
     * @param Request $request
     * @return Response
     */
    public function uploadScreenshots(string $code, Request $request, AwsS3Url $awsS3Url): Response
    {
        $errors = [];
        $form = $this->createForm(ScreenshotType::class);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $img = $form->get('imgs')->getData();
            $sourceFilePath = $img->getRealPath();

            $video = $this->getVideo($code);

            if(!$video) {
                return $this->json([
                    'status' => 'failed',
                    'errors' => [
                        0 => "Vidéo non trouvée"
                    ]
                ], 400);
            }

            $screenshots = $video->getScreenshots();
            $nb_screenshots = count($screenshots);
            $filenumber = str_pad($nb_screenshots, 7, "0", STR_PAD_LEFT);
            $filename = sprintf("%s/manifest_720p.%s.jpg", $code, $filenumber);

            $screenshots[] =  $filename;
            $video->setScreenshots($screenshots);

            $this->manager->persist($video);
            $this->manager->flush();


            $this->s3Service->putObject($video->getBucket(), $filename, $sourceFilePath);

            $screenshot = $awsS3Url->urlBasename($filename, $video);

            //Dispatch
            $payload = [
                "code" => $code,
                "key" => $filename
            ];
            
            $this->coaVideolibrary->multicastMessage('mc.thumbnail.add', $payload);
            
            return $this->json([
                'status' => 'successful',
                'screenshot' => $screenshot,
                'key' => $filename
            ]);
        }

        $form_errors = $form->getErrors(true);
        if ($form_errors->count() > 0) {
            $nb_errors = $form_errors->count();
            for($i=0; $i<$nb_errors; $i++) {
               $errors[] = $form_errors->offsetGet($i)->getMessage();
            }
        }

        return $this->json([
            'status' => 'failed',
            'errors' => $errors
        ], 400);

    }

    /**
     * Suppression de screenshot
     * @Route("/delete-screenshot/{code}", name="delete_screenshot")
     * @param string $code
     * @param Request $request
     * @return Response
     */
    public function deleteScreenshot(string $code, Request $request): Response
    {
        $video = $this->getVideo($code);
        $key = $request->request->get("key");

        if(!$video) {
            return $this->json([
                'status' => 'failed',
                'errors' => [
                    0 => "Vidéo non trouvée"
                ]
            ], 400);
        }

        $screenshots = $video->getScreenshots();
        $screenshot_index = array_search($key, $screenshots);
        unset($screenshots[$screenshot_index]);

        $video->setScreenshots($screenshots);

        $this->manager->persist($video);
        $this->manager->flush();

        $this->s3Service->deleteObject($video->getBucket(), $key);

        //Dispatch
        $payload = [
            "code" => $code,
            "key" => $key
        ];

        $this->coaVideolibrary->multicastMessage('mc.thumbnail.remove', $payload);

        return $this->json([
            'status' => 'successful'
        ]);
    }

    /**
     * @Route("/getStatus", name="getstatus")
     */
    public function getStatus(CoaVideolibraryService $coaVideolibrary): Response
    {
        $result = $coaVideolibrary->getStatus(20);
        return  $this->json($result);
    }


    /**
     * @Route("/ftpsync", name="ftpsync", methods={"POST"})
     * @IsGranted("ROLE_VIDEOLIBRARY_UPLOAD")
     * synchronisation du dossier coa_videolibrary_ftp
     */
    public function ftpsync(Request $request, CoaVideolibraryService $coaVideolibrary): Response
    {
        $result = ["status"=>true];
        $coaVideolibrary->FtpSync();
        return  $this->json($result);
    }
}
