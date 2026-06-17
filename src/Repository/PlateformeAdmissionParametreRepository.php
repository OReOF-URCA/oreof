<?php

namespace App\Repository;

use App\Entity\Parcours;
use App\Entity\PlateformeAdmissionParametre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlateformeAdmissionParametre>
 */
class PlateformeAdmissionParametreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlateformeAdmissionParametre::class);
    }

    public function findByParcours(Parcours $parcours): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.parcours = :val')
            ->setParameter('val', $parcours)
            ->getQuery()
            ->getResult();
    }
}
