<?php

namespace App\Repository;

use App\Entity\Annee;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Annee>
 */
class AnneeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Annee::class);
    }

    /**
     * @return array<int, list<Annee>>
     */
    public function findByCampagneIndexedByParcours(\App\Entity\CampagneCollecte $campagne): array
    {
        $annees = $this->createQueryBuilder('a')
            ->join('a.parcours', 'p')
            ->join('p.dpeParcours', 'dp')
            ->where('dp.campagneCollecte = :campagne')
            ->setParameter('campagne', $campagne)
            ->orderBy('a.ordre', 'ASC')
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($annees as $annee) {
            $parcoursId = $annee->getParcours()?->getId();
            if ($parcoursId !== null) {
                $map[$parcoursId][] = $annee;
            }
        }

        return $map;
    }
}
