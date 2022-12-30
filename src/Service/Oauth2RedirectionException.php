<?php

namespace Coa\VideolibraryBundle\Service;

class Oauth2RedirectionException extends \Exception
{
    private int $statusCode;
    private array $headers;

    public function __construct(int $statusCode, array $headers, string $message)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    /**
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }


    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

}