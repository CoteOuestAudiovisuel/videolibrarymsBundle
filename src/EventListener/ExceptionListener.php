<?php

namespace Coa\VideolibraryBundle\EventListener;

use Coa\VideolibraryBundle\Service\APIException;
use Coa\VideolibraryBundle\Service\Oauth2Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

class ExceptionListener
{
    public function onKernelException(ExceptionEvent $event)
    {
        $exception = $event->getThrowable();
        $response = new JsonResponse();

        if($exception instanceof APIException) {

            $response->setData([
                'code' => $exception->getErrorCode(),
                'type' => $exception->getErrorType(),
                'message' => $exception->getMessage(),
                'detail' => $exception->getState()
            ]);

            $response->setStatusCode($exception->getStatusCode());

            $event->setResponse($response);
        } else if($exception instanceof Oauth2Exception) {

            $response->setData([
                'code' => $exception->getErrorType(),
                'message' => $exception->getMessage(),
                'detail' => $exception->getState()
            ]);

            $headers = [
                'Content-Type' => 'application/json;charset=UTF-8',
                'Cache-Control' => 'no-store',
                'Pragma' => 'no-cache'
            ];

            $response->setStatusCode($exception->getStatusCode());
            $response->headers->add($headers);

            $event->setResponse($response);
        }
    }
}