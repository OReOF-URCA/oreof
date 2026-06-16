<?php

namespace App\Repository;

use App\Entity\TypeRamificationParcours;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TypeRamificationParcours>
 */
class TypeRamificationParcoursRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TypeRamificationParcours::class);
    }
}
