<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Command/MigrateTroncCommunSemestresCommand.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 02/04/2026 22:30
 */

declare(strict_types=1);

namespace App\Command;

use App\Entity\CampagneCollecte;
use App\Enums\CampagnePublicationTagEnum;
use App\Repository\CampagneCollecteRepository;
use App\Service\Semestre\TroncCommunToMutualisationMigrator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:semestre:migrate-tronc-commun',
    description: 'Transforme les semestres en tronc commun en semestres mutualises pour la campagne courante.'
)]
final class MigrateTroncCommunSemestresCommand extends Command
{
    public function __construct(
        private readonly CampagneCollecteRepository         $campagneCollecteRepository,
        private readonly TroncCommunToMutualisationMigrator $migrator,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Applique reellement les changements (sinon dry-run).')
            ->addOption('campaign', null, InputOption::VALUE_REQUIRED, 'ID de la campagne a traiter (sinon annee_courante puis defaut=true).')
            ->addOption('formation', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Restreint aux IDs de formation fournis (option repetable).')
            ->addOption('porteur', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Mapping formationId:parcoursId (option repetable).')
            ->addOption('mapping-file', null, InputOption::VALUE_REQUIRED, 'Fichier JSON de mapping {"formationId": parcoursId}.')
            ->addOption('allow-cross-formation', null, InputOption::VALUE_NONE, 'Autorise le traitement des semestres deja partages entre plusieurs formations.')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Chemin du rapport JSON.', 'var/log/migrate-tronc-commun-report.json');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $apply = (bool)$input->getOption('apply');
        $allowCrossFormation = (bool)$input->getOption('allow-cross-formation');
        $formationIds = array_map('intval', (array)$input->getOption('formation'));

        $campagne = $this->resolveCampagne($input->getOption('campaign'));
        if ($campagne === null) {
            $io->error('Impossible de determiner la campagne courante. Utilisez --campaign=<id>.');

            return Command::FAILURE;
        }

        $formations = $this->migrator->listFormationsWithTroncCommun($campagne, $formationIds);
        if ($formations === []) {
            $io->success('Aucune formation avec semestre(s) en tronc commun sur la campagne cible.');

            return Command::SUCCESS;
        }

        $porteursByFormationId = $this->buildPorteurMapping($input, $formations);

        $missingMapping = [];
        foreach ($formations as $formation) {
            $formationId = $formation->getId();
            if ($formationId !== null && !isset($porteursByFormationId[$formationId])) {
                $missingMapping[] = $formationId;
            }
        }

        if ($missingMapping !== []) {
            if (!$input->isInteractive()) {
                $io->error(sprintf(
                    'Parcours porteur manquant pour les formations: %s. Fournissez --porteur ou --mapping-file.',
                    implode(', ', $missingMapping),
                ));

                return Command::FAILURE;
            }

            foreach ($formations as $formation) {
                $formationId = $formation->getId();
                if ($formationId === null || isset($porteursByFormationId[$formationId])) {
                    continue;
                }

                $choices = [];
                foreach ($formation->getParcours() as $parcours) {
                    $parcoursId = $parcours->getId();
                    if ($parcoursId === null) {
                        continue;
                    }
                    $choices[(string)$parcoursId] = sprintf('%d - %s', $parcoursId, $parcours->getDisplay());
                }

                if ($choices === []) {
                    $io->warning(sprintf('Formation %d: aucun parcours disponible, ignoree.', $formationId));
                    continue;
                }

                $selectedLabel = $io->choice(
                    sprintf('Formation %d (%s) - choisir le parcours porteur', $formationId, $formation->getDisplayLong()),
                    array_values($choices),
                    array_values($choices)[0]
                );

                $selectedId = array_search($selectedLabel, $choices, true);
                if ($selectedId !== false) {
                    $porteursByFormationId[$formationId] = (int)$selectedId;
                }
            }
        }

        foreach ($formations as $formation) {
            $formationId = $formation->getId();
            if ($formationId !== null && !isset($porteursByFormationId[$formationId])) {
                $io->error(sprintf('Aucun parcours porteur selectionne pour la formation %d.', $formationId));

                return Command::FAILURE;
            }
        }

        if (!$apply) {
            $io->note('Mode dry-run actif: aucun changement ne sera enregistre. Ajouter --apply pour executer la migration.');
        }

        $report = $this->migrator->migrate(
            $campagne,
            $porteursByFormationId,
            $apply,
            $allowCrossFormation,
        );

        $totals = $report['totals'];
        $io->section('Resultat');
        $io->listing([
            sprintf('Campagne: %d - %s (%s)', $campagne->getId(), $campagne->getLibelle(), $campagne->getAnnee()),
            sprintf('Mode: %s', $apply ? 'APPLY' : 'DRY-RUN'),
            sprintf('Formations traitees: %d', (int)$totals['formations']),
            sprintf('Semestres tronc commun analyses: %d', (int)$totals['semestresTroncCommun']),
            sprintf('Mutualisations a creer/creees: %d', (int)$totals['mutualisationsCreated']),
            sprintf('Semestres detaches a creer/crees: %d', (int)$totals['semestresDetaches']),
            sprintf('Semestres deja detaches: %d', (int)$totals['alreadyDetached']),
            sprintf('Warnings: %d', (int)$totals['warnings']),
            sprintf('Errors: %d', (int)$totals['errors']),
        ]);

        $outputPath = (string)$input->getOption('output');
        $this->writeReport($outputPath, $report);
        $io->text(sprintf('Rapport JSON ecrit dans `%s`.', $outputPath));

        if ((int)$totals['errors'] > 0) {
            return Command::FAILURE;
        }

        $io->success($apply ? 'Migration terminee.' : 'Dry-run termine.');

        return Command::SUCCESS;
    }

    private function resolveCampagne(mixed $campaignOption): ?CampagneCollecte
    {
        if ($campaignOption !== null) {
            return $this->campagneCollecteRepository->find((int)$campaignOption);
        }

        $campagne = $this->campagneCollecteRepository->findOneBy([
            'publicationTag' => CampagnePublicationTagEnum::ANNEE_COURANTE->value,
        ]);

        if ($campagne instanceof CampagneCollecte) {
            return $campagne;
        }

        return $this->campagneCollecteRepository->findOneBy(['defaut' => true]);
    }

    /**
     * @param array<int, mixed> $formations
     * @return array<int, int>
     */
    private function buildPorteurMapping(InputInterface $input, array $formations): array
    {
        $mapping = [];

        $mappingFile = $input->getOption('mapping-file');
        if (is_string($mappingFile) && $mappingFile !== '') {
            $raw = file_get_contents($mappingFile);
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($decoded)) {
                foreach ($decoded as $formationId => $parcoursId) {
                    $mapping[(int)$formationId] = (int)$parcoursId;
                }
            }
        }

        foreach ((array)$input->getOption('porteur') as $item) {
            if (!is_string($item) || !str_contains($item, ':')) {
                continue;
            }

            [$formationId, $parcoursId] = explode(':', $item, 2);
            $mapping[(int)$formationId] = (int)$parcoursId;
        }

        // On limite le mapping aux formations effectivement traitees.
        $authorizedFormationIds = [];
        foreach ($formations as $formation) {
            if ($formation->getId() !== null) {
                $authorizedFormationIds[$formation->getId()] = true;
            }
        }

        return array_filter(
            $mapping,
            static fn(int $formationId): bool => isset($authorizedFormationIds[$formationId]),
            ARRAY_FILTER_USE_KEY,
        );
    }

    private function writeReport(string $path, array $report): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Impossible de creer le dossier de rapport: %s', $dir));
        }

        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}


