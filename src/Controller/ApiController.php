<?php

namespace Coa\VideolibraryBundle\Controller;

use Coa\VideolibraryBundle\Entity\Client;
use Coa\VideolibraryBundle\Service\CoaVideolibraryService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

/**
 * @Route("/api/v1", name="coa_videolibrary_api_")
 */
class ApiController extends AbstractController
{
    /**
     * @Route("/upload", name="upload")
     * @IsGranted("upload")
     */
    public function upload(CoaVideolibraryService $coaVideolibrary, Security $security): Response
    {
        /** @var Client $client */
        $client = $security->getUser();
        $result = $coaVideolibrary->upload($client);
        return $this->json($result);
    }
}