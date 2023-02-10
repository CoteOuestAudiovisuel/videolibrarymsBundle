<?php

namespace Coa\VideolibraryBundle\Controller;

use Coa\VideolibraryBundle\Service\Oauth2Exception;
use Coa\VideolibraryBundle\Service\OAuth2Server;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/oauth2/old", name="coa_videolibrary_oauth2_old_")
 */
class OAuth2Controller extends AbstractController
{
    private OAuth2Server $oauth2Server;

    public function __construct(OAuth2Server $oauth2Server)
    {
        $this->oauth2Server = $oauth2Server;
    }

    /**
     * permet de vérifier un token
     * @Route("/verify", name="verify")
     * @return void
     */
    public function verify()
    {

    }

    /**
     * Pour les authentifications avec authorization_code
     * @Route("/autorize", name="authorize")
     * @return void
     */
    public function authorize()
    {

    }

    /**
     * Se charge de la génération des tokens
     * @Route("/token", name="token", methods={"POST"})
     * @throws Oauth2Exception
     */
    public function token(): JsonResponse
    {
        $payload = $this->oauth2Server->handleTokenRequest();

        $token_headers = [
            'Cache-Control' => 'no-store',
            'Content-Type' => 'application/json;charset=UTF-8',
            'Pragma' => 'no-cache'
        ];

        return $this->json($payload, 200, $token_headers);
    }

    /**
     * Permet de supprimer des tokens
     * @Route("/revoke", name="revoke", methods={"POST"})
     * @return void
     */
    public function revoke()
    {

    }

    /**
     * Pour les autorisations de type code
     * @return void
     */
    public function authorizeLogin()
    {

    }

    /**
     * Pour les autorisations de type code
     * @return void
     */
    public function authorizePrompt()
    {

    }
}