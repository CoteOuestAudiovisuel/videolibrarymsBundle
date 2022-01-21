<?php
namespace Coa\VideolibraryBundle\DependencyInjection;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class Configuration implements ConfigurationInterface
{
    function getConfigTreeBuilder()
    {
        $builder = new TreeBuilder('coa_videolibrary');

        $rootNode = $builder->getRootNode();
        $rootNode
            ->children()

            ->scalarNode('AWS_ACCESS_KEY_ID')
            ->isRequired()
            ->end()

            ->scalarNode('AWS_SECRET_ACCESS_KEY')
            ->isRequired()
            ->end()

            ->scalarNode('AWS_ACCOUNT_ENDPOINT')
            ->isRequired()
            ->end()

            ->scalarNode('MEDIA_CONVERT_ROLE_ARN')
            ->isRequired()
            ->end()

            ->scalarNode('CLOUD_FRONT_DISTRIB')
            ->isRequired()
            ->end()

            ->end();

        return $builder;
    }
}