<?php

namespace Coa\VideolibraryBundle\Service;

class APIException extends Oauth2Exception
{
    private int $errorCode;

    public function __construct($errorCode, $errorType, $errorDescription, $statusCode)
    {
        parent::__construct($errorType, $statusCode, $errorDescription);
        $this->errorCode = $errorCode;
    }

    /**
     * @return int
     */
    public function getErrorCode(): int
    {
        return $this->errorCode;
    }
}