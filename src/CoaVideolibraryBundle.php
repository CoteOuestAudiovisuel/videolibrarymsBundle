<?php
namespace Coa\VideolibraryBundle;
use Coa\VideolibraryBundle\DependencyInjection\CoaVideolibraryExtension;


use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CoaVideolibraryBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $ext = new CoaVideolibraryExtension([],$container);
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

}