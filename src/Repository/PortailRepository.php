<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CampagneCollecte;
use App\Entity\Portail;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Portail>
 */
class PortailRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Portail::class);
    }

    /**
     * @return list<Portail>
     */
    public function findByCampagne(CampagneCollecte $campagne): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.annees', 'a')
            ->leftJoin('a.parcours', 'parc')
            ->leftJoin('parc.formation', 'f')
            ->addSelect('a', 'parc', 'f')
            ->where('p.campagne = :campagne')
            ->setParameter('campagne', $campagne)
            ->orderBy('p.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

