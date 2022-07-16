<?php

namespace Coa\VideolibraryBundle\Controller;

use Coa\VideolibraryBundle\Service\CoaVideolibraryService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("/videolibrary/constellation", name="coa_videolibrary_constellation_")
 * Class VideolibraryConstellationController
 * @package Coa\VideolibraryBundle\Controller
 */
class VideolibraryConstellationController extends AbstractController
{
    /**
     * @Route("/search", name="search")
     * @param CoaVideolibraryService $coaVideolibrary
     * @return Response
     */
    public function search(CoaVideolibraryService $coaVideolibrary):JsonResponse{
        return $this->json($coaVideolibrary->searchWithJsonResult());
    }
}
