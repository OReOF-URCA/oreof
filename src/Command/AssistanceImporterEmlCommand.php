<?php

namespace App\Command;

use App\Service\Assistance\IntegrationMailAssistance;
use App\Service\Assistance\Source\EmlSource;
use App\Service\Assistance\Source\MailEntrantFactory;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'app:assistance:importer-eml',
    description: 'Importe des mails .eml (fichier ou dossier) dans le module Assistance',
)]
class AssistanceImporterEmlCommand extends Command
{
    public function __construct(
        private readonly IntegrationMailAssistance $integration,
        private readonly MailEntrantFactory $factory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('chemin', InputArgument::REQUIRED, 'Fichier .eml ou dossier contenant des .eml');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $nombre = $this->integration->integrerTout(
                new EmlSource((string) $input->getArgument('chemin'), $this->factory),
                new DateTimeImmutable()
            );
        } catch (Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('%d mail(s) intégré(s).', $nombre));

        return Command::SUCCESS;
    }
}
