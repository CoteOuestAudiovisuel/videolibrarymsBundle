<?php

namespace Coa\VideolibraryBundle\Service;

use Throwable;

class Oauth2Exception extends \Exception
{
    private $errorType;
    private $statusCode;
    private $errorUri;
    private $state;

    public function __construct($errorType, $statusCode, $message)
    {
        parent::__construct($message);
        $this->errorType = $errorType;
        $this->statusCode = $statusCode;
    }

    /**
     * @return mixed
     */
    public function getErrorType()
    {
        return $this->errorType;
    }


    /**
     * @return mixed
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * @return mixed
     */
    public function getErrorUri()
    {
        return $this->errorUri;
    }

    /**
     * @param mixed $errorUri
     */
    public function setErrorUri($errorUri): void
    {
        $this->errorUri = $errorUri;
    }

    /**
     * @return mixed
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @param mixed $state
     */
    public function setState($state): void
    {
        $this->state = $state;
    }
}