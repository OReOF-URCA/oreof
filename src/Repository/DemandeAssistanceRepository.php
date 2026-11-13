<?php

namespace App\Repository;

use App\Entity\DemandeAssistance;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DemandeAssistance>
 */
class DemandeAssistanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DemandeAssistance::class);
    }

    /**
     * Demande la plus récente portant ce sujet normalisé et active depuis $depuis.
     */
    public function findRecenteParSujet(string $sujetNormalise, DateTimeImmutable $depuis): ?DemandeAssistance
    {
        if ($sujetNormalise === '') {
            return null;
        }

        return $this->createQueryBuilder('d')
            ->where('d.sujetNormalise = :sujet')
            ->andWhere('d.dateDernierMessage >= :depuis')
            ->setParameter('sujet', $sujetNormalise)
            ->setParameter('depuis', $depuis)
            ->orderBy('d.dateDernierMessage', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
