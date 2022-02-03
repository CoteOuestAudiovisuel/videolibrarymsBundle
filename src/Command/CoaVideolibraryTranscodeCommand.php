<?php

namespace Coa\VideolibraryBundle\Command;

use Coa\VideolibraryBundle\Service\MediaConvertService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\RequestStack;


class CoaVideolibraryTranscodeCommand extends Command
{
    protected static $defaultName = 'coa:videolibrary:transcode';
    protected static $defaultDescription = 'commande de transcodage des fichiers uploadés';
    private EntityManagerInterface $em;
    private MediaConvertService $mediaConvert;
    private ContainerInterface $container;
    private Packages $packages;

    public function __construct(string $name = null, EntityManagerInterface $em, MediaConvertService $mediaConvert, ContainerInterface $container, Packages $packages)
    {
        parent::__construct($name);
        $this->em = $em;
        $this->mediaConvert = $mediaConvert;
        $this->container = $container;
        $this->packages = $packages;
    }

    protected function configure(): void
    {
        $this
            ->addOption('hls-key-baseurl', 'k', InputOption::VALUE_REQUIRED, "base url pour les clés DRM")
            ->addOption('video-baseurl', 'b', InputOption::VALUE_REQUIRED, "base url d'acces aux fichiers mp4 en HTTP")
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, "nombre de fichier a transcode en une seul fois",10)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title("Transcode des videos en attente");
        $video_entity = $this->container->getParameter('coa_videolibrary.video_entity');
        $rep = $this->em->getRepository($video_entity);
        $videosPath = $this->container->getParameter('kernel.project_dir') . "/public/coa_videolibrary";
        $limit = $input->getOption("limit");
        $video_baseurl = $input->getOption("video-baseurl");
        $hls_key_baseurl = $input->getOption("hls-key-baseurl");


        if(!file_exists($videosPath)){
            mkdir($videosPath);
        }

        //dd($video_baseurl,$hls_key_baseurl);

        if(null == $video_baseurl){
            do{
                $video_baseurl = trim($io->ask("video_baseurl", "https://mynina.tv"));
            }while(strlen($video_baseurl) == 0);

        }

        if(null == $hls_key_baseurl){
            do{
                $hls_key_baseurl = trim($io->ask("hls_key_baseurl", "https://mynina.tv"));
            }while(strlen($hls_key_baseurl) ==0);
        }

        if(($data = $rep->findBy(["state"=>"pending"],["id"=>"ASC"],$limit))){
            foreach ($data as $i=>$video){
                $code = $video->getCode();
                $io->info(sprintf("fichier %s, code: %s, envoyé en transcodage",$video->getOriginalFilename(), $video->getCode()));
                $input_path = $videosPath."/".$code.'.mp4';
                if(!file_exists($input_path)) continue;

                $inputfile = $video_baseurl.$this->packages->getUrl('/coa_videolibrary/'.$code.'.mp4');
                $keyfilename = $code;
                $bucket = $this->container->getParameter("coa_videolibrary.s3_bucket");
                $region = $this->container->getParameter("coa_videolibrary.aws_region");
                $keyurl = $hls_key_baseurl.$this->container->getParameter("coa_videolibrary.keys_route") . "/" . $keyfilename;

                try {
                    $job = $this->mediaConvert->createJob($inputfile,$keyfilename,$keyurl,$bucket);
                    $video->setJobRef($job["data"]["id"]);
                    $video->setState("SUBMITTED");
                }catch (\Exception $e){
                    throw $e;
                }

                $video->setBucket($bucket);
                $video->setRegion($region);
                $video->setJobPercent(0);

                $this->em->persist($video);
            }
            $this->em->flush();
        }

        $io->success("Fin de l'operation de transcodage");
        return Command::SUCCESS;
    }
}
