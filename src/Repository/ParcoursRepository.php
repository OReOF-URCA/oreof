<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Repository/ParcoursRepository.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 02/02/2023 18:32
 */

namespace App\Repository;

use App\Entity\CampagneCollecte;
use App\Entity\Composante;
use App\Entity\Formation;
use App\Entity\Mention;
use App\Entity\Parcours;
use App\Entity\User;
use App\Enums\TypeModificationDpeEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Parcours>
 *
 * @method Parcours|null find($id, $lockMode = null, $lockVersion = null)
 * @method Parcours|null findOneBy(array $criteria, array $orderBy = null)
 * @method Parcours[]    findAll()
 * @method Parcours[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ParcoursRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Parcours::class);
    }

    public function save(Parcours $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Parcours $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByFormation(Formation $formation): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.formation = :formation')
            ->setParameter('formation', $formation)
            ->orderBy('p.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByTypeValidation(CampagneCollecte $campagneCollecte, mixed $typeValidation): array
    {
        $query = $this->createQueryBuilder('p')
            ->join('p.dpeParcours', 'dp')
            ->innerJoin('p.formation', 'f')
            ->andWhere("JSON_CONTAINS(dp.etatValidation, :etatDpe) = 1")
            ->setParameter('etatDpe', json_encode([$typeValidation => 1]))
            ->andWhere('dp.campagneCollecte = :campagneCollecte')
            ->setParameter('campagneCollecte', $campagneCollecte);

        return $query->getQuery()
            ->getResult();
    }

    public function findByTypeValidationAttenteCfvu(CampagneCollecte $campagneCollecte, mixed $typeValidation): array
    {
        $query = $this->createQueryBuilder('p')
            ->join('p.dpeParcours', 'dp')
            ->innerJoin('p.formation', 'f')
            ->andWhere("JSON_CONTAINS(dp.etatValidation, :etatDpe) = 1")
            ->andWhere('dp.etatReconduction = :etatReconduction')
            ->orWhere('dp.etatReconduction = :etatReconduction2')
            ->setParameter('etatDpe', json_encode([$typeValidation => 1]))
            ->setParameter('etatReconduction', TypeModificationDpeEnum::MODIFICATION_MCCC)
            ->setParameter('etatReconduction2', TypeModificationDpeEnum::MODIFICATION_MCCC_TEXTE)
            ->andWhere('dp.campagneCollecte = :campagneCollecte')
            ->setParameter('campagneCollecte', $campagneCollecte);

        return $query->getQuery()
            ->getResult();
    }



    public function findParcours(CampagneCollecte $campagneCollecte, array $options): array
    {
        $qb = $this->createQueryBuilder('p')
            ->join('p.dpeParcours', 'dp')
            ->where('p.libelle <> :libelle')
            ->setParameter('libelle', Parcours::PARCOURS_DEFAUT)
            ->innerJoin('p.formation', 'f')
            ->andWhere('dp.campagneCollecte = :campagneCollecte')
            ->setParameter('campagneCollecte', $campagneCollecte);

        foreach ($options as $sort => $direction) {
            if ($sort === 'recherche' && $direction !== '') {
                $qb->andWhere('p.libelle LIKE :recherche OR p.sigle LIKE :recherche')
                    ->setParameter('recherche', '%' . $direction . '%');
            } elseif ($sort === 'composante') {
                $qb->innerJoin('f.composantePorteuse', 'c')
                    ->addOrderBy('c.libelle', $direction);
            } elseif ($sort === 'mention') {
                $qb->leftJoin(Mention::class, 'm', 'WITH', 'f.mention = m.id');
                $qb->addOrderBy(
                    'CASE
                            WHEN f.mention IS NOT NULL THEN m.libelle
                            WHEN f.mentionTexte IS NOT NULL THEN f.mentionTexte
                            ELSE f.mentionTexte
                            END',
                    $direction
                );
            } else {
                // $validSortFields = ['libelle', 'sigle', 'typeParcours']; // Règle un crash quand la recherche de parcours est vide. La requête bug quand même car elle est beaucoup trop grosse.
                // if (in_array($sort, $validSortFields))
                // {
                    $qb->addOrderBy('p.' . $sort, $direction);
                // }
            }
        }

        return $qb->getQuery()->getResult();
    }

    public function findRespOtherParcoursInFormation(Parcours $parcours, User $user): array
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.formation', 'f')
            ->andWhere('f = :formation')
            ->andWhere('p.respParcours = :user')
            ->andWhere('p.id <> :parcours')
            ->setParameter('formation', $parcours->getFormation())
            ->setParameter('user', $user)
            ->setParameter('parcours', $parcours)
            ->getQuery()
            ->getResult();
    }

    public function findAllParcoursId(){
        return $this->createQueryBuilder('p')
            ->select('p.id')
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findAllParcours()
    {
        return $this->createQueryBuilder('p')
            ->join('p.formation', 'f')
            ->select('p.id', 'f.id as formation_id', 'f.f.etatDpe as etatDpe')
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findAllParcoursForDpe(CampagneCollecte $campagneCollecte){
        return $this->createQueryBuilder('p')
            ->join('p.dpeParcours', 'dp')
            ->where('dp.campagneCollecte = :campagneCollecte')
            ->setParameter('campagneCollecte', $campagneCollecte)
            ->getQuery()
            ->getResult();

    }

    public function findByTypeValidationAttenteCfvuAndComposante(CampagneCollecte $campagneCollecte, string $typeValidation, Composante|int $composante)
    {
        if (!is_int($composante)) {
            $composante = $composante->getId();
        }

        $query = $this->createQueryBuilder('p')
            ->join('p.dpeParcours', 'dp')
            ->innerJoin('p.formation', 'f')
            ->andWhere('f.composantePorteuse = :composante')
            ->andWhere("JSON_CONTAINS(dp.etatValidation, :etatDpe) = 1")
            ->andWhere('dp.etatReconduction = :etatReconduction')
            ->orWhere('dp.etatReconduction = :etatReconduction2')
            ->setParameter('etatDpe', json_encode([$typeValidation => 1]))
            ->setParameter('composante', $composante)
            ->setParameter('etatReconduction', TypeModificationDpeEnum::MODIFICATION_MCCC)
            ->setParameter('etatReconduction2', TypeModificationDpeEnum::MODIFICATION_MCCC_TEXTE)
            ->andWhere('dp.campagneCollecte = :campagneCollecte')
            ->setParameter('campagneCollecte', $campagneCollecte);

        return $query->getQuery()
            ->getResult();

    }

    public function findByComposanteTypeValidation(Composante $composante,
                                                   CampagneCollecte $campagneCollecte,
                                                   string $typeValidation): array
    {
        $query = $this->createQueryBuilder('p')
            ->innerJoin('p.formation', 'f')
            ->innerJoin(Composante::class, 'c', 'WITH', 'f.composantePorteuse = c.id')
            ->leftJoin('p.dpeParcours', 'dp')
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

    public function findWithKeyword(string $keyword, CampagneCollecte $campagne) {
        $qb = $this->createQueryBuilder('p');

        $parcoursParDefaut = Parcours::PARCOURS_DEFAUT;

        $qb = $qb
            ->select(
                [
                    'p.id AS parcours_id', 'p.libelle AS parcours_libelle',
                    'p.sigle AS parcours_sigle', 'p.objectifsParcours',
                    'p.poursuitesEtudes', 'p.contenuFormation',
                    'p.resultatsAttendus', 'f.id AS formation_id',
                    'p.typeParcours AS type_parcours'
                ]
            )
            ->join('p.formation', 'f', 'WITH', 'p.formation = f.id')
            ->join('p.dpeParcours', 'dpe')
            ->where(
                $qb->expr()->like('UPPER(p.objectifsParcours)', 'UPPER(:keyword)')
            )
            ->orWhere(
                $qb->expr()->like('UPPER(p.poursuitesEtudes)', 'UPPER(:keyword)')
            )
            ->orWhere(
                $qb->expr()->like('UPPER(p.contenuFormation)', 'UPPER(:keyword)')
            )
            ->orWhere(
                $qb->expr()->like('UPPER(p.resultatsAttendus)', 'UPPER(:keyword)')
            )
            ->andWhere("p.libelle != :parcoursParDefaut")
            ->andWhere('dpe.campagneCollecte = :campagne')
            ->setParameter('parcoursParDefaut', $parcoursParDefaut)
            ->setParameter('keyword', '%' . $keyword . '%')
            ->setParameter(':campagne', $campagne);

        return $qb->getQuery()->getResult();
    }

    public function findWithKeywordForDefaultParcours(string $keyword, CampagneCollecte $campagne){
        $qb = $this->createQueryBuilder('p');

        $parcoursParDefaut = Parcours::PARCOURS_DEFAUT;

        $qb = $qb
            ->join('p.formation', 'f', 'WITH', 'f.id = p.formation')
            ->join('p.dpeParcours', 'dpe')
            ->select(
                [
                    'f.id AS formation_id', 'f.slug AS formation_slug', 'p.id AS parcours_id',
                    'f.contenuFormation', 'f.resultatsAttendus', 'f.objectifsFormation',
                    'p.poursuitesEtudes', 'p.libelle AS parcours_libelle', 'f.sigle AS formation_sigle',
                    'p.typeParcours AS type_parcours'
                ]
            )
            ->where(
                $qb->expr()->like('UPPER(f.contenuFormation)', 'UPPER(:keyword)')
            )
            ->orWhere(
                $qb->expr()->like('UPPER(f.resultatsAttendus)', 'UPPER(:keyword)')
            )
            ->orWhere(
                $qb->expr()->like('UPPER(f.objectifsFormation)', 'UPPER(:keyword)')
            )->orWhere(
                $qb->expr()->like('UPPER(p.poursuitesEtudes)', 'UPPER(:keyword)')
            )
            ->andWhere('p.libelle = :parcoursParDefaut')
            ->andWhere('dpe.campagneCollecte = :campagne')
            ->setParameter('parcoursParDefaut', $parcoursParDefaut)
            ->setParameter('keyword', '%' . $keyword . '%')
            ->setParameter(':campagne', $campagne);

            return $qb->getQuery()->getResult();
    }

    public function findByCampagneCollecte(CampagneCollecte $getCampagneCollecte): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.dpeParcours', 'dp')
            ->andWhere('dp.campagneCollecte = :campagneCollecte')
            ->andWhere('p.libelle <> :libelle')
            ->setParameter('libelle', Parcours::PARCOURS_DEFAUT)
            ->setParameter('campagneCollecte', $getCampagneCollecte)
            ->orderBy('p.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findFromAnneeUniversitaire(int $idCampagneCollecte) : array {
        $qb = $this->createQueryBuilder('parcours');

        $subqueryCheck = $this->createQueryBuilder('p')
            ->select('pCopie.id')
            ->join('p.parcoursOrigineCopie', 'pCopie');


        return $qb->select('DISTINCT parcours.id')
            ->join('parcours.dpeParcours', 'dpeP')
            ->join('dpeP.campagneCollecte', 'campC')
            ->andWhere('campC.id = :idCampagne')
            ->andWhere(
                $qb->expr()->notIn(
                    'parcours.id', $subqueryCheck->getDQL()
                )
            )
            ->setParameter(':idCampagne', $idCampagneCollecte)
            ->getQuery()
            ->getResult();
    }

    public function findParcoursDeBaseAlternance(string $libelle, int $idCampagneCollecte) {
        $qb = $this->createQueryBuilder('p');

        return $qb
            ->join('p.dpeParcours', 'dpeP')
            ->join('dpeP.campagneCollecte', 'campC')
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->neq('p.typeParcours', ':typeAlternance'),
                    $qb->expr()->isNull('p.typeParcours')
                )
            )
            ->andWhere("p.libelle = :libelle")
            ->andWhere('campC.id = :idCampagne')
            ->setParameter(':libelle', $libelle)
            ->setParameter(':idCampagne', $idCampagneCollecte)
            ->setParameter(':typeAlternance', 'alternance')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array Ordre des semestres non dispensés ou manquant - ex: [1, 2, 3, 4]
     */
    public function findParcoursAlternanceHasMissingSemestre(Parcours $parcours) : array {
        // semestre 1 & 2 non dispensés ou absents
        $subQuery1 = $this->createQueryBuilder('p1');
        $subQuery1 = $subQuery1->select('p1.id')
            ->leftJoin('p1.semestreParcours', 'sp1')
            ->leftJoin('sp1.semestre', 'sem1')
            ->andWhere(
                $subQuery1->expr()->orX(
                    $subQuery1->expr()->andX(
                        $subQuery1->expr()->in('sem1.ordre', ':ordre1ou2'),
                        $subQuery1->expr()->eq('sem1.nonDispense', ':nonDispenseFlag')
                    ),
                    $subQuery1->expr()->andX(
                        $subQuery1->expr()->in('sem1.ordre', ':ordre1ou2'),
                        $subQuery1->expr()->isNull('sp1')
                    )
                )
            )
            ->andWhere('p1.id = :parcoursId')
            ->setParameter(':ordre1ou2', [1, 2])
            ->setParameter(':nonDispenseFlag', 1)
            ->setParameter(':parcoursId', $parcours->getId())
            ->getQuery()
            ->getResult();


        // semestre 3 ou 4 non dispensés
        $subQuery2 = $this->createQueryBuilder('p2');
        $subQuery2 = $subQuery2->select('p2.id')
            ->leftJoin('p2.semestreParcours', 'sp2')
            ->leftJoin('sp2.semestre', 'sem2')
            ->andWhere(
                $subQuery2->expr()->orX(
                    $subQuery2->expr()->andX(
                        $subQuery2->expr()->in('sem2.ordre', ':ordre3ou4'),
                        $subQuery2->expr()->eq('sem2.nonDispense', ':nonDispenseFlag')
                    ),
                    $subQuery2->expr()->andX(
                        $subQuery2->expr()->in('sem2.ordre', ':ordre3ou4'),
                        $subQuery2->expr()->isNull('sp2')
                    )
                )
            )->andWhere('p2.id= :parcoursId')
            ->setParameter(':ordre3ou4', [3, 4])
            ->setParameter(':nonDispenseFlag', 1)
            ->setParameter(':parcoursId', $parcours->getId())
            ->getQuery()
            ->getResult();


        $result = [];
        if(in_array($parcours->getId(), array_merge(...$subQuery1))) {
            $result[] = 1;
            $result[] = 2;
        }

        if(in_array($parcours->getId(), array_merge(...$subQuery2))) {
            $result[] = 3;
            $result[] = 4;
        }

        return $result;
    }

    public function findAllByCampagneCollecte(CampagneCollecte $campagneC) {
        return $this->createQueryBuilder("p")
            ->join('p.dpeParcours', 'dpe')
            ->join('dpe.campagneCollecte', 'camp')
            ->andWhere('camp.id = :idCampagne')
            ->setParameter(':idCampagne', $campagneC->getId())
            ->getQuery()
            ->getResult();
    } 
}
