<?php

namespace Coa\VideolibraryBundle\Service;

use Coa\VideolibraryBundle\Entity\Client;
use Coa\VideolibraryBundle\Entity\Video;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ClientService
{
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {

        $this->httpClient = $httpClient;
    }

    /*
    * Permet de lancer le process pour informer le client
    */
    public function postBackProcess(Video $video, array $datas)
    {
        /** @var Client $client */
        if($client = $video->getClient()) {
            if($postBackUrl = $client->getPostbackUrl()) {

                try {
                    $this->httpClient->request("POST", $postBackUrl, [
                        'headers' => [
                            'Content-Type' => 'application/json'
                        ],
                        'json' => $datas
                    ]);
                } catch (TransportExceptionInterface $e) {
                    dd($e);
                }

            }
        }
    }
}