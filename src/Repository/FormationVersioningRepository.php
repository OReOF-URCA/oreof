<?php

namespace App\Repository;

use App\Entity\Formation;
use App\Entity\FormationVersioning;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FormationVersioning>
 *
 * @method FormationVersioning|null find($id, $lockMode = null, $lockVersion = null)
 * @method FormationVersioning|null findOneBy(array $criteria, array $orderBy = null)
 * @method FormationVersioning[]    findAll()
 * @method FormationVersioning[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FormationVersioningRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FormationVersioning::class);
    }

    /**
     * @return list<FormationVersioning>
     */
    public function findLastVersion(Formation $formation): array
    {
        return $this->createQueryBuilder('pv')
            ->orderBy('pv.version_timestamp', 'DESC')
            ->where('pv.formation = :formation')
            ->setParameter('formation', $formation)
            ->getQuery()
            ->getResult();
    }
}
