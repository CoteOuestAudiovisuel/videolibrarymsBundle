<?php

namespace Coa\VideolibraryBundle\Controller;

use Coa\VideolibraryBundle\Entity\Video;
use Coa\VideolibraryBundle\Service\CoaVideolibraryService;
use Coa\VideolibraryBundle\Service\MediaConvertService;
use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api/v1", name="coa_videolibrary_api_")
 */
class ApiController extends AbstractController
{
    /**
     * @Route("/", name="index")
     * @return Response
     */
    public function index(): Response
    {
        dd('ok');
    }

    /**
     * @Route("/upload", name="upload")
     * @IsGranted("upload")
     */
    public function upload(Request $request, MediaConvertService $mediaConvert,
                           Packages $packages, CoaVideolibraryService $coaVideolibrary, EntityManagerInterface $em): Response
    {
        $video_entity = $this->getParameter("coa_videolibrary.video_entity");
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
                return $this->json($result,404);
            }

            $code = $video->getCode();
            $chunk = $file->getContent();
            $filepath = sprintf($targetDirectory . "/%s.mp4", $code);
            file_put_contents($filepath, $chunk, FILE_APPEND);
            $video->setFileSize($video->getFileSize() + $file_length);
            $video->setEncrypted($encrypted);
            $video->setUseFor($usefor);


            if($is_end) {
                $video->setState("pending");
            }

            $result["video_id"] = $video->getCode();
            $result["status"] = "downloading";

            if ($is_end) {
                $result['status'] = "success";
                $key_baseurl = $this->getParameter("coa_videolibrary.hls_key_baseurl");
                $baseurl = $request->getSchemeAndHttpHost();

                if(!$key_baseurl){
                    $key_baseurl = $baseurl;
                }
                else{
                    $baseurl = $key_baseurl;
                }

                if($_ENV["APP_ENV"] == "prod"){
                    $baseurl = $request->getSchemeAndHttpHost();
                }

                //$coaVideolibrary->transcode($video,$baseurl,$key_baseurl);
                $video_ = $em->getRepository(Video::class)->find(5830);
                $datas = $coaVideolibrary->generateVideoPayload($video_);
                $coaVideolibrary->postBackProcess($video_, $datas);

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
            if(($code_prefix = $this->getParameter("coa_videolibrary.prefix"))){
                $code = sprintf("%s_%s",$code_prefix,$code);
            }

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
            $video->setEncrypted($encrypted);
            $video->setUseFor($usefor);
            $video->setClient($this->getUser());

            $em->persist($video);
            $em->flush();
            $result["video_id"] = $video->getCode();
            $result['status'] = "start";
        }
        return $this->json($result);
    }

    private  function  getTargetDirectory(){
        $basedir = $this->getParameter('kernel.project_dir')."/public/coa_videolibrary_upload";
        if(!file_exists($basedir)){
            mkdir($basedir);
        }
        return $basedir;
    }
}