<?php

namespace Coa\VideolibraryBundle\Command;

use Coa\VideolibraryBundle\Service\CoaVideolibraryService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;


class CoaVideolibraryStatusCommand extends Command
{
    protected static $defaultName = 'coa:videolibrary:status';
    protected static $defaultDescription = 'Mettre à jour le status les videos en transcodage';
    private CoaVideolibraryService $coaVideolibrary;

    public function __construct(string $name = null, CoaVideolibraryService $coaVideolibrary)
    {
        parent::__construct($name);
        $this->coaVideolibrary = $coaVideolibrary;
    }

    protected function configure(): void
    {
        $this
            ->addOption('max-results', null, InputOption::VALUE_OPTIONAL, "le nombre d'éléments a retourner depuis AWS",20)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title("AWS transcoding status");
        $maxResults = $input->getOption("max-results");
        $result = $this->coaVideolibrary->getStatus($maxResults);
        return Command::SUCCESS;
    }
}
