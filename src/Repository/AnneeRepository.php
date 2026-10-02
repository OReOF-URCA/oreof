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
    public function findByCampagneIndexedByParcours(
        \App\Entity\CampagneCollecte $campagne,
        ?\App\Entity\Composante $composante = null
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->join('a.parcours', 'p')
            ->join('p.dpeParcours', 'dp')
            ->addSelect('p')
            ->where('dp.campagneCollecte = :campagne')
            ->setParameter('campagne', $campagne)
            ->orderBy('a.ordre', 'ASC');

        if ($composante !== null) {
            $qb->join('p.formation', 'f')
                ->andWhere('f.composantePorteuse = :composante')
                ->setParameter('composante', $composante);
        }

        $annees = $qb->getQuery()->getResult();

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
