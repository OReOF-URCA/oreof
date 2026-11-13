<?php

namespace App\Command;

use App\Service\Assistance\IntegrationMailAssistance;
use App\Service\Assistance\Source\ImapSource;
use App\Service\Assistance\VerrouReleveAssistance;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'app:assistance:relever',
    description: 'Relève la boîte IMAP d\'assistance et regroupe les nouveaux mails en demandes',
)]
class AssistanceReleverCommand extends Command
{
    public function __construct(
        private readonly IntegrationMailAssistance $integration,
        private readonly ImapSource $source,
        private readonly VerrouReleveAssistance $verrou,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('depuis', null, InputOption::VALUE_REQUIRED, 'Nombre de jours à relire', '7');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->source->estConfiguree()) {
            $io->error('Boîte IMAP non configurée (variables ASSISTANCE_IMAP_*).');

            return Command::FAILURE;
        }

        if (!$this->verrou->acquerir()) {
            $io->warning('Une relève est déjà en cours.');

            return Command::SUCCESS;
        }

        $jours = max(1, (int) $input->getOption('depuis'));

        try {
            $nombre = $this->integration->integrerTout($this->source, new DateTimeImmutable(sprintf('-%d days', $jours)));
        } catch (Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } finally {
            $this->verrou->liberer();
        }

        $io->success(sprintf('%d mail(s) intégré(s).', $nombre));

        return Command::SUCCESS;
    }
}
