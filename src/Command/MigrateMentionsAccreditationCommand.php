<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Accreditation;
use App\Entity\Formation;
use App\Entity\Mention;
use App\Repository\AccreditationRepository;
use App\Repository\FormationRepository;
use App\Repository\MentionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:transfer-mentions',
    description: 'Transfère les mentions vers une accréditation (2024-2028), unifie les doublons et initialise formation.classique'
)]
class MigrateMentionsAccreditationCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AccreditationRepository $accreditationRepository,
        private readonly MentionRepository $mentionRepository,
        private readonly FormationRepository $formationRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Exécuter sans enregistrer en base')
            ->addOption('accreditation-label', null, InputOption::VALUE_REQUIRED, 'Libellé de la période d\'accréditation', '2024-2028')
            ->addOption('annee-debut', null, InputOption::VALUE_REQUIRED, 'Année de début', '2024')
            ->addOption('annee-fin', null, InputOption::VALUE_REQUIRED, 'Année de fin', '2028');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $label = (string) $input->getOption('accreditation-label');
        $anneeDebut = (int) $input->getOption('annee-debut');
        $anneeFin = (int) $input->getOption('annee-fin');

        $io->title('Migration et refonte des mentions & accréditations');
        if ($dryRun) {
            $io->note('Mode DRY-RUN actif : aucune modification ne sera persistée.');
        }

        // 1. Période d'accréditation
        $accreditation = $this->accreditationRepository->findOneBy(['libelle' => $label]);
        if ($accreditation === null) {
            $accreditation = new Accreditation();
            $accreditation->setLibelle($label);
            $accreditation->setAnneeDebut($anneeDebut);
            $accreditation->setAnneeFin($anneeFin);
            $accreditation->setActif(true);
            $this->entityManager->persist($accreditation);
            $io->success(sprintf('Création de la période d\'accréditation "%s" (%d-%d)', $label, $anneeDebut, $anneeFin));
        } else {
            $io->info(sprintf('Période d\'accréditation "%s" trouvée (id: %d)', $label, $accreditation->getId()));
        }

        // 2. Initialisation de Formation.classique
        $formations = $this->formationRepository->findAll();
        $updatedFormations = 0;
        foreach ($formations as $formation) {
            if ($formation->getClassique() === null) {
                $isClassique = $formation->getTypeDiplome()?->isClassique() ?? true;
                $formation->setClassique($isClassique);
                $updatedFormations++;
            }
        }
        $io->info(sprintf('%d formation(s) mise(s) à jour avec le champ classique.', $updatedFormations));

        // 3. Déduplication et rattachement des mentions
        $mentions = $this->mentionRepository->findAll();
        $groupedByLibelle = [];

        foreach ($mentions as $mention) {
            $key = mb_strtolower(trim((string)$mention->getLibelle()));
            $groupedByLibelle[$key][] = $mention;
        }

        $mergedCount = 0;
        $totalMentionsBefore = count($mentions);

        foreach ($groupedByLibelle as $group) {
            /** @var Mention $canonical */
            $canonical = array_shift($group);
            $canonical->setAccreditation($accreditation);

            /** @var Mention $duplicate */
            foreach ($group as $duplicate) {
                // Transférer les domaines
                foreach ($duplicate->getDomaines() as $domaine) {
                    if (!$canonical->getDomaines()->contains($domaine)) {
                        $canonical->addDomaine($domaine);
                    }
                }

                // Transférer les formations
                foreach ($duplicate->getFormations() as $f) {
                    $f->setMention($canonical);
                }

                // Supprimer le doublon
                $this->entityManager->remove($duplicate);
                $mergedCount++;
            }
        }

        $io->info(sprintf(
            '%d mention(s) traitée(s) : %d fusionnée(s)/supprimée(s), %d mention(s) conservée(s) pour l\'accréditation "%s".',
            $totalMentionsBefore,
            $mergedCount,
            $totalMentionsBefore - $mergedCount,
            $label
        ));

        if (!$dryRun) {
            $this->entityManager->flush();
            $io->success('Modifications appliquées et enregistrées avec succès en base de données.');
        } else {
            $io->info('Dry-run terminé sans flush.');
        }

        return Command::SUCCESS;
    }
}

