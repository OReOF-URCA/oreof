<?php

namespace App\Repository;

use App\Entity\CampagneCollecte;
use App\Entity\ChangeRf;
use App\Entity\Formation;
use App\Entity\HistoriqueFormation;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HistoriqueFormation>
 *
 * @method HistoriqueFormation|null find($id, $lockMode = null, $lockVersion = null)
 * @method HistoriqueFormation|null findOneBy(array $criteria, array $orderBy = null)
 * @method HistoriqueFormation[]    findAll()
 * @method HistoriqueFormation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HistoriqueFormationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HistoriqueFormation::class);
    }

    public function save(HistoriqueFormation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(HistoriqueFormation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByFormationLastStep(Formation $formation, string $step): ?HistoriqueFormation
    {
        $data = $this->createQueryBuilder('h')
            ->where('h.formation = :formation')
            ->andWhere('h.etape = :step')
            ->setParameter('formation', $formation)
            ->setParameter('step', $step)
            ->orderBy('h.date', 'DESC')
            ->getQuery()
            ->getResult();

        return count($data) > 0 ? $data[0] : null;
    }

    public function findBeforDate(DateTime $param): array
    {
        return $this->createQueryBuilder('h')
            ->innerJoin('h.formation', 'f')
            ->addSelect('f')
            ->where('h.created <= :date')
            ->setParameter('date', $param)
            ->getQuery()
            ->getResult();
    }

    public function findByChangeRfLastStep(?ChangeRf $changeRf, string $step): ?HistoriqueFormation
    {
        $data = $this->createQueryBuilder('h')
            ->where('h.changeRf = :changeRf')
            ->andWhere('h.etape = :step')
            ->setParameter('changeRf', $changeRf)
            ->setParameter('step', $step)
            ->orderBy('h.date', 'DESC')
            ->getQuery()
            ->getResult();

        return count($data) > 0 ? $data[0] : null;
    }

    /**
     * @return list<HistoriqueFormation>
     */
    public function findForConseilDocuments(
        ?CampagneCollecte $campagneCollecte,
        ?int              $composanteId = null,
        ?int              $formationId = null,
        ?string           $processType = null,
    ): array
    {
        $qb = $this->createQueryBuilder('h')
            ->addSelect('f', 'c', 'cr', 'df', 'pv', 'note')
            ->leftJoin('h.formation', 'f')
            ->leftJoin('h.changeRf', 'cr')
            ->leftJoin('h.dpeFormation', 'df')
            ->leftJoin('h.documentPv', 'pv')
            ->leftJoin('h.documentNote', 'note')
            ->leftJoin('f.composantePorteuse', 'c')
            ->orderBy('h.created', 'DESC');

        if ($processType === 'change_rf') {
            $qb->andWhere('h.changeRf IS NOT NULL OR h.etape LIKE :changeRfPrefix')
                ->setParameter('changeRfPrefix', 'changeRf.%');
        } elseif ($processType === 'dpe_formation') {
            $qb->andWhere('(h.dpeFormation IS NOT NULL OR h.changeRf IS NULL) AND (h.etape NOT LIKE :changeRfPrefix OR h.etape IS NULL)')
                ->setParameter('changeRfPrefix', 'changeRf.%');
        }

        if ($campagneCollecte !== null) {
            $qb->andWhere('f.dpe = :dpe OR df.campagneCollecte = :dpe OR cr.campagneCollecte = :dpe')
                ->setParameter('dpe', $campagneCollecte);
        }

        if ($composanteId !== null) {
            $qb->andWhere('c.id = :composanteId')
                ->setParameter('composanteId', $composanteId);
        }

        if ($formationId !== null) {
            $qb->andWhere('f.id = :formationId OR cr.formation = :formationId OR df.formation = :formationId')
                ->setParameter('formationId', $formationId);
        }

        return $qb->getQuery()->getResult();
    }
}
