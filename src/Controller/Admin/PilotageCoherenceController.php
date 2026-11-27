<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\DataTable\PilotageCoherenceDataTable;
use App\Entity\CampagneCollecte;
use App\Entity\CoherenceAnalyse;
use App\Entity\Parcours;
use App\Message\AnalyzeParcoursCoherence;
use App\Navigation\Breadcrumb\Attribute\Breadcrumb;
use App\Repository\CoherenceAnalyseRepository;
use App\Repository\ParcoursRepository;
use App\Service\Coherence\CoherenceAnalyzerService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/administration/pilotage-coherence', name: 'app_admin_pilotage_coherence_')]
#[IsGranted('ROLE_ADMIN')]
class PilotageCoherenceController extends BaseController
{
    public function __construct(
        private readonly CoherenceAnalyseRepository $analyseRepository,
        private readonly ParcoursRepository         $parcoursRepository,
        private readonly CoherenceAnalyzerService   $coherenceAnalyzer,
    )
    {
    }

    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    #[Breadcrumb(menuKey: 'pilotage')]
    #[Breadcrumb(label: 'menu.admin.coherence_ia')]
    public function index(PilotageCoherenceDataTable $table): Response
    {
        $selectedCampagne = $this->dataUserSession->getCampagneCollecte();
        $stats = $this->analyseRepository->getParcoursStatsForCampagne($selectedCampagne);

        return $this->render('admin/pilotage/coherence/index.html.twig', [
            'table' => $table,
            'selectedCampagne' => $selectedCampagne,
            'stats' => $stats,
            'aiConfigured' => $this->coherenceAnalyzer->isAiConfigured(),
        ]);
    }

    #[Route('/batch-deterministe', name: 'batch_deterministic', methods: ['POST'])]
    public function batchDeterministic(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('pilotage_coherence_batch_deterministic', (string)$request->request->get('_token', ''))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_admin_pilotage_coherence_index');
        }

        $campagne = $this->dataUserSession->getCampagneCollecte();
        if ($campagne === null) {
            $this->addFlash('warning', 'Aucune campagne de collecte sélectionnée.');

            return $this->redirectToRoute('app_admin_pilotage_coherence_index');
        }

        $parcoursList = $this->findCampagneParcours($campagne, limit: 80, onlyMissingOrStale: true);
        $count = 0;
        $alerts = 0;

        foreach ($parcoursList as $parcours) {
            $result = $this->coherenceAnalyzer->analyzeParcours($parcours, useAi: false, forceRefresh: false, persist: true);
            $count++;
            if ($result->status === CoherenceAnalyse::STATUS_ALERT) {
                $alerts++;
            }
        }

        if ($count === 0) {
            $this->addFlash('info', 'Tous les parcours de la campagne disposent déjà d\'une analyse à jour.');
        } else {
            $this->addFlash(
                'success',
                sprintf('Pré-filtre déterministe exécuté sur %d parcours (%d alerte(s) détectée(s)).', $count, $alerts)
            );
        }

        return $this->redirectToRoute('app_admin_pilotage_coherence_index');
    }

    #[Route('/batch-ia', name: 'batch_dispatch_ai', methods: ['POST'])]
    public function batchDispatchAi(Request $request, MessageBusInterface $messageBus): Response
    {
        if (!$this->isCsrfTokenValid('pilotage_coherence_batch_ai', (string)$request->request->get('_token', ''))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_admin_pilotage_coherence_index');
        }

        $campagne = $this->dataUserSession->getCampagneCollecte();
        if ($campagne === null) {
            $this->addFlash('warning', 'Aucune campagne de collecte sélectionnée.');

            return $this->redirectToRoute('app_admin_pilotage_coherence_index');
        }

        if (!$this->coherenceAnalyzer->isAiConfigured()) {
            $this->addFlash('warning', 'Aucune configuration IA active (établissement ou .env AI_API_KEY).');

            return $this->redirectToRoute('app_admin_pilotage_coherence_index');
        }

        $scope = (string)$request->request->get('scope', 'non_analyzed');
        $limit = max(1, min(200, (int)$request->request->get('limit', 50)));

        $parcoursList = $this->findParcoursForAiDispatch($campagne, $scope, $limit);
        $dispatched = 0;

        foreach ($parcoursList as $parcours) {
            $id = $parcours->getId();
            if ($id === null) {
                continue;
            }
            $messageBus->dispatch(new AnalyzeParcoursCoherence($id, useAi: true, forceRefresh: $scope === 'alerts'));
            $dispatched++;
        }

        if ($dispatched === 0) {
            $this->addFlash('info', 'Aucun parcours éligible trouvé pour cette file d\'analyse.');
        } else {
            $this->addFlash(
                'success',
                sprintf('%d tâche(s) d\'analyse IA ajoutée(s) à la file asynchrone.', $dispatched)
            );
        }

        return $this->redirectToRoute('app_admin_pilotage_coherence_index');
    }

    /**
     * @return list<Parcours>
     */
    private function findCampagneParcours(CampagneCollecte $campagne, int $limit, bool $onlyMissingOrStale): array
    {
        $qb = $this->parcoursRepository->createQueryBuilder('p')
            ->leftJoin('p.dpeParcours', 'dp')
            ->leftJoin('p.formation', 'f')
            ->leftJoin('p.coherenceAnalyse', 'ca')
            ->andWhere('dp.campagneCollecte = :campagne OR f.dpe = :campagne')
            ->setParameter('campagne', $campagne)
            ->distinct()
            ->orderBy('p.id', 'ASC')
            ->setMaxResults($limit);

        if ($onlyMissingOrStale) {
            $qb->andWhere('ca.id IS NULL');
        }

        /** @var list<Parcours> $rows */
        $rows = $qb->getQuery()->getResult();

        return $rows;
    }

    /**
     * @return list<Parcours>
     */
    private function findParcoursForAiDispatch(CampagneCollecte $campagne, string $scope, int $limit): array
    {
        $qb = $this->parcoursRepository->createQueryBuilder('p')
            ->leftJoin('p.dpeParcours', 'dp')
            ->leftJoin('p.formation', 'f')
            ->leftJoin('p.coherenceAnalyse', 'ca')
            ->andWhere('dp.campagneCollecte = :campagne OR f.dpe = :campagne')
            ->setParameter('campagne', $campagne)
            ->distinct()
            ->orderBy('p.id', 'ASC')
            ->setMaxResults($limit);

        if ($scope === 'alerts') {
            $qb->andWhere('ca.status IN (:statuses)')
                ->setParameter('statuses', [CoherenceAnalyse::STATUS_ALERT, CoherenceAnalyse::STATUS_WARNING]);
        } elseif ($scope === 'not_ai') {
            $qb->andWhere('ca.id IS NULL OR ca.isAiGenerated = false');
        } else {
            // non_analyzed
            $qb->andWhere('ca.id IS NULL');
        }

        /** @var list<Parcours> $rows */
        $rows = $qb->getQuery()->getResult();

        return $rows;
    }
}
