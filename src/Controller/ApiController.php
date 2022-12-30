<?php

namespace Coa\VideolibraryBundle\Controller;

use Doctrine\DBAL\Schema\AbstractAsset;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api", name="coa_videolibrary_api_")
 */
class ApiController extends AbstractAsset
{
    /**
     * @Route("/", name="index")
     * @return Response
     */
    public function index(): Response
    {
        dd('ok');
    }
}