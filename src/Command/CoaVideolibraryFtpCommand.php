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


class CoaVideolibraryFtpCommand extends Command
{
    protected static $defaultName = 'coa:videolibrary:ftp';
    protected static $defaultDescription = 'commande de synchronisation des fichiers upload par ftp';
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

    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title("Synchronisation Upload video par FTP");
        $video_entity = $this->container->getParameter('coa_videolibrary.video_entity');
        $rep = $this->em->getRepository($video_entity);
        $ftpPath = $this->container->getParameter('kernel.project_dir') . "/coa_videolibrary_ftp";
        $destPath = $this->container->getParameter('kernel.project_dir') . "/public/coa_videolibrary";

        if(!file_exists($ftpPath)){
            mkdir($ftpPath);
        }

        foreach (glob(sprintf("%s/*.mp4",$ftpPath)) as $filename) {
            $basename =  basename($filename);
            $basedir =  dirname($filename);

            $code = substr(trim(base64_encode(bin2hex(openssl_random_pseudo_bytes(32,$ok))),"="),0,32);
            $file_length = filesize($filename);
            $newFilename = $destPath . "/" . $code . ".mp4";

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

        $io->success("Fin de l'operation de synchronisation FTP");
        return Command::SUCCESS;
    }
}
