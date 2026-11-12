<?php

namespace App\Repository;

use App\Entity\Tutoriel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tutoriel>
 */
class TutorielRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tutoriel::class);
    }

    /**
     * @return list<Tutoriel>
     */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['ordre' => 'ASC', 'id' => 'ASC']);
    }

    /**
     * @return list<Tutoriel>
     */
    public function findActiveOrdered(): array
    {
        return $this->findBy(['isActive' => true], ['ordre' => 'ASC', 'id' => 'ASC']);
    }

    public function getNextOrdre(): int
    {
        $max = $this->createQueryBuilder('t')
            ->select('MAX(t.ordre)')
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $max) + 1;
    }
}
