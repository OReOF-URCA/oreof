<?php

namespace App\Repository;

use App\Entity\HistoriqueParcours;
use App\Entity\Parcours;
use App\Entity\CampagneCollecte;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HistoriqueParcours>
 *
 * @method HistoriqueParcours|null find($id, $lockMode = null, $lockVersion = null)
 * @method HistoriqueParcours|null findOneBy(array $criteria, array $orderBy = null)
 * @method HistoriqueParcours[]    findAll()
 * @method HistoriqueParcours[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HistoriqueParcoursRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HistoriqueParcours::class);
    }

    public function save(HistoriqueParcours $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(HistoriqueParcours $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByParcoursLastStep(?Parcours $parcours, string $step): ?HistoriqueParcours
    {
        $data = $this->createQueryBuilder('h')
            ->where('h.parcours = :parcours')
            ->andWhere('h.etape = :step')
            ->setParameter('parcours', $parcours)
            ->setParameter('step', $step)
            ->orderBy('h.date', 'DESC')
            ->getQuery()
            ->getResult();

        return count($data) > 0 ? $data[0] : null;
    }

    /**
     * @return list<HistoriqueParcours>
     */
    public function findForConseilDocuments(
        ?CampagneCollecte $campagneCollecte,
        ?int              $composanteId = null,
        ?int              $formationId = null,
        ?int              $parcoursId = null,
    ): array
    {
        $qb = $this->createQueryBuilder('h')
            ->addSelect('p', 'f', 'c')
            ->innerJoin('h.parcours', 'p')
            ->innerJoin('p.formation', 'f')
            ->leftJoin('f.composantePorteuse', 'c')
            ->orderBy('h.created', 'DESC');

        if ($campagneCollecte !== null) {
            $qb
                ->andWhere('f.dpe = :dpe')
                ->setParameter('dpe', $campagneCollecte);
        }

        if ($composanteId !== null) {
            $qb
                ->andWhere('c.id = :composanteId')
                ->setParameter('composanteId', $composanteId);
        }

        if ($formationId !== null) {
            $qb
                ->andWhere('f.id = :formationId')
                ->setParameter('formationId', $formationId);
        }

        if ($parcoursId !== null) {
            $qb
                ->andWhere('p.id = :parcoursId')
                ->setParameter('parcoursId', $parcoursId);
        }

        return $qb->getQuery()->getResult();
    }
}
