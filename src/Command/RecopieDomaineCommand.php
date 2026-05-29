<?php

namespace App\Command;

use App\Repository\MentionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:recopie-domaine',
    description: 'Add a short description for your command',
)]
class RecopieDomaineCommand extends Command
{
    public function __construct(
        private MentionRepository      $mentionRepository,
        private EntityManagerInterface $entityManager,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        //récupérer toutes les mentions
        $mentions = $this->mentionRepository->findAll();
        $io->info(sprintf('Found %d mentions', count($mentions)));

        // pour chaque mention récupérer le domaine associé et l'ajouter dans la liste des domaines de la mention
        foreach ($mentions as $mention) {
            $domaine = $mention->getDomaine();
            if ($domaine) {
                $io->info(sprintf('Processing mention: %s with domaine: %s', $mention->getLibelle(), $domaine->getLibelle()));
                // Ajouter le domaine à la mention
                if (!$mention->getDomaines()->contains($domaine)) {
                    $mention->addDomaine($domaine);
                }
            } else {
                $io->warning(sprintf('Mention %s has no associated domaine.', $mention->getLibelle()));
            }
        }

        // Enregistrer les modifications
        $this->entityManager->flush();

        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');

        return Command::SUCCESS;
    }
}
