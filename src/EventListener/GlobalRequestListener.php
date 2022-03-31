<?php
namespace Coa\VideolibraryBundle\EventListener;

use Psr\Container\ContainerInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class GlobalRequestListener
{
    private ContainerInterface $container;
    private RequestStack $requestStack;
    private Packages $packages;

    public function __construct(ContainerInterface $containerInterface, RequestStack $requestStack, Packages $packages)
    {
        $this->container = $containerInterface;
        $this->requestStack = $requestStack;
        $this->packages = $packages;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if ($event->isMainRequest()) {
            $versionStrategy = new EmptyVersionStrategy();
            $package = new UrlPackage("https://kiwitv.s3.us-east-2.amazonaws.com/", $versionStrategy);
            $this->packages->addPackage("kiwitv", $package);
        }
    }
}