<?php
namespace Coa\VideolibraryBundle;
use Coa\VideolibraryBundle\DependencyInjection\VideolibraryExtension;


use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class VideolibraryBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $ext = new VideolibraryExtension([],$container);
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

}