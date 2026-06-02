<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Repository/FormationRepository.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Repository;

use App\Entity\CampagneCollecte;
use App\Entity\Composante;
use App\Entity\Formation;
use App\Entity\Mention;
use App\Entity\Parcours;
use App\Entity\TypeDiplome;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<Formation>
 *
 * @method Formation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Formation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Formation[]    findAll()
 * @method Formation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FormationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Formation::class);
    }

    public function save(Formation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Formation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByComposanteDpe(
        UserInterface    $user,
        CampagneCollecte $campagneCollecte,
        array            $sorts = [],
        string|null $q = null
    ): array {
        $query = $this->createQueryBuilder('f')
            ->join('f.dpeParcours', 'dp')
            ->innerJoin(Composante::class, 'c', 'WITH', 'f.composantePorteuse = c.id')
            ->where('c.responsableDpe = :user')
            ->andWhere('dp.campagneCollecte = :campagneCollecte')
            ->setParameter('user', $user)

            ->setParameter('campagneCollecte', $campagneCollecte);

        if ($q !== null) {
            $query ->andWhere('m.libelle LIKE :q or f.sigle LIKE :q or m.sigle LIKE :q or f.mentionTexte LIKE :q or parcours.libelle LIKE :q or parcours.sigle LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        foreach ($sorts as $sort => $direction) {
            if ($sort === 'mention') {
                $query->leftJoin(Mention::class, 'm', 'WITH', 'f.mention = m.id');
                $query->addOrderBy(
                    'CASE
                            WHEN f.mention IS NOT NULL THEN m.libelle
                            WHEN f.mentionTexte IS NOT NULL THEN f.mentionTexte
                            ELSE f.mentionTexte
                            END',
                    $direction
                );
            } else {
                $query->addOrderBy('f.' . $sort, $direction);
            }
        }

        return $query->getQuery()
            ->getResult();
    }

    public function findBySearch(
        string|null      $q,
        CampagneCollecte $campagneCollecte,
        array            $options = [],
        Composante|null  $composante = null,
    ): array {
        $sort = $options['sort'] ?? 'typeDiplome';
        $direction = $options['direction'] ?? 'ASC';

        $filtres = [$sort => $direction];
//todo: ne fonctionne que si au moins un parcours existe, donc pas pour les nouveaux parcours
        $query = $this->createQueryBuilder('f')
            ->leftJoin('f.dpeParcours', 'p')
            ->leftJoin('p.parcours', 'parcours')
            ->addSelect('p')
            ->andWhere('p.campagneCollecte = :campagneCollecte')
            ->setParameter('campagneCollecte', $campagneCollecte)
            ->innerJoin(Mention::class, 'm', 'WITH', 'f.mention = m.id')
            ->andWhere('m.libelle LIKE :q or f.sigle LIKE :q or m.sigle LIKE :q or f.mentionTexte LIKE :q or parcours.libelle LIKE :q or parcours.sigle LIKE :q')

            ->setParameter('q', '%' . $q . '%')
            ->orderBy('f.' . $sort, $direction);

        if (array_key_exists('typeDiplome', $options) && null !== $options['typeDiplome']) {
            $query->andWhere('f.typeDiplome = :typeDiplome')
                ->setParameter('typeDiplome', $options['typeDiplome']);
        }

        if (array_key_exists('mention', $options) && null !== $options['mention']) {
            $query->andWhere('f.mention = :mention')
                ->setParameter('mention', $options['mention']);
        }

        if (array_key_exists('composantePorteuse', $options) && null !== $options['composantePorteuse']) {
            $query->andWhere('f.composantePorteuse = :composante')
                ->setParameter('composante', $options['composantePorteuse']);
        }

//        if (array_key_exists('etatDpe', $options) && null !== $options['etatDpe']) {
//            $query->andWhere("JSON_CONTAINS(p.etatValidation, :etatDpe) = 1")
//                ->setParameter('etatDpe', json_encode([$options['etatDpe'] => 1]));
//        }

        if (array_key_exists('mention', $filtres) && null !== $filtres['mention']) {
            $query->addOrderBy(
                'CASE
                            WHEN f.mention IS NOT NULL THEN m.libelle
                            WHEN f.mentionTexte IS NOT NULL THEN f.mentionTexte
                            ELSE f.mentionTexte
                            END',
                $filtres['mention']
            );
        }

        if (array_key_exists('domaine', $filtres) && null !== $filtres['domaine']) {
            $query->addOrderBy(
                'm.domaine',
                $filtres['domaine']
            );
        }

        // si responsable rechercher sur formation ou parcours si responsable ou co-responsable
        if (array_key_exists('responsable', $options) && null !== $options['responsable']) {
            $query->andWhere('f.responsableMention = :responsable OR f.coResponsable = :responsable OR parcours.respParcours = :responsable OR parcours.coResponsable = :responsable')
                ->setParameter('responsable', $options['responsable']);
        }

        if ($composante !== null) {
            $query->andWhere('f.composantePorteuse = :composante')
                ->setParameter('composante', $composante);
        }

        return $query->getQuery()
            ->getResult();
    }

    public function findBySearchAndCfvu(
        string|null      $q,
        CampagneCollecte $campagneCollecte,
        array            $options = [],
        Composante|null  $composante = null,
    ): array {
        $sort = $options['sort'] ?? 'typeDiplome';
        $direction = $options['direction'] ?? 'ASC';

        $query = $this->createQueryBuilder('f')
            ->innerJoin(Mention::class, 'm', 'WITH', 'f.mention = m.id')
            ->leftJoin('f.dpeParcours', 'p')
            ->addSelect('p')
            ->where('p.campagneCollecte = :campagneCollecte')
            ->setParameter('campagneCollecte', $campagneCollecte)
            ->andWhere('m.libelle LIKE :q or m.sigle LIKE :q or f.mentionTexte LIKE :q ')
            ->andWhere("JSON_CONTAINS(p.etatValidation, :etatDpe) = 1")
            ->setParameter('etatDpe', json_encode(['soumis_cfvu' => 1]))
            ->setParameter('q', '%' . $q . '%')
            ->orderBy('f.' . $sort, $direction);

        if (array_key_exists('typeDiplome', $options) && null !== $options['typeDiplome']) {
            $query->andWhere('f.typeDiplome = :typeDiplome')
                ->setParameter('typeDiplome', $options['typeDiplome']);
        }

        if (array_key_exists('mention', $options) && null !== $options['mention']) {
            $query->andWhere('f.mention = :mention')
                ->setParameter('mention', $options['mention']);
        }

        if (array_key_exists('composantePorteuse', $options) && null !== $options['composantePorteuse']) {
            $query->andWhere('f.composantePorteuse = :composante')
                ->setParameter('composante', $options['composantePorteuse']);
        }

        if ($sort === 'mention') {
            $query->leftJoin(Mention::class, 'm', 'WITH', 'f.mention = m.id');
            $query->addOrderBy(
                'CASE
                            WHEN f.mention IS NOT NULL THEN m.libelle
                            WHEN f.mentionTexte IS NOT NULL THEN f.mentionTexte
                            ELSE f.mentionTexte
                            END',
                $direction
            );
        }

        if ($composante !== null) {
            $query->andWhere('f.composantePorteuse = :composante')
                ->setParameter('composante', $composante);
        }

        return $query->getQuery()
            ->getResult();
    }

    public function findByResponsableOuCoResponsable(User $user, CampagneCollecte $campagneCollecte, array $sorts = []): array
    {
        $query = $this->createQueryBuilder('f')
            ->leftJoin('f.dpeParcours', 'dp')
            ->addSelect('dp')
            ->where('f.responsableMention = :user')
            ->orWhere('f.coResponsable = :user')
            ->andWhere('dp.campagneCollecte = :campagneCollecte')
            ->setParameter('user', $user)
            ->setParameter('campagneCollecte', $campagneCollecte);

        foreach ($sorts as $sort => $direction) {
            if ($sort === 'mention') {
                $query->leftJoin(Mention::class, 'm', 'WITH', 'f.mention = m.id');
                $query->addOrderBy(
                    'CASE
                            WHEN f.mention IS NOT NULL THEN m.libelle
                            WHEN f.mentionTexte IS NOT NULL THEN f.mentionTexte
                            ELSE f.mentionTexte
                            END',
                    $direction
                );
            } else {
                $query->addOrderBy('f.' . $sort, $direction);
            }
        }

        return $query->getQuery()
            ->getResult();
    }

    public function findByComposante(Composante $composante, CampagneCollecte $campagneCollecte, array $sorts = []): array
    {
        $query = $this->createQueryBuilder('f')
            ->innerJoin(Composante::class, 'c', 'WITH', 'f.composantePorteuse = c.id')
            ->leftJoin('f.dpeParcours', 'dp')
            ->addSelect('dp')
            ->andWhere('c.id = :composante')
            ->andWhere('dp.campagneCollecte = :campagneCollecte')
            ->setParameter('campagneCollecte', $campagneCollecte)
            ->setParameter('composante', $composante);

        foreach ($sorts as $sort => $direction) {
            if ($sort === 'mention') {
                $query->leftJoin(Mention::class, 'm', 'WITH', 'f.mention = m.id');
                $query->addOrderBy(
                    'CASE
                            WHEN f.mention IS NOT NULL THEN m.libelle
                            WHEN f.mentionTexte IS NOT NULL THEN f.mentionTexte
                            ELSE f.mentionTexte
                            END',
                    $direction
                );
            } else {
                $query->addOrderBy('f.' . $sort, $direction);
            }
        }

        return $query->getQuery()
            ->getResult();
    }



    /** @deprecated  */
    public function findByComposanteTypeValidation(Composante $composante, CampagneCollecte $campagneCollecte, string $typeValidation): array
    {
        $query = $this->createQueryBuilder('f')
            ->innerJoin(Composante::class, 'c', 'WITH', 'f.composantePorteuse = c.id')
            ->leftJoin('f.dpeParcours', 'dp')
            ->addSelect('dp')
            ->andWhere('c.id = :composante')
            ->andWhere("JSON_CONTAINS(dp.etatValidation, :etatDpe) = 1")
            ->setParameter('etatDpe', json_encode([$typeValidation => 1]))
            ->andWhere('dp.campagneCollecte = :campagneCollecte')
            ->setParameter('campagneCollecte', $campagneCollecte)
            ->setParameter('composante', $composante);


        return $query->getQuery()
            ->getResult();
    }

    public function findByResponsableOuCoResponsableParcours(?UserInterface $user, CampagneCollecte $campagneCollecte, array $sorts)
    {
        $query = $this->createQueryBuilder('f')
            ->innerJoin(Parcours::class, 'p', 'WITH', 'f.id = p.formation')
            ->leftJoin('f.dpeParcours', 'dp')
            ->addSelect('dp')
            ->where('p.respParcours = :user')
            ->orWhere('p.coResponsable = :user')
            ->andWhere('dp.campagneCollecte = :campagneCollecte')
            ->setParameter('user', $user)
            ->setParameter('campagneCollecte', $campagneCollecte);

        foreach ($sorts as $sort => $direction) {
            if ($sort === 'mention') {
                $query->leftJoin(Mention::class, 'm', 'WITH', 'f.mention = m.id');
                $query->addOrderBy(
                    'CASE
                            WHEN f.mention IS NOT NULL THEN m.libelle
                            WHEN f.mentionTexte IS NOT NULL THEN f.mentionTexte
                            ELSE f.mentionTexte
                            END',
                    $direction
                );
            } else {
                $query->addOrderBy('f.' . $sort, $direction);
            }
        }

        return $query->getQuery()
            ->getResult();
    }

    public function findByComposantePorteuse(mixed $composante, CampagneCollecte $campagneCollecte): array
    {
        return $this->createQueryBuilder('f')
            ->innerJoin('f.dpeParcours', 'dp')
            ->where('f.composantePorteuse = :composante')
            ->andWhere('dp.campagneCollecte = :campagneCollecte')
            ->setParameter('composante', $composante)
            ->setParameter('campagneCollecte', $campagneCollecte)

            ->leftJoin(Mention::class, 'm', 'WITH', 'f.mention = m.id')
            ->addOrderBy('f.typeDiplome', 'ASC')
            ->addOrderBy(
                'CASE
                            WHEN f.mention IS NOT NULL THEN m.libelle
                            WHEN f.mentionTexte IS NOT NULL THEN f.mentionTexte
                            ELSE f.mentionTexte
                            END',
                'ASC'
            )->getQuery()
            ->getResult();
    }

    public function findByComposanteAndDpe(string|null $composante, ?CampagneCollecte $campagneCollecte): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.composantePorteuse = :composante')
            ->leftJoin('f.dpeParcours', 'dp')
            ->addSelect('dp')
            ->andWhere('dp.campagneCollecte = :campagneCollecte')
            ->setParameter('composante', $composante)
            ->setParameter('campagneCollecte', $campagneCollecte)
            ->leftJoin(Mention::class, 'm', 'WITH', 'f.mention = m.id')
            ->addOrderBy(
                'CASE
                            WHEN f.mention IS NOT NULL THEN m.libelle
                            WHEN f.mentionTexte IS NOT NULL THEN f.mentionTexte
                            ELSE f.mentionTexte
                            END',
                'ASC'
            )->getQuery()
            ->getResult();
    }

    public function findByComposanteAndAnneeUniversitaireAndTypeDiplome(string|null $composante, string|null $campagneCollecte, string|null $typeDiplome): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.composantePorteuse = :composante')
            ->leftJoin('f.dpeParcours', 'dp')
            ->addSelect('dp')
            ->andWhere('dp.campagneCollecte = :campagneCollecte')
            ->andWhere('f.typeDiplome = :typeDiplome')

            ->setParameter('composante', $composante)
            ->setParameter('campagneCollecte', $campagneCollecte)
            ->setParameter('typeDiplome', $typeDiplome)
            ->leftJoin(Mention::class, 'm', 'WITH', 'f.mention = m.id')
            ->addOrderBy(
                'CASE
                            WHEN f.mention IS NOT NULL THEN m.libelle
                            WHEN f.mentionTexte IS NOT NULL THEN f.mentionTexte
                            ELSE f.mentionTexte
                            END',
                'ASC'
            )->getQuery()
            ->getResult();
    }

    public function findByDpeAndTypeDiplome(CampagneCollecte $campagneCollecte, TypeDiplome $typeDiplome): array
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.dpeParcours', 'dp')
            ->addSelect('dp')
            ->andWhere('dp.campagneCollecte = :campagneCollecte')
            ->andWhere('f.typeDiplome = :typeDiplome')
            ->setParameter('campagneCollecte', $campagneCollecte)
            ->setParameter('typeDiplome', $typeDiplome)
            ->leftJoin(Mention::class, 'm', 'WITH', 'f.mention = m.id')
            ->addOrderBy(
                'CASE
                            WHEN f.mention IS NOT NULL THEN m.libelle
                            WHEN f.mentionTexte IS NOT NULL THEN f.mentionTexte
                            ELSE f.mentionTexte
                            END',
                'ASC'
            )->getQuery()
            ->getResult();
    }

    public function findByComposanteCfvu(Composante $composante, CampagneCollecte $campagneCollecte): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.composantePorteuse = :composante')
            ->leftJoin('f.dpeParcours', 'dp')
            ->addSelect('dp')
            ->andWhere('dp.campagneCollecte = :campagneCollecte')
            ->andWhere("JSON_CONTAINS(dp.etatValidation, :etatDpe) = 1")
            ->setParameter('etatDpe', json_encode(['soumis_cfvu' => 1]))
            ->setParameter('composante', $composante)
            ->setParameter('campagneCollecte', $campagneCollecte)
            ->leftJoin(Mention::class, 'm', 'WITH', 'f.mention = m.id')
            ->addOrderBy(
                'CASE
                            WHEN f.mention IS NOT NULL THEN m.libelle
                            WHEN f.mentionTexte IS NOT NULL THEN f.mentionTexte
                            ELSE f.mentionTexte
                            END',
                'ASC'
            )->getQuery()
            ->getResult();
    }

    public function findByTypeValidation(CampagneCollecte $campagneCollecte, mixed $typeValidation): array
    {
        $query = $this->createQueryBuilder('f')
            ->innerJoin(Composante::class, 'c', 'WITH', 'f.composantePorteuse = c.id')
            ->leftJoin('f.dpeParcours', 'dp')
            ->addSelect('dp')
            ->andWhere("JSON_CONTAINS(dp.etatValidation, :etatDpe) = 1")
            ->setParameter('etatDpe', json_encode([$typeValidation => 1]))
            ->andWhere('dp.campagneCollecte = :campagneCollecte')
            ->setParameter('campagneCollecte', $campagneCollecte);


        return $query->getQuery()
            ->getResult();
    }

    public function findByCampagneCollecte(CampagneCollecte $campagneCollecte): array
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.dpeParcours', 'dp')
            ->leftJoin('f.typeDiplome', 't')
            ->addSelect('dp')
            ->andWhere('dp.campagneCollecte = :campagneCollecte')
            ->setParameter('campagneCollecte', $campagneCollecte)
            ->leftJoin(Mention::class, 'm', 'WITH', 'f.mention = m.id')
            ->addOrderBy('t.libelle_court', 'ASC')
            ->addOrderBy(
                'CASE
                            WHEN f.mention IS NOT NULL THEN m.libelle
                            WHEN f.mentionTexte IS NOT NULL THEN f.mentionTexte
                            ELSE f.mentionTexte
                            END',
                'ASC'
            )->getQuery()
            ->getResult();
    }

    public function findFromAnneeUniversitaire(int $idCampagneCollecte) : array {
        $qb = $this->createQueryBuilder('formation');
        return $qb
            ->select('DISTINCT formation.id')
            ->join('formation.dpeParcours', 'dpeP')
            ->join('dpeP.campagneCollecte', 'campagneC')
            ->andWhere('campagneC.id = :idCampagne')
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->isNull('formation.isSoftDeleted'),
                    $qb->expr()->eq('formation.isSoftDeleted', 0)
                )
            )
            ->setParameter(':idCampagne', $idCampagneCollecte)
            ->getQuery()
            ->getResult();
    }
}
