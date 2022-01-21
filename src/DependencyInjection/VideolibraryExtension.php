<?php
namespace Coa\VideolibraryBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * VideolibraryExtension
 */
class VideolibraryExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        $container->setParameter('videolibrary.my_var_string', $configs['my_var_string']);
        $container->setParameter('videolibrary.my_array', $configs['my_array']);
        $container->setParameter('videolibrary.my_integer', $configs['my_integer']);
        $container->setParameter('videolibrary.my_var_string_option', $configs['my_var_string_option']);
    }


}