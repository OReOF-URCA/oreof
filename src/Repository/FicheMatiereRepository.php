<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Repository/ElementConstitutifRepository.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 08/03/2023 11:26
 */

namespace App\Repository;

use App\Entity\CampagneCollecte;
use App\Entity\Composante;
use App\Entity\DpeParcours;
use App\Entity\FicheMatiere;
use App\Entity\Formation;
use App\Entity\Mention;
use App\Entity\Parcours;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<FicheMatiere>
 *
 * @method FicheMatiere|null find($id, $lockMode = null, $lockVersion = null)
 * @method FicheMatiere|null findOneBy(array $criteria, array $orderBy = null)
 * @method FicheMatiere[]    findAll()
 * @method FicheMatiere[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FicheMatiereRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FicheMatiere::class);
    }

    public function save(FicheMatiere $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(FicheMatiere $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByParcours(Parcours $parcours, CampagneCollecte $campagneCollecte): array
    {
        //soit la fiche est portée par le parcours, soit elle est mutualisée avec ce parcours
        return $this->createQueryBuilder('f')
            ->leftJoin('f.parcours', 'p')
            ->leftJoin('f.ficheMatiereParcours', 'pm')
            ->where('f.campagneCollecte = :campagneCollecte')
            ->andWhere('p = :parcours')
            ->orWhere('pm.parcours = :parcours')
            ->setParameter('parcours', $parcours)
            ->setParameter('campagneCollecte', $campagneCollecte)
            ->orderBy('f.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByAdmin(
        CampagneCollecte $campagneCollecte,
        array            $options = []
    ): array {
        $qb = $this->createQueryBuilder('f')
            ->leftJoin(Parcours::class, 'p', 'WITH', 'f.parcours = p.id')
            ->join(Formation::class, 'fo', 'WITH', 'p.formation = fo.id')
            ->andWhere('f.campagneCollecte = :campagneCollecte')
            ->andWhere('f.horsDiplome = 0 or f.horsDiplome IS NULL')
            ->setParameter('campagneCollecte', $campagneCollecte);

        $this->addFiltres($qb, $options);
        if (array_key_exists('utilise', $options) && "1" === $options['utilise']) {
            $qb
                ->leftJoin('f.elementConstitutifs', 'ec')
                ->groupBy('f.id')
                ->having('count(ec.id) > 0');
        }

        if (array_key_exists('utilise', $options) && "0" === $options['utilise']) {
            $qb
                ->leftJoin('f.elementConstitutifs', 'ec')
                ->groupBy('f.id')
                ->having('count(ec.id) = 0');
        }

        $query =  $qb->getQuery();

        $paginator = new Paginator($query, false);
        return [
            'data' => iterator_to_array($paginator),
            'total' => count($paginator),
            'page' => $options['page'] ?? 1,
            'limit' => 50,
        ];
    }

    private function addFiltres(QueryBuilder $qb, array $options, bool $count = false): void
    {
        $sort = $options['sort'] ?? 'mention';
        $direction = $options['direction'] ?? 'ASC';
        $start = max(1, (int)($options['page'] ?? 1));

        if (array_key_exists('parcours', $options) && null !== $options['parcours']) {
            $qb->andWhere('p.id = :parcours')
                ->setParameter('parcours', $options['parcours']);
        }

        if (array_key_exists('mention', $options) && null !== $options['mention']) {
            $qb->andWhere('fo.mention = :mention')
                ->setParameter('mention', $options['mention']);
        }

        if (array_key_exists('referent', $options) && null !== $options['referent']) {
            if ($options['referent'] === 'vide') {
                $qb->andWhere('f.responsableFicheMatiere IS NULL');
            } else {
                $qb->andWhere('f.responsableFicheMatiere = :referent')
                    ->setParameter('referent', $options['referent']);
            }
        }

        if (array_key_exists('q', $options) && null !== $options['q']) {
            $qb->andWhere('f.libelle LIKE :q or f.id LIKE :q')
                ->setParameter('q', '%' . $options['q'] . '%');
        }

        if (array_key_exists('libelle', $options) && null !== $options['libelle']) {
            $qb->andWhere('f.libelle LIKE :q')
                ->setParameter('q', '%' . $options['libelle'] . '%');
        }

        if (isset($options['remplissage']) && null !== $options['remplissage'] && 'all' !== $options['remplissage']) {
            if ($options['remplissage'] === '100') {
                $qb->andWhere('JSON_EXTRACT(f.remplissage, \'$.pourcentage\') = :remplissage')
                    ->setParameter('remplissage', 100);
            } else {
                $qb->andWhere('JSON_EXTRACT(f.remplissage, \'$.pourcentage\') < :remplissage')
                    ->setParameter('remplissage', 100);
            }
        }

        if ($sort === 'responsableFicheMatiere') {
            $qb->join('f.responsableFicheMatiere', 'u');
            $qb->addOrderBy('u.nom', $direction);
            $qb->addOrderBy('u.prenom', $direction);
        } elseif ($sort === 'mention') {
            $qb->leftJoin(Mention::class, 'm', 'WITH', 'fo.mention = m.id');
            $qb->addOrderBy(
                'CASE
                        WHEN fo.mention IS NOT NULL THEN m.libelle
                        WHEN fo.mentionTexte IS NOT NULL THEN fo.mentionTexte
                        ELSE fo.mentionTexte
                        END',
                $direction
            );
        } else {
            $qb->addOrderBy('f.' . $sort, $direction);
        }

        if ($count === false) {
            $qb->setFirstResult(($start - 1) * 50)
                ->setMaxResults($options['length'] ?? 50);
        }
    }

    public function findByHd(CampagneCollecte $campagneCollecte, array $options): array
    {
        $start = max(1, (int)($options['page'] ?? 1));
        $query = $this->createQueryBuilder('f')
            ->where('f.horsDiplome = 1')
            ->andWhere('f.campagneCollecte = :campagneCollecte')
            ->setParameter('campagneCollecte', $campagneCollecte)
            ->orderBy('f.libelle', 'ASC')
            ->setFirstResult(($start - 1) * 50)
            ->setMaxResults($options['length'] ?? 50);

        //$this->addFiltres($query, $options);

        if (array_key_exists('q', $options) && null !== $options['q']) {
            $query->andWhere('f.libelle LIKE :q')
                ->setParameter('q', '%' . $options['q'] . '%');
        }

        if (array_key_exists('utilise', $options) && "1" === $options['utilise']) {
            $query
                ->leftJoin('f.elementConstitutifs', 'ec')
                ->groupBy('f.id')
                ->having('count(ec.id) > 0');
        }

        if (array_key_exists('utilise', $options) && "0" === $options['utilise']) {

            $query
                ->leftJoin('f.elementConstitutifs', 'ec')
                ->groupBy('f.id')
                ->having('count(ec.id) = 0');
        }

        $qb = $query->getQuery();

        $paginator = new Paginator($qb, false);

        return [
            'data' => iterator_to_array($paginator),
            'total' => count($paginator),
            'page' => $options['page'] ?? 1,
            'limit' => 50,
        ];
    }

    public function findByCodeAndFormation(mixed $codeMatiere, Formation $formation): array
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.parcours', 'p')
            ->join('p.formation', 'fo')
            ->where('fo = :formation')
            ->andWhere('f.sigle = :code')
            ->setParameter('formation', $formation)
            ->setParameter('code', $codeMatiere)
            ->getQuery()
            ->getResult();
    }

    public function findByDpe(?UserInterface $user, CampagneCollecte $campagneCollecte, array $options): array
    {
        $query = $this->createQueryBuilder('f')
            ->join('f.parcours', 'p')
            ->join('p.formation', 'fo')
            ->join('fo.composantePorteuse', 'co')
            ->andWhere('f.campagneCollecte = :campagneCollecte')
            ->andWhere('(fo.responsableMention = :user OR fo.coResponsable = :user OR p.respParcours = :user OR p.coResponsable = :user OR f.responsableFicheMatiere = :user OR co.responsableDpe = :user)')
            ->orderBy('f.libelle', 'ASC')
            ->setParameters([
                'campagneCollecte' => $campagneCollecte,
                'user' => $user
            ]);

        $this->addFiltres($query, $options);

        $qb = $query->getQuery();

        $paginator = new Paginator($qb, false);

        return [
            'data' => iterator_to_array($paginator),
            'total' => count($paginator),
            'page' => $options['page'] ?? 1,
            'limit' => 50,
        ];
    }


    public function findByResponsable(?UserInterface $user, CampagneCollecte $campagneCollecte, array $options): array
    {
        $query = $this->createQueryBuilder('f')
            ->join('f.parcours', 'p')
            ->join('p.formation', 'fo')
            ->andWhere('f.campagneCollecte = :campagneCollecte')
            ->andWhere('(fo.responsableMention = :user OR fo.coResponsable = :user OR p.respParcours = :user OR p.coResponsable = :user OR f.responsableFicheMatiere = :user)')
            ->orderBy('f.libelle', 'ASC')
            ->setParameters([
                'campagneCollecte' => $campagneCollecte,
                'user' => $user
            ]);

        $this->addFiltres($query, $options);

        $qb = $query->getQuery();

        $paginator = new Paginator($qb, false);

        return [
            'data' => iterator_to_array($paginator),
            'total' => count($paginator),
            'page' => $options['page'] ?? 1,
            'limit' => 50,
        ];
    }

    public function findByComposanteTypeValidation(Composante $composante, CampagneCollecte $campagneCollecte, mixed $transition): array
    {
        $qb = $this->createQueryBuilder('f')
            ->join(Parcours::class, 'p', 'WITH', 'f.parcours = p.id')
            ->join(Formation::class, 'fo', 'WITH', 'p.formation = fo.id')
            ->join(DpeParcours::class, 'dp', 'WITH', 'p.id = dp.parcours')
            ->andWhere('dp.campagneCollecte = :campagneCollecte')
            ->andWhere("JSON_CONTAINS(f.etatFiche, :transition) = 1")
            ->setParameter('transition', json_encode([$transition => 1]))
            ->andWhere('fo.composantePorteuse = :composante')
            ->setParameter('campagneCollecte', $campagneCollecte)
            ->setParameter('composante', $composante);

        return $qb->getQuery()->getResult();
    }

    public function findByTypeValidation(CampagneCollecte $campagneCollecte, mixed $transition): array
    {
        $qb = $this->createQueryBuilder('f')
        ->join(Parcours::class, 'p', 'WITH', 'f.parcours = p.id')
        ->join(Formation::class, 'fo', 'WITH', 'p.formation = fo.id')
        ->join(DpeParcours::class, 'dp', 'WITH', 'p.id = dp.parcours')
        ->andWhere('dp.campagneCollecte = :campagneCollecte')
        ->andWhere("JSON_CONTAINS(f.etatFiche, :transition) = 1")
        ->setParameter('transition', json_encode([$transition => 1]))
        ->setParameter('campagneCollecte', $campagneCollecte);

        return $qb->getQuery()->getResult();
    }

    public function findByTypeValidationNull(CampagneCollecte $campagneCollecte): array
    {
        $qb = $this->createQueryBuilder('f')
            ->join(Parcours::class, 'p', 'WITH', 'f.parcours = p.id')
            ->join(Formation::class, 'fo', 'WITH', 'p.formation = fo.id')
            ->join(DpeParcours::class, 'dp', 'WITH', 'p.id = dp.parcours')
            ->andWhere('dp.campagneCollecte = :campagneCollecte')
            ->andWhere('f.etatFiche IS NULL')
            ->setParameter('campagneCollecte', $campagneCollecte);

        return $qb->getQuery()->getResult();
    }

    public function countDuplicatesCode() : array
    {
        return $this->createQueryBuilder('fm')
            ->select('count(fm.codeApogee)')
            ->where('fm.codeApogee IS NOT NULL')
            ->groupBy('fm.codeApogee')
            ->having('count(fm.codeApogee) > 1')
            ->getQuery()
            ->getResult();
    }

    public function findByTypeValidationHorsDiplome(CampagneCollecte $campagneCollecte, mixed $transition): array
    {
        $qb = $this->createQueryBuilder('f')
            ->where('f.horsDiplome = 1')
            //->andWhere('dp.campagneCollecte = :campagneCollecte')
            ->andWhere("JSON_CONTAINS(f.etatFiche, :transition) = 1")
            ->setParameter('transition', json_encode([$transition => 1]));
        // ->setParameter('campagneCollecte', $campagneCollecte);

        return $qb->getQuery()->getResult();
    }

    public function findForParcoursWithKeyword(Parcours $parcours, string $keyword)
    {
        $qb = $this->createQueryBuilder('fm');

        $qb = $qb->select('fm.id, fm.description, fm.objectifs, fm.slug, fm.libelle')
            ->where(
                $qb->expr()->like('UPPER(fm.description)', 'UPPER(:keyword)')
            )
            ->andWhere('fm.parcours = :parcours')
            ->setParameter('keyword', '%' . $keyword . '%')
            ->setParameter('parcours', $parcours);

        return $qb->getQuery()->getResult();
    }

    public function findCountForKeyword(string $keyword, CampagneCollecte $campagne) : array
    {
        $qb = $this->createQueryBuilder('fm');

        $qb = $qb->select('COUNT(fm.id) AS nombre_total')
            ->innerJoin('fm.parcours', 'p')
            ->join('p.formation', 'f', 'WITH', 'p.formation = f.id')
            ->join('f.mention', 'm')
            ->join('f.typeDiplome', 'td')
            ->where(
                $qb->expr()->andX(
                    $qb->expr()->orX(
                        $qb->expr()->like('UPPER(fm.description)', 'UPPER(:keyword)'),
                        $qb->expr()->like('UPPER(fm.objectifs)', 'UPPER(:keyword)')
                    ),
                    $qb->expr()->eq('fm.campagneCollecte', ':campagne')
                )
            )
            ->setParameter('keyword', '%' . $keyword . '%')
            ->setParameter(':campagne', $campagne)
        ->getQuery()
        ->getResult();

        return $qb;

    }

    public function findFicheMatiereWithKeywordAndPagination(
        string $keyword,
        int    $pageNumber,
        bool   $paginate,
        CampagneCollecte $campagne,
    ) : array
    {
        $qb = $this->createQueryBuilder('fm');

        $firstResults = $pageNumber > 1 ? ($pageNumber - 1) * 30 : 0;

        $qb = $qb->select(
            [
                'fm.id AS fiche_matiere_id', 'fm.slug AS fiche_matiere_slug', 'fm.sigle AS fiche_matiere_sigle',
                'fm.objectifs AS fiche_matiere_objectifs', 'fm.description AS fiche_matiere_description',
                'fm.libelle AS fiche_matiere_libelle', 'p.id AS parcours_id',
                'fm.sigle AS formation_sigle', 'm.libelle AS mention_libelle',
                'p.libelle AS parcours_libelle', 'td.libelle AS type_diplome_libelle',
                'p.sigle AS parcours_sigle'
            ]
        )
        ->innerJoin('fm.parcours', 'p')
        ->join('p.formation', 'f', 'WITH', 'p.formation = f.id')
        ->join('f.mention', 'm')
        ->join('f.typeDiplome', 'td')
        ->where(
            $qb->expr()->andX(
                $qb->expr()->orX(
                    $qb->expr()->like('UPPER(fm.description)', 'UPPER(:keyword)'),
                    $qb->expr()->like('UPPER(fm.objectifs)', 'UPPER(:keyword)')
                ),
                $qb->expr()->eq('fm.campagneCollecte', ':campagne')
            )
        )
        ->setParameter('keyword', '%' . $keyword . '%')
        ->setParameter(':campagne', $campagne);


        if($paginate) {
            $qb = $qb->setFirstResult($firstResults)
                ->setMaxResults(30);
        }
        $qb = $qb->getQuery()
            ->getResult();

        return $qb;
    }

    public function findAllWithPagination(int $pageNumber, int $pageLength)
    {
        return $this->createQueryBuilder('fm')
            ->orderBy('id', 'ASC')
            ->setMaxResults($pageLength)
            ->setFirstResult($pageNumber * $pageLength)
            ->getQuery()
            ->getResult();
    }

    public function findAllByHd(CampagneCollecte $campagneCollecte): array
    {
        $query = $this->createQueryBuilder('f')
            ->where('f.horsDiplome = 1')
            ->andWhere('f.campagneCollecte = :campagneCollecte')
            ->setParameter('campagneCollecte', $campagneCollecte)
            ->orderBy('f.libelle', 'ASC');

        return $query->getQuery()->getResult();
    }

    public function findByCampagneCollecteForStats(CampagneCollecte $campagne)
    {
        $qb = $this->createQueryBuilder('f')
            ->select('f.etatFiche AS etat, COUNT(f.id) AS nb')
            ->andWhere('f.campagneCollecte = :campagne')
            ->setParameter('campagne', $campagne)
            ->groupBy('etat');

        return $qb->getQuery()->getArrayResult();
    }

    public function findByComposanteAndCampagneForStats(Composante $composante, CampagneCollecte $campagne): array
    {
        $qb = $this->createQueryBuilder('f')
            ->select('f.etatFiche AS etat, COUNT(f.id) AS nb')
            ->join(Parcours::class, 'p', 'WITH', 'f.parcours = p.id')
            ->join(Formation::class, 'fo', 'WITH', 'p.formation = fo.id')
            ->andWhere('fo.composantePorteuse = :composante')
            ->andWhere('f.campagneCollecte = :campagne')
            ->setParameter('composante', $composante)
            ->setParameter('campagne', $campagne)
            ->groupBy('etat');

        return $qb->getQuery()->getArrayResult();
    }

    public function findFromAnneeUniversitaire(int $idCampagneCollecte) : array {
        $qb = $this->createQueryBuilder('ficheMatiere');

        $subQueryParcoursAnnee = $this->createQueryBuilder('ficheMatiereAnnee')
            ->select('ficheMatiereAnnee.id')
            ->join('ficheMatiereAnnee.parcours', 'parcoursAnnee')
            ->join('parcoursAnnee.dpeParcours', 'dpeAnnee')
            ->join('dpeAnnee.campagneCollecte', 'campagneAnnee')
            ->andWhere('campagneAnnee.id = :idCampagne');

        $subQueryFicheHorsDiplomeAnnee = $this->createQueryBuilder('ficheHDAnnee')
            ->select('ficheHDAnnee.id')
            ->join('ficheHDAnnee.ficheMatiereParcours', 'ficheHDMutu')
            ->join('ficheHDMutu.parcours', 'parcoursMutu')
            ->join('parcoursMutu.dpeParcours', 'dpeMutu')
            ->join('dpeMutu.campagneCollecte', 'campagneMutu')
            ->andWhere('ficheHDAnnee.horsDiplome = 1')
            ->andWhere('campagneMutu.id = :idCampagne');

        $subQueryFicheNotAlreadyCopied = $this->createQueryBuilder('f')
            ->select('fCopy.id')
            ->join('f.ficheMatiereOrigineCopie', 'fCopy');

        $subQueryFicheHorsDiplomeSansParcours = $this->createQueryBuilder('ficheHD')
            ->select('ficheHD.id')
            ->join('ficheHD.campagneCollecte', 'camp')
            ->andWhere('ficheHD.horsDiplome = 1')
            ->andWhere('camp.id = :idCampagne');

        return $qb
            ->select('DISTINCT ficheMatiere.id')
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->in(
                        'ficheMatiere.id', $subQueryParcoursAnnee->getDQL()
                    ),
                    $qb->expr()->in(
                        'ficheMatiere.id', $subQueryFicheHorsDiplomeAnnee->getDQL()
                    ),
                    $qb->expr()->in(
                        'ficheMatiere.id', $subQueryFicheHorsDiplomeSansParcours->getDQL()
                    )
                )
            )
            ->andWhere(
                $qb->expr()->notIn('ficheMatiere.id', $subQueryFicheNotAlreadyCopied->getDQL())
            )
            ->setParameter(':idCampagne', $idCampagneCollecte)
            ->getQuery()
            ->getResult();
    }

    public function qbForParcoursOrHd(Parcours $parcours, CampagneCollecte $campagneCollecte): QueryBuilder
    {
        //
//        $matieres[] = $this->ficheMatiereRepository->findByParcours($parcours, $campagneCollecte);
//        $matieres[] = $this->ficheMatiereRepository->findAllByHd($campagneCollecte);
        return $this->createQueryBuilder('fm')
            ->where('fm.parcours = :parcours')
            ->orWhere('fm.horsDiplome = 1 AND fm.campagneCollecte = :campagneCollecte')
            ->setParameter('parcours', $parcours)
            ->setParameter('campagneCollecte', $campagneCollecte)
            ->orderBy('fm.libelle', 'ASC');
    }

}
