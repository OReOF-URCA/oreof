<?php

namespace App\Repository;

use App\Entity\CampagneCollecte;
use App\Entity\Composante;
use App\Entity\Etablissement;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\Profil;
use App\Entity\UserProfil;
use App\Enums\CentreGestionEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserProfil>
 */
class UserProfilRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserProfil::class);
    }

    public function findEnable(
        CampagneCollecte           $campagneCollecte,
        float|bool|int|string|null $sort,
        string|null                $direction
    ): array
    {
        return $this->createQueryBuilder('up')
            ->join('up.profil', 'p')
            ->join('up.user', 'u')
            ->where('u.isEnable = :isEnable')
            ->andWhere('u.isDeleted = false')
            ->andWhere('up.campagneCollecte = :campagne OR up.campagneCollecte IS NULL')
            ->setParameter('campagne', $campagneCollecte)
            ->setParameter('isEnable', true)
            ->addOrderBy('u.' . $sort, $direction)
            ->getQuery()
            ->getResult();
    }

    public function findEnableBySearch(
        CampagneCollecte           $campagneCollecte,
        string|null                $q,
        float|bool|int|string|null $sort,
        string|null                $direction
    )
    {
        return $this->createQueryBuilder('up')
            ->join('up.profil', 'p')
            ->join('up.user', 'u')
            ->where('u.isEnable = :isEnable')
            ->andWhere('u.nom LIKE :q OR u.prenom LIKE :q OR u.email LIKE :q OR u.username LIKE :q')
            ->andWhere('u.isDeleted = false')
            ->andWhere('up.campagneCollecte = :campagneCollecte OR up.campagneCollecte IS NULL')
            ->setParameter('campagneCollecte', $campagneCollecte)
            ->setParameter('isEnable', true)
            ->setParameter('q', '%' . $q . '%')
            ->addOrderBy('u.' . $sort, $direction)
            ->getQuery()
            ->getResult();
    }

    public function deleteAll(): void
    {
        $this->getEntityManager()->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $this->getEntityManager()->createQuery('DELETE FROM App\Entity\UserProfil')->execute();
        $this->getEntityManager()->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function findByComposanteEnableBySearch(CampagneCollecte $getCampagneCollecte, $composante, float|bool|int|string|null $q, float|bool|int|string|null $sort, float|bool|int|string|null $direction)
    {//todo: comment filtrer par composante
        return $this->createQueryBuilder('up')
            ->join('up.profil', 'p')
            ->join('up.user', 'u')
            ->where('u.isEnable = :isEnable')
            ->andWhere('u.isDeleted = false')
            ->andWhere('up.campagneCollecte = :campagne OR up.campagneCollecte IS NULL')
            ->setParameter('campagne', $getCampagneCollecte)
            ->setParameter('isEnable', true)
            // ->setParameter('composante', $composante)
            //   ->setParameter('q', '%' . $q . '%')
            ->addOrderBy('u.' . $sort, $direction)
            ->getQuery()
            ->getResult();
    }

    public function findByComposanteEnable(CampagneCollecte $campagneCollecte, $composante, float|bool|int|string|null $sort, float|bool|int|string|null $direction): array
    {

        //je veux une requete qui va récupérer les utilsateurs qui sont dans la bonne composante. Cela va dépendre du centre, si composante alors c'est une égalité, si c'est formation alors c'est la composante porteuse de la formation, si c'est le parcours alors c'est la composante porteuse de la formation du parcours, si c'est établissement pas concerné

        $qb = $this->createQueryBuilder('up')
            ->join('up.profil', 'p')
            ->join('up.user', 'u')
            ->leftJoin('up.formation', 'f')
            ->leftJoin('up.parcours', 'pa')
            ->leftJoin('pa.formation', 'pf')
            ->where('u.isEnable = :isEnable')
            ->andWhere('u.isDeleted = false')
            ->andWhere('up.campagneCollecte = :campagne OR up.campagneCollecte IS NULL')
            ->andWhere('
            up.composante = :composante
            OR f.composantePorteuse = :composante
            OR pf.composantePorteuse = :composante
        ')
            ->setParameter('campagne', $campagneCollecte)
            ->setParameter('isEnable', true)
            ->setParameter('composante', $composante)
            ->addOrderBy('u.' . $sort, $direction);

        return $qb->getQuery()->getResult();

    }

    public function findFormationWithSameRole(Formation $centre, Profil $profil, CampagneCollecte $campagneCollecte)
    {
        return $this->createQueryBuilder('p')
            ->where('p.formation = :formation')
            ->andWhere('p.profil = :profil')
            ->andWhere('p.campagneCollecte = :campagneCollecte')
            ->setParameter('formation', $centre)
            ->setParameter('profil', $profil)
            ->setParameter('campagneCollecte', $campagneCollecte)
            ->getQuery()
            ->getResult();

    }

    public function findParcoursWithSameRole(Parcours $centre, Profil $profil, CampagneCollecte $campagneCollecte): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.parcours = :parcours')
            ->andWhere('p.profil = :profil')
            ->andWhere('p.campagneCollecte = :campagneCollecte')
            ->setParameter('parcours', $centre)
            ->setParameter('profil', $profil)
            ->setParameter('campagneCollecte', $campagneCollecte)
            ->getQuery()
            ->getResult();
    }

    /**
     * Liste unifiée avec filtres et tri pour l'UI (admin ou DPE composante).
     * Params supportés:
     *  - q: recherche texte sur user (nom, prenom, email, username)
     *  - profil: id de Profil
     *  - typeCentre: valeur de l'enum CentreGestionEnum
     *  - sort: champ user (nom|prenom|username)
     *  - direction: asc|desc
     */
    public function findForListe(
        CampagneCollecte $campagneCollecte,
        ?Composante      $composante,
        array            $params
    ): array
    {
        $qb = $this->createQueryBuilder('up')
            ->join('up.profil', 'p')
            ->join('up.user', 'u')
            ->leftJoin('up.formation', 'f')
            ->leftJoin('up.parcours', 'pa')
            ->leftJoin('pa.formation', 'pf')
            ->where('u.isEnable = :isEnable')
            ->andWhere('u.isDeleted = false')
            ->andWhere('up.campagneCollecte = :campagne OR up.campagneCollecte IS NULL')
            ->setParameter('campagne', $campagneCollecte)
            ->setParameter('isEnable', true);

        // Périmètre composante (si non null => restreindre)
        if ($composante !== null) {
            $qb->andWhere('up.composante = :composante OR f.composantePorteuse = :composante OR pf.composantePorteuse = :composante')
                ->setParameter('composante', $composante);
        }

        // Recherche texte
        if (!empty($params['q'])) {
            $qb->andWhere('u.nom LIKE :q OR u.prenom LIKE :q OR u.email LIKE :q OR u.username LIKE :q')
                ->setParameter('q', '%' . $params['q'] . '%');
        }

        // Filtre par profil (id)
        if (!empty($params['profil'])) {
            $qb->andWhere('p.id = :profilId')
                ->setParameter('profilId', (int)$params['profil']);
        }

        // Filtre par type de centre (enum string stockée sur Profil.centre)
        if (!empty($params['typeCentre']) && CentreGestionEnum::has($params['typeCentre'])) {
            $qb->andWhere('p.centre = :typeCentre')
                ->setParameter('typeCentre', $params['typeCentre']);
        }

        // Tri
        $sort = $params['sort'] ?? 'nom';
        $direction = $params['direction'] ?? 'asc';
        $allowedSort = ['nom', 'prenom', 'username'];
        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'nom';
        }
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $qb->addOrderBy('u.' . $sort, $direction);

        return $qb->getQuery()->getResult();
    }
}
