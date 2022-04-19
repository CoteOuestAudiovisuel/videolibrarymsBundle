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
        //$ext = new CoaVideolibraryExtension([],$container);
    }

    public function boot()
    {
        $container = $this->container;

        $ftp_dirname = "coa_videolibrary_ftp";
        $keys_dirname = "coa_videolibrary_keys";
        $upload_dirname = "coa_videolibrary_upload";

        $ftp_path = $container->getParameter('kernel.project_dir')."/".$ftp_dirname;
        $keys_path = $container->getParameter('kernel.project_dir')."/".$keys_dirname;
        $upload_path = $container->getParameter('kernel.project_dir')."/public/".$upload_dirname;
        # creation du dossier des clés aes
        if(!file_exists($keys_path)){
            mkdir($keys_path);
        }
        # creation du dossier upload
        if(!file_exists($upload_path)){
            mkdir($upload_path);
        }
        # creation du dossier ftp
        if(!file_exists($ftp_path)){
            mkdir($ftp_path);
        }

        // creation du fichier de configuration
        $config_path = $container->getParameter('kernel.project_dir')."/config/packages/coa_videolibrary.yaml";
        if(!file_exists($config_path)){
            copy(__DIR__.'/Resources/config/packages/coa_videolibrary.yaml',$config_path);
        }

        // creation du fichier des routes
        $config_path = $container->getParameter('kernel.project_dir')."/config/routes/coa_videolibrary.yaml";
        if(!file_exists($config_path)){
            copy(__DIR__ . '/Resources/config/routes/coa_videolibrary.yaml',$config_path);
        }

        // dossiers en .gitignore
        $gitignore_path = $container->getParameter('kernel.project_dir')."/.gitignore";
        if(file_exists($gitignore_path)){
            $ignoresData = file_get_contents($gitignore_path);
            $ignoresData = explode("\n", $ignoresData);
            $keys_in_gitingore = false;
            $upload_in_gitingore = false;
            $ftp_in_gitingore = false;

            foreach ($ignoresData as $item){
                $item = trim($item);
                if(false !== stripos($item,$keys_dirname)){
                    $keys_in_gitingore = true;
                }
                else if(false !== stripos($item,$upload_dirname)){
                    $upload_in_gitingore = true;
                }
                else if(false !== stripos($item,$ftp_dirname)){
                    $ftp_in_gitingore = true;
                }
            }

            if(!$ftp_in_gitingore){
                $ignoresData[] = "/".$ftp_dirname;
            }
            if(!$keys_in_gitingore){
                $ignoresData[] = "/".$keys_dirname;
            }
            if(!$upload_in_gitingore){
                $ignoresData[] = "/public/".$upload_dirname;
            }
            file_put_contents($gitignore_path, implode("\n",$ignoresData));
        }

        parent::boot();
    }

    public function shutdown()
    {
        $container = $this->container;
        // supression du fichier de configuration
        $config_path = $container->getParameter('kernel.project_dir')."/config/packages/coa_videolibrary.yaml";
        if(file_exists($config_path)){
            @unlink($config_path);
        }

        // supression du fichier des routes
        $config_path = $container->getParameter('kernel.project_dir')."/config/routes/coa_videolibrary.yaml";
        if(file_exists($config_path)){
            @unlink($config_path);
        }

        parent::shutdown();
    }

    /**
     * {@inheritdoc}
     */
    public function registerCommands(Application $application)
    {
        // noop
    }
}