<?php

namespace App\Repository;

use App\Entity\DemandeAssistance;
use App\Entity\User;
use App\Enums\StatutDemandeAssistanceEnum;
use Doctrine\ORM\Tools\Pagination\Paginator;
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

    /**
     * Liste filtrée et paginée.
     *
     * @param string      $statut      « ouvertes », « toutes » ou une valeur de StatutDemandeAssistanceEnum
     * @param User|null   $responsable filtre sur le responsable (null = tous)
     * @param string|null $recherche   sujet, email ou nom du demandeur
     *
     * @return Paginator<DemandeAssistance>
     */
    public function rechercher(string $statut, ?User $responsable, ?string $recherche, int $page, int $parPage = 30): Paginator
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.responsable', 'r')->addSelect('r')
            ->orderBy('d.dateDernierMessage', 'DESC')
            ->setFirstResult(max(0, $page - 1) * $parPage)
            ->setMaxResults($parPage);

        if ($statut === 'ouvertes') {
            $qb->andWhere('d.statut != :resolue')->setParameter('resolue', StatutDemandeAssistanceEnum::RESOLUE);
        } elseif (($enum = StatutDemandeAssistanceEnum::tryFrom($statut)) !== null) {
            $qb->andWhere('d.statut = :statut')->setParameter('statut', $enum);
        }

        if ($responsable !== null) {
            $qb->andWhere('d.responsable = :responsable')->setParameter('responsable', $responsable);
        }

        if ($recherche !== null && trim($recherche) !== '') {
            $qb->andWhere('d.sujet LIKE :q OR d.demandeurEmail LIKE :q OR d.demandeurNom LIKE :q')
                ->setParameter('q', '%' . addcslashes(trim($recherche), '%_') . '%');
        }

        return new Paginator($qb->getQuery());
    }

    public function compterOuvertes(): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.statut != :resolue')
            ->setParameter('resolue', StatutDemandeAssistanceEnum::RESOLUE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Autres demandes candidates à une fusion : celles du même demandeur, puis les plus récentes.
     *
     * @return list<DemandeAssistance>
     */
    public function candidatesFusion(DemandeAssistance $demande, int $limite = 50): array
    {
        return $this->createQueryBuilder('d')
            ->where('d != :demande')
            ->setParameter('demande', $demande)
            ->addSelect('CASE WHEN d.demandeurEmail = :email THEN 0 ELSE 1 END AS HIDDEN priorite')
            ->setParameter('email', $demande->getDemandeurEmail())
            ->orderBy('priorite', 'ASC')
            ->addOrderBy('d.dateDernierMessage', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }
}
