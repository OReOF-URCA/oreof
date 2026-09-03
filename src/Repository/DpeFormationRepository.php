<?php

namespace App\Repository;

use App\Entity\DpeFormation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DpeFormation>
 *
 * @method DpeFormation|null find($id, $lockMode = null, $lockVersion = null)
 * @method DpeFormation|null findOneBy(array $criteria, array $orderBy = null)
 * @method DpeFormation[]    findAll()
 * @method DpeFormation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DpeFormationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DpeFormation::class);
    }
}
