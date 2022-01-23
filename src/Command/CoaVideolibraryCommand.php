<?php

namespace App\Command;

use Coa\VideolibraryBundle\Entity\Video;
use Coa\VideolibraryBundle\Service\MediaConvertService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CoaVideolibraryCommand extends Command
{
    protected static $defaultName = 'coa:videolibrary';
    protected static $defaultDescription = 'commande de transcodage des fichiers uploadés';
    private EntityManagerInterface $em;
    private MediaConvertService $mediaConvert;

    public function __construct(string $name = null,EntityManagerInterface $em, MediaConvertService $mediaConvert)
    {
        parent::__construct($name);
        $this->em = $em;
        $this->mediaConvert = $mediaConvert;
    }

    protected function configure(): void
    {
        $this
            ->addOption('sync', null, InputOption::VALUE_NONE, 'synchronise le dossier de depot')
            ->addOption('transcode', null, InputOption::VALUE_NONE, 'lance le trancodage')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isTranscoding = filter_var($input->getOption('transcode'), FILTER_VALIDATE_BOOLEAN);
        $io->title("Gestion videos");
        $rep = $this->em->getRepository(Video::class);
        $videosPath = __DIR__."/../../public/coa_videolibrary";

        if(!file_exists($videosPath)){
            mkdir($videosPath);
        }

        //dd($input->getOption('sync'));

        // creation des entités video a partir des depots de fichiers dans le dossier de synchronisation
        if($input->getOption('sync')){
            foreach (glob(sprintf("%s/*.mp4",$videosPath)) as $filename) {
                $basename =  basename($filename);
                $basedir =  dirname($filename);

                if(!($video = $rep->findOneBy(["code"=>$basename]))){

                    $code = substr(trim(base64_encode(bin2hex(openssl_random_pseudo_bytes(32,$ok))),"="),0,32);
                    $file_length = filesize($filename);
                    $newFilename = $basedir . "/" . $code . ".mp4";

                    $video = new Video();
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
                foreach ($data as $i=>$el){

//                    $io->info(sprintf("fichier %s, code: %s, envoyé en transcodage",$basename, $code));
//
//                    $baseurl = $request->getScheme() . '://' . $request->getHttpHost() . $request->getBasePath();
//                    $baseurl = "https://kiwi.loca.lt";
//
//                    // c'est ici qu'on lance le processus de transcodage sur AWS
//                    $inputfile = $baseurl.$packages->getUrl('/coa_videolibrary/'.$code.'.mp4');
//                    $keyfilename = $code;
//                    $bucket = $this->getParameter("coa_videolibrary.s3_bucket");
//                    $keyurl = $baseurl.$this->getParameter("coa_videolibrary.keys_route") . "/" . $keyfilename;
//
//                    $result['inputfile'] = $inputfile;
//                    try {
//                        $job = $mediaConvert->createJob($inputfile,$keyfilename,$keyurl,$bucket);
//                        $video->setJobRef($job["data"]["id"]);
//                        $video->setState("SUBMITTED");
//                    }catch (\Exception $e){
//                        throw $e;
//                    }
//
//                    $video->setBucket($bucket);
//                    $video->setJobPercent(0);
                }
            }
        }

        return Command::SUCCESS;
    }
}
