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


class CoaVideolibraryCommand extends Command
{
    protected static $defaultName = 'coa:videolibrary';
    protected static $defaultDescription = 'commande de transcodage des fichiers uploadés';
    private EntityManagerInterface $em;
    private MediaConvertService $mediaConvert;
    private ContainerInterface $container;
    private Packages $packages;

    public function __construct(
        string $name = null,
        EntityManagerInterface $em,
        MediaConvertService $mediaConvert,
        ContainerInterface $container,
        Packages $packages
    )
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
            ->addOption('hls-key-baseurl', null, InputOption::VALUE_REQUIRED, "l'url pour les clés DRM")
            ->addOption('sync', null, InputOption::VALUE_NONE, 'synchronise le dossier de depot')
            ->addOption('transcode', null, InputOption::VALUE_NONE, 'lance le trancodage')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isTranscoding = filter_var($input->getOption('transcode'), FILTER_VALIDATE_BOOLEAN);
        $io->title("Gestion videos");
        $video_entity = $this->container->getParameter('coa_videolibrary.video_entity');
        $rep = $this->em->getRepository($video_entity);
        $videosPath = $this->container->getParameter('kernel.project_dir') . "/public/coa_videolibrary";

        if(!file_exists($videosPath)){
            mkdir($videosPath);
        }

        // creation des entités video a partir des depots de fichiers dans le dossier de synchronisation
        if($input->getOption('sync')){
            foreach (glob(sprintf("%s/*.mp4",$videosPath)) as $filename) {
                $basename =  basename($filename);
                $basedir =  dirname($filename);

                if(!($video = $rep->findOneBy(["code"=>$basename]))){

                    $code = substr(trim(base64_encode(bin2hex(openssl_random_pseudo_bytes(32,$ok))),"="),0,32);
                    $file_length = filesize($filename);
                    $newFilename = $basedir . "/" . $code . ".mp4";

                    $video = new $video_entity();
                    $video->setCode($code);
                    $video->setOriginalFilename($basename);
                    $video->setFileSize($file_length);
                    $video->setState("pending");
                    $video->setIsTranscoded(false);
                    $video->setCreatedAt(new \DateTimeImmutable());
                    $this->em->persist($video);
                    $this->em->flush();

                    rename($filename, $newFilename);
                }
            }
        }

        // lance le transcodage
        if($input->getOption("transcode")){
            if(($data = $rep->findBy(["state"=>"pending"],["id"=>"ASC"],10))){
                $baseurl = $input->getOption("hls-key-baseurl");
                foreach ($data as $i=>$el){
                    $code = $el->getCode();
                    $io->info(sprintf("fichier %s, code: %s, envoyé en transcodage",$el->getOriginalFilename(), $el->getCode()));
                    $inputfile = $baseurl.$this->packages->getUrl('/coa_videolibrary/'.$code.'.mp4');
                    $keyfilename = $code;
                    $bucket = $this->container->getParameter("coa_videolibrary.s3_bucket");
                    $region = $this->container->getParameter("coa_videolibrary.aws_region");
                    $keyurl = $baseurl.$this->container->getParameter("coa_videolibrary.keys_route") . "/" . $keyfilename;

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
        }
        return Command::SUCCESS;
    }
}
