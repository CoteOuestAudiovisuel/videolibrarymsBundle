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

            ->scalarNode('aws_access_key_id')
            ->isRequired()
            ->end()

            ->scalarNode('aws_secret_access_key')
            ->isRequired()
            ->end()

            ->scalarNode('aws_region')
            ->isRequired()
            ->end()

            ->scalarNode('mediaconvert_endpoint')
            ->isRequired()
            ->end()

            ->scalarNode('mediaconvert_role_arn')
            ->isRequired()
            ->end()

            ->scalarNode('cloudfront_distrib')
            ->isRequired()
            ->end()

            ->scalarNode('s3_bucket')
            ->isRequired()
            ->end()

            ->scalarNode('keys_folder')
            ->isRequired()
            ->end()

            ->scalarNode('keys_route')
            ->end()

            ->scalarNode('upload_folder')
            ->end()


            ->end();

        return $builder;
    }
}