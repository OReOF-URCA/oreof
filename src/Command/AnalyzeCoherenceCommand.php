<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\CampagneCollecte;
use App\Entity\Parcours;
use App\Repository\CampagneCollecteRepository;
use App\Repository\ParcoursRepository;
use App\Service\Coherence\CoherenceAnalyzerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:coherence:analyze',
    description: 'Analyse la cohérence textuelle et structurelle des parcours (pré-filtre déterministe et IA optionnelle).',
)]
final class AnalyzeCoherenceCommand extends Command
{
    public function __construct(
        private readonly ParcoursRepository         $parcoursRepository,
        private readonly CampagneCollecteRepository $campagneCollecteRepository,
        private readonly CoherenceAnalyzerService   $coherenceAnalyzer,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('campagne', null, InputOption::VALUE_REQUIRED, 'ID de la campagne de collecte (défaut : campagne active ou la plus récente)')
            ->addOption('parcours', null, InputOption::VALUE_REQUIRED, 'ID d\'un parcours spécifique à analyser')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre maximum de parcours à analyser', '0')
            ->addOption('no-ai', null, InputOption::VALUE_NONE, 'Exécuter uniquement le pré-filtre déterministe sans appel LLM')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Forcer la réanalyse même si le hash de contenu est inchangé');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $useAi = !$input->getOption('no-ai');
        $force = (bool)$input->getOption('force');
        $limit = max(0, (int)$input->getOption('limit'));

        $parcoursIdOption = $input->getOption('parcours');
        if ($parcoursIdOption !== null) {
            $parcours = $this->parcoursRepository->find((int)$parcoursIdOption);
            if ($parcours === null) {
                $io->error(sprintf('Parcours #%s introuvable.', (string)$parcoursIdOption));

                return Command::FAILURE;
            }
            $parcoursList = [$parcours];
        } else {
            $campagne = $this->resolveCampagne($input->getOption('campagne'));
            if ($campagne === null) {
                $io->error('Aucune campagne de collecte trouvée.');

                return Command::FAILURE;
            }
            $io->title(sprintf('Analyse de cohérence — Campagne %s', $campagne->getLibelle()));
            $qb = $this->parcoursRepository->createQueryBuilder('p')
                ->leftJoin('p.dpeParcours', 'dp')
                ->leftJoin('p.formation', 'f')
                ->andWhere('dp.campagneCollecte = :campagne OR f.dpe = :campagne')
                ->setParameter('campagne', $campagne)
                ->distinct()
                ->orderBy('p.id', 'ASC');

            if ($limit > 0) {
                $qb->setMaxResults($limit);
            }

            /** @var list<Parcours> $parcoursList */
            $parcoursList = $qb->getQuery()->getResult();
        }

        if ($parcoursList === []) {
            $io->info('Aucun parcours à analyser.');

            return Command::SUCCESS;
        }

        $counts = ['ok' => 0, 'warning' => 0, 'alert' => 0];
        $io->progressStart(count($parcoursList));

        foreach ($parcoursList as $parcours) {
            $result = $this->coherenceAnalyzer->analyzeParcours($parcours, useAi: $useAi, forceRefresh: $force, persist: true);
            if (isset($counts[$result->status])) {
                $counts[$result->status]++;
            }
            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success(sprintf(
            '%d parcours analysé(s) : %d cohérent(s), %d à vérifier, %d alerte(s).',
            count($parcoursList),
            $counts['ok'],
            $counts['warning'],
            $counts['alert']
        ));

        return Command::SUCCESS;
    }

    private function resolveCampagne(mixed $campagneId): ?CampagneCollecte
    {
        if ($campagneId !== null && (string)$campagneId !== '') {
            return $this->campagneCollecteRepository->find((int)$campagneId);
        }

        return $this->campagneCollecteRepository->findOneBy(['defaut' => true])
            ?? $this->campagneCollecteRepository->findOneBy([], ['id' => 'DESC']);
    }
}
