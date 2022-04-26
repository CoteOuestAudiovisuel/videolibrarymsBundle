<?php
namespace Coa\VideolibraryBundle;
use Coa\VideolibraryBundle\DependencyInjection\CoaVideolibraryExtension;


use Symfony\Component\Console\Application;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CoaVideolibraryBundle extends Bundle
{

    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $ext = new CoaVideolibraryExtension([],$container);
    }

    /**
     * {@inheritdoc}
     */
    public function registerCommands(Application $application)
    {
        // noop
    }
}