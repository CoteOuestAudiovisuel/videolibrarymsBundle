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
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('aws_access_key_id')
                    ->info('access_key_id du compte aws')
                    ->defaultNull()
                ->end()

                ->scalarNode('aws_secret_access_key')
                    ->info('secret_access_key du compte aws')
                    ->defaultNull()
                ->end()

                ->scalarNode('aws_region')
                    ->info('la région definie, du bucket aws')
                    ->defaultNull()
                ->end()

                ->scalarNode('mediaconvert_endpoint')
                    ->info("l'endpoint sur laquelle requete la creation de tâche mediaconvert")
                    ->defaultNull()
                ->end()

                ->scalarNode('mediaconvert_role_arn')
                    ->info('ARN du role à utiliser pour mediaconvert')
                    ->defaultNull()
                ->end()

                ->scalarNode('s3_bucket')
                    ->info('le nom du bucket dans lequel les fichiers transcodés seront stockés')
                    ->defaultNull()
                ->end()


                ->scalarNode('keys_route')
                    ->defaultValue("/keys")
                    ->info('la route par default, pour les requete GET AES Key')
                ->end()

                ->scalarNode('hls_key_baseurl')
                    ->info("la base url pour l'access GET aux  clés")
                    ->defaultNull()
                ->end()

                ->scalarNode('inputfile_baseurl')
                    ->info("la base url pour l'access aux fichiers mp4")
                    ->defaultNull()
                ->end()

                ->scalarNode('video_entity')
                    ->info("l'entité Video à utiliser pour les opération de CRUD")
                    ->defaultNull()
                ->end()

                ->scalarNode('prefix')
                    ->info("le prefix des noms crées")
                    ->defaultValue("media")
                ->end()

                ->scalarNode('token')
                    ->info("le token identifiant ce client dans la constellation videolibrary")
                    ->defaultValue("vRsJ5P-y_8Qd2am")
                ->end()

                ->arrayNode('constellation')
                    ->info("information du client dans la constellation")
                    ->children()
                        ->scalarNode('name')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('id')
                            ->defaultNull()
                        ->end()
                        ->booleanNode('enabled')
                            ->info("le status du service dans la constellation")
                            ->defaultValue(true)
                        ->end()
                        ->scalarNode('token')
                            ->info("l'identifiant dans la constellation")
                            ->defaultValue("vRsJ5P-y_8Qd2am")
                        ->end()

                        ->arrayNode('connections')
                            ->defaultValue([])
                            ->info("les bases de données faisant partie de la constellation")
                            ->useAttributeAsKey('name')
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('service')
                                        ->isRequired()
                                    ->end()
                                    ->scalarNode('domain')
                                        ->isRequired()
                                    ->end()
                                    ->booleanNode('enabled')
                                        ->defaultValue(true)
                                    ->end()
                                    ->scalarNode('endpoint')
                                        ->defaultValue("/constellation")
                                    ->end()
                                    ->scalarNode('token')
                                        ->isRequired()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $builder;
    }
}