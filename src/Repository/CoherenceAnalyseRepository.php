<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CampagneCollecte;
use App\Entity\CoherenceAnalyse;
use App\Entity\FicheMatiere;
use App\Entity\Parcours;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CoherenceAnalyse>
 */
class CoherenceAnalyseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CoherenceAnalyse::class);
    }

    public function findForParcours(Parcours $parcours): ?CoherenceAnalyse
    {
        $id = $parcours->getId();
        if ($id === null) {
            return null;
        }

        return $this->findOneBy([
            'entityType' => CoherenceAnalyse::TYPE_PARCOURS,
            'entityId' => $id,
        ]);
    }

    public function findForFicheMatiere(FicheMatiere $ficheMatiere): ?CoherenceAnalyse
    {
        $id = $ficheMatiere->getId();
        if ($id === null) {
            return null;
        }

        return $this->findOneBy([
            'entityType' => CoherenceAnalyse::TYPE_FICHE_MATIERE,
            'entityId' => $id,
        ]);
    }

    /**
     * @return array{
     *     total: int,
     *     analyzed: int,
     *     ai_analyzed: int,
     *     not_analyzed: int,
     *     ok: int,
     *     warning: int,
     *     alert: int
     * }
     */
    public function getParcoursStatsForCampagne(?CampagneCollecte $campagne): array
    {
        if ($campagne === null) {
            return [
                'total' => 0,
                'analyzed' => 0,
                'ai_analyzed' => 0,
                'not_analyzed' => 0,
                'ok' => 0,
                'warning' => 0,
                'alert' => 0,
            ];
        }

        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select(
                'COUNT(DISTINCT p.id) AS total',
                'COUNT(DISTINCT ca.id) AS analyzed',
                'COUNT(DISTINCT CASE WHEN ca.id IS NOT NULL AND ca.isAiGenerated = true THEN p.id ELSE NULL END) AS ai_analyzed',
                "COUNT(DISTINCT CASE WHEN ca.status = 'ok' THEN p.id ELSE NULL END) AS ok_count",
                "COUNT(DISTINCT CASE WHEN ca.status = 'warning' THEN p.id ELSE NULL END) AS warning_count",
                "COUNT(DISTINCT CASE WHEN ca.status = 'alert' THEN p.id ELSE NULL END) AS alert_count"
            )
            ->from(Parcours::class, 'p')
            ->leftJoin('p.dpeParcours', 'dp')
            ->leftJoin('p.formation', 'f')
            ->leftJoin('p.coherenceAnalyse', 'ca')
            ->andWhere('dp.campagneCollecte = :campagne OR f.dpe = :campagne')
            ->setParameter('campagne', $campagne);

        $row = $qb->getQuery()->getOneOrNullResult() ?? [];

        $total = (int)($row['total'] ?? 0);
        $analyzed = (int)($row['analyzed'] ?? 0);

        return [
            'total' => $total,
            'analyzed' => $analyzed,
            'ai_analyzed' => (int)($row['ai_analyzed'] ?? 0),
            'not_analyzed' => max(0, $total - $analyzed),
            'ok' => (int)($row['ok_count'] ?? 0),
            'warning' => (int)($row['warning_count'] ?? 0),
            'alert' => (int)($row['alert_count'] ?? 0),
        ];
    }
}
