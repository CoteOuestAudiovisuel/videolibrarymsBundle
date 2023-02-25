<?php
namespace Coa\VideolibraryBundle\EventListener;

use Psr\Container\ContainerInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Asset\VersionStrategy\StaticVersionStrategy;

class GlobalRequestListener
{
    private $requestStack;
    private $packages;

    public function __construct(Packages $packages, RequestStack $requestStack)
    {
        $this->packages = $packages;
        $this->requestStack = $requestStack;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if ($event->isMainRequest()) {
            $versionStrategy = new StaticVersionStrategy("v1.0");
            $base_url_cdn = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost();
            $package = new UrlPackage($base_url_cdn, $versionStrategy);
            $this->packages->addPackage("coa_videolibrary_host", $package);
        }

        /*$headers = [
            "Access-Control-Allow-Origin" => "*"
        ];
        $event->getRequest()->headers->add($headers);
        $event->stopPropagation();*/
    }
}