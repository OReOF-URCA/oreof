<?php

namespace App\Repository;

use App\Entity\DemandeAssistance;
use App\Entity\User;
use App\Enums\StatutDemandeAssistanceEnum;
use Doctrine\ORM\QueryBuilder;
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
    public function findRecenteParSujet(string $sujetNormalise, DateTimeImmutable $depuis, ?string $demandeurEmail = null): ?DemandeAssistance
    {
        if ($sujetNormalise === '') {
            return null;
        }

        $qb = $this->createQueryBuilder('d')
            ->where('d.sujetNormalise = :sujet')
            ->andWhere('d.dateDernierMessage >= :depuis')
            ->andWhere('d.annonce = false')
            ->setParameter('sujet', $sujetNormalise)
            ->setParameter('depuis', $depuis)
            ->orderBy('d.dateDernierMessage', 'DESC')
            ->setMaxResults(1);

        if ($demandeurEmail !== null) {
            $qb->andWhere('d.demandeurEmail = :demandeur')->setParameter('demandeur', mb_strtolower($demandeurEmail));
        }

        return $qb->getQuery()->getOneOrNullResult();
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
    public const VUES = ['demandes', 'notifications', 'annonces'];

    /**
     * @param string $vue « demandes » (échanges à traiter), « notifications » (mails système) ou « annonces » (mails de masse)
     * @param string $statut « ouvertes », « toutes » ou une valeur de StatutDemandeAssistanceEnum (vue « demandes » seulement)
     */
    public function rechercher(string $vue, string $statut, ?User $responsable, bool $sansResponsable, ?string $recherche, int $page, int $parPage = 30): Paginator
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.responsable', 'r')->addSelect('r')
            // messages chargés avec la liste (fils dépliables) : pas de requête par demande
            ->leftJoin('d.messages', 'm')->addSelect('m')
            ->orderBy('d.dateDernierMessage', 'DESC')
            ->setFirstResult(max(0, $page - 1) * $parPage)
            ->setMaxResults($parPage);

        match ($vue) {
            'notifications' => $qb->andWhere('d.notification = true'),
            'annonces' => $qb->andWhere('d.annonce = true'),
            default => $this->filtrerDemandes($qb, $statut),
        };

        if ($responsable !== null) {
            $qb->andWhere('d.responsable = :responsable')->setParameter('responsable', $responsable);
        } elseif ($sansResponsable) {
            $qb->andWhere('d.responsable IS NULL');
        }

        if ($recherche !== null && trim($recherche) !== '') {
            $qb->andWhere('d.sujet LIKE :q OR d.demandeurEmail LIKE :q OR d.demandeurNom LIKE :q')
                ->setParameter('q', '%' . addcslashes(trim($recherche), '%_') . '%');
        }

        return new Paginator($qb->getQuery());
    }

    private function filtrerDemandes(QueryBuilder $qb, string $statut): void
    {
        $qb->andWhere('d.annonce = false')->andWhere('d.notification = false');

        if ($statut === 'ouvertes') {
            $qb->andWhere('d.statut != :resolue')->setParameter('resolue', StatutDemandeAssistanceEnum::RESOLUE);
        } elseif (($enum = StatutDemandeAssistanceEnum::tryFrom($statut)) !== null) {
            $qb->andWhere('d.statut = :statut')->setParameter('statut', $enum);
        }
    }

    public function compterOuvertes(): int
    {
        return $this->indicateurs(null)['ouvertes'];
    }

    /**
     * Compteurs des demandes à traiter (annonces et notifications exclues).
     *
     * @return array{ouvertes: int, a_moi: int, nouvelles: int, en_cours: int, en_attente_usager: int, sans_responsable: int}
     */
    public function indicateurs(?User $moi): array
    {
        $ligne = $this->createQueryBuilder('d')
            ->select(
                'SUM(CASE WHEN d.statut != :resolue THEN 1 ELSE 0 END) AS ouvertes',
                'SUM(CASE WHEN d.statut != :resolue AND d.responsable = :moi THEN 1 ELSE 0 END) AS a_moi',
                'SUM(CASE WHEN d.statut = :nouvelle THEN 1 ELSE 0 END) AS nouvelles',
                'SUM(CASE WHEN d.statut = :en_cours THEN 1 ELSE 0 END) AS en_cours',
                'SUM(CASE WHEN d.statut = :en_attente THEN 1 ELSE 0 END) AS en_attente_usager',
                'SUM(CASE WHEN d.statut != :resolue AND d.responsable IS NULL THEN 1 ELSE 0 END) AS sans_responsable',
            )
            ->where('d.annonce = false')
            ->andWhere('d.notification = false')
            ->setParameter('resolue', StatutDemandeAssistanceEnum::RESOLUE)
            ->setParameter('nouvelle', StatutDemandeAssistanceEnum::NOUVELLE)
            ->setParameter('en_cours', StatutDemandeAssistanceEnum::EN_COURS)
            ->setParameter('en_attente', StatutDemandeAssistanceEnum::EN_ATTENTE_USAGER)
            ->setParameter('moi', $moi?->getId() ?? 0)
            ->getQuery()
            ->getSingleResult();

        return array_map(static fn (mixed $valeur): int => (int) $valeur, $ligne);
    }

    /**
     * Nombre d'éléments de chaque onglet : demandes ouvertes, notifications et annonces.
     *
     * @return array{demandes: int, notifications: int, annonces: int}
     */
    public function compterParVue(): array
    {
        $ligne = $this->createQueryBuilder('d')
            ->select(
                'SUM(CASE WHEN d.annonce = false AND d.notification = false AND d.statut != :resolue THEN 1 ELSE 0 END) AS demandes',
                'SUM(CASE WHEN d.notification = true THEN 1 ELSE 0 END) AS notifications',
                'SUM(CASE WHEN d.annonce = true THEN 1 ELSE 0 END) AS annonces',
            )
            ->setParameter('resolue', StatutDemandeAssistanceEnum::RESOLUE)
            ->getQuery()
            ->getSingleResult();

        return array_map(static fn (mixed $valeur): int => (int) $valeur, $ligne);
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
