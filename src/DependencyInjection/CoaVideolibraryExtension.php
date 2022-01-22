<?php
namespace Coa\VideolibraryBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * VideolibraryExtension
 */
class CoaVideolibraryExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        $config = $this->processConfiguration(new Configuration(), $configs);
        $container->setParameter('coa_videolibrary', $config);

        foreach ($config as $k=>$v){
            $container->setParameter("coa_videolibrary.$k", $v);
        }
    }


    public function prepend(ContainerBuilder $container)
    {
        $twigConfig = [];
        $twigConfig['paths'][__DIR__.'/../Resources/views'] = "coa_videolibrary";

        $twigConfig['paths'][__DIR__.'/../Resources/public'] = "coa_videolibrary.public";

        $container->prependExtensionConfig('twig', $twigConfig);
    }
}