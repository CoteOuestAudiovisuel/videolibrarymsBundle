<?php

namespace Coa\VideolibraryBundle\Command;

use Coa\VideolibraryBundle\Service\CoaVideolibraryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;


class CoaVideolibraryTranscodeCommand extends Command
{
    protected static $defaultName = 'coa:videolibrary:transcode';
    protected static $defaultDescription = 'commande de transcodage des fichiers uploadés';
    private EntityManagerInterface $em;
    private ContainerBagInterface $container;
    private CoaVideolibraryService $coaVideolibrary;

    public function __construct(string $name = null, EntityManagerInterface $em,
                                ContainerBagInterface $container, CoaVideolibraryService $coaVideolibrary)
    {
        parent::__construct($name);
        $this->em = $em;
        $this->container = $container;
        $this->coaVideolibrary = $coaVideolibrary;
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, "nombre de fichier a transcode en une seul fois",20)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title("Transcode des videos en attente");
        $video_entity = $this->container->get('coa_videolibrary.video_entity');
        $rep = $this->em->getRepository($video_entity);
        $limit = $input->getOption("limit");
        $video_baseurl = $this->container->get('coa_videolibrary.inputfile_baseurl');

        if(null == $video_baseurl){
            do{
                $video_baseurl = trim($io->ask("video_baseurl", "https://kiwi.ci"));
            }while(strlen($video_baseurl) == 0);
        }

        if(($data = $rep->findBy(["state"=>"pending"],["id"=>"ASC"],$limit))){
            foreach ($data as $i=>$video){
                if(!$video->getClient()) continue;

                $io->info(sprintf("fichier %s, code: %s, envoyé en transcodage",$video->getOriginalFilename(), $video->getCode()));
                $hls_key_baseurl = $video->getClient()->getHlsKeyBaseurl();
                $this->coaVideolibrary->transcode($video,$video_baseurl,$hls_key_baseurl);
            }
        }

        $io->success("Fin de l'operation de transcodage");
        return Command::SUCCESS;
    }
}
