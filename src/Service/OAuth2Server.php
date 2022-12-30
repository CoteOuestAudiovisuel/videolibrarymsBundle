<?php

namespace Coa\VideolibraryBundle\Service;

use Coa\VideolibraryBundle\Entity\AccessToken;
use Coa\VideolibraryBundle\Entity\Client;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OAuth2Server
{
    private RequestStack $requestStack;
    private ManagerRegistry $managerRegistry;
    private HttpClientInterface $httpClient;

    public function __construct(RequestStack $requestStack, ManagerRegistry $managerRegistry, HttpClientInterface $httpClient)
    {
        $this->requestStack = $requestStack;
        $this->managerRegistry = $managerRegistry;
        $this->httpClient = $httpClient->withOptions([
            'verify_peer' => false,
            'verify_host' => false
        ]);
    }

    /**
     * Pour la vérification de toutes les requêtes
     * @return void
     */
    public function handleVerifyRequest()
    {

    }

    /**
     * Pour la vérification des requêtes de type code
     * @return void
     */
    public function handleAuthorizeRequest()
    {

    }

    /**
     * Pour la vérification des requêtes de type token
     * @return array
     * @throws Oauth2Exception
     */
    public function handleTokenRequest(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $grant_type = $request->request->get('grant_type');
        $scope = $request->request->get('scope');
        $client_id = $request->request->get('client_id');
        $client_secret = $request->request->get('client_secret');
        $authorization = $request->headers->get('Authorization');
        $content_type = $request->headers->get('Content-Type');

        $this->checkRequestMethod("POST");

        $this->checkRequestContentType($content_type);

        if($grant_type !== "client_credentials") {
            throw new Oauth2Exception(
                "invalid_grant",
                400,
                "Vous n'êtes pas autorisé à effectuer cette action"
            );
        }

        if($authorization) {
            $exploded = explode(" ", $authorization);

            if(strtolower($exploded[0]) !== "basic") {
                throw new Oauth2Exception(
                    "wrong_authorization_format",
                    400,
                    "Le format d'authorization n'est pas autorisé"
                );
            }

            $decoded_credentials = base64_decode(end($exploded));
            $exploded_credentials = explode(':', $decoded_credentials);

            if(count($exploded_credentials) !== 2) {
                throw new Oauth2Exception(
                    "bad_client_auth_id",
                    400,
                    "Mauvais format de l'id d'authentification"
                );
            }

            $client_id = $exploded_credentials[0];
            $client_secret = $exploded_credentials[1];
        } else {
            if(!$client_id || !$client_secret) {
                throw new Oauth2Exception(
                    "invalid_client",
                    404,
                    "Les informations de connexion du client sont introuvables, ...."
                );
            }
        }

        $client = $this->managerRegistry->getRepository(Client::class)
            ->findOneBy([
                'clientId' => $client_id
            ]);

        if(!$client) {
            throw new Oauth2Exception(
                "client_not_found",
                404,
                "Le client n'existe pas dans la base de données"
            );
        }

        if(!hash_equals($client->getClientSecret(), $client_secret)) {
            throw new Oauth2Exception(
                "invalid_grant",
                400,
                "Le client_secret n'est pas valide"
            );
        }

        if(!$this->checkScopes($client, $scope)) {
            throw new Oauth2Exception(
                "invalid_scope",
                400,
                "Ce scope n'est pas authorisé pour ce client"
            );
        }

        /*if($client->getGrantType() !== $grant_type) {

        }*/

        $accessToken = $this->createAccessToken($client, $scope);

        return [
            'access_token' => $accessToken->getAccessToken(),
            'token_type' => $accessToken->getTokenType(),
            'refresh_token' => $accessToken->getRefreshToken(),
            'expires_in' => $accessToken->getExpiresIn()->getTimestamp(),
            'scope' => json_decode($accessToken->getScope())
        ];

    }


    private function createAccessToken(Client $client, string $scope = '', ?string $tokenType = "bearer"): AccessToken
    {
        $token = substr(trim(base64_encode(bin2hex(openssl_random_pseudo_bytes(64,$ok))),"="),0,32);
        $refreshToken = substr(trim(base64_encode(bin2hex(openssl_random_pseudo_bytes(64,$ok))),"="),0,32);
        $expiresIn = new \DateTimeImmutable("+1 day");

        $scopes = json_encode(explode(" ", $scope));

        $accessToken = (new AccessToken())
            ->setClient($client)
            ->setTokenType($tokenType)
            ->setAccessToken($token)
            ->setRefreshToken($refreshToken)
            ->setScope($scopes)
            ->setExpiresIn($expiresIn)
        ;

        $em = $this->managerRegistry->getManager();
        $em->persist($accessToken);
        $em->flush();

        return $accessToken;
    }

    /**
     * Permet de vérifier les scopes
     * @param Client $client
     * @param string $scope
     * @return bool
     */
    private function checkScopes(Client $client, string $scope): bool
    {
        $client_scopes = array_map(function ($item) {
            return $item->getLabel();
        }, $client->getScopes()->toArray());

        $request_scopes = explode(" ", $scope);

        foreach ($request_scopes as $request_scope) {
            if(!in_array($request_scope, $client_scopes)) {// Si on ne trouve pas le scope demandé dans celui du client
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $method
     * @return void
     * @throws Oauth2Exception
     */
    private function checkRequestMethod(string $method): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $req_method = $request->getMethod();
        if (!$request->isMethod($method)) {
            throw new Oauth2Exception(
                "invalid_request",
                400,
                "La methode '$req_method' n'est pas acceptée pour la requête"
            );
        }
    }

    /**
     * @param string $search
     * @param string|null $content_type
     * @return void
     * @throws Oauth2Exception
     */
    private function checkRequestContentType(string $search, ?string $content_type = "application/x-www-form-urlencoded" ): void
    {
        if ($search !== $content_type) {
            throw new Oauth2Exception(
                "wrong_content_type",
                400,
                "Le Content-Type n'est pas autorisé"
            );
        }
    }
}