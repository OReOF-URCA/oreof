<?php

namespace App\Repository;

use App\Entity\Composante;
use App\Entity\Formation;
use App\Entity\Mention;
use App\Entity\Parcours;
use App\Entity\SemestreMutualisable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SemestreMutualisable>
 *
 * @method SemestreMutualisable|null find($id, $lockMode = null, $lockVersion = null)
 * @method SemestreMutualisable|null findOneBy(array $criteria, array $orderBy = null)
 * @method SemestreMutualisable[]    findAll()
 * @method SemestreMutualisable[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SemestreMutualisableRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SemestreMutualisable::class);
    }

    public function save(SemestreMutualisable $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(SemestreMutualisable $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findAllBy(array $options): array
    {
        $qb = $this->createQueryBuilder('s')
            ->join('s.semestre', 'sem');

        $joinedSemParcours = false;
        $joinedSemFormation = false;
        $joinedSemMention = false;
        $joinedSemestreParcours = false;

        $joinedSParcours = false;
        $joinedSFormation = false;
        $joinedSMention = false;
        $joinedSComposante = false;

        foreach ($options as $sort => $direction) {
            if ($sort === 'formation') {
                if (!$joinedSemestreParcours) {
                    $qb->join('sem.semestreParcours', 'sem_sp');
                    $joinedSemestreParcours = true;
                }
                if (!$joinedSemParcours) {
                    $qb->leftJoin(Parcours::class, 'sem_p', 'WITH', 'sem_sp.parcours = sem_p.id');
                    $joinedSemParcours = true;
                }
                if (!$joinedSemFormation) {
                    $qb->leftJoin(Formation::class, 'sem_fo', 'WITH', 'sem_p.formation = sem_fo.id');
                    $joinedSemFormation = true;
                }
                if (!$joinedSemMention) {
                    $qb->leftJoin(Mention::class, 'sem_m', 'WITH', 'sem_fo.mention = sem_m.id');
                    $joinedSemMention = true;
                }
                $qb->addOrderBy(
                    'CASE
                        WHEN sem_fo.mention IS NOT NULL THEN sem_m.libelle
                        WHEN sem_fo.mentionTexte IS NOT NULL THEN sem_fo.mentionTexte
                        ELSE sem_fo.mentionTexte
                        END',
                    $direction
                );
            } elseif ($sort === 'composante') {
                if (!$joinedSParcours) {
                    $qb->leftJoin(Parcours::class, 's_p', 'WITH', 's.parcours = s_p.id');
                    $joinedSParcours = true;
                }
                if (!$joinedSFormation) {
                    $qb->innerJoin(Formation::class, 's_fo', 'WITH', 's_p.formation = s_fo.id');
                    $joinedSFormation = true;
                }
                if (!$joinedSComposante) {
                    $qb->innerJoin(Composante::class, 's_co', 'WITH', 's_fo.composantePorteuse = s_co.id');
                    $joinedSComposante = true;
                }
                $qb->addOrderBy('s_co.libelle', $direction);
            } elseif ($sort === 'mention') {
                if (!$joinedSParcours) {
                    $qb->leftJoin(Parcours::class, 's_p', 'WITH', 's.parcours = s_p.id');
                    $joinedSParcours = true;
                }
                if (!$joinedSFormation) {
                    $qb->leftJoin(Formation::class, 's_fo', 'WITH', 's_p.formation = s_fo.id');
                    $joinedSFormation = true;
                }
                if (!$joinedSMention) {
                    $qb->leftJoin(Mention::class, 's_m', 'WITH', 's_fo.mention = s_m.id');
                    $joinedSMention = true;
                }
                $qb->addOrderBy(
                    'CASE
                        WHEN s_fo.mention IS NOT NULL THEN s_m.libelle
                        WHEN s_fo.mentionTexte IS NOT NULL THEN s_fo.mentionTexte
                        ELSE s_fo.mentionTexte
                        END',
                    $direction
                );
            } elseif ($sort === 'parcours') {
                if (!$joinedSParcours) {
                    $qb->leftJoin(Parcours::class, 's_p', 'WITH', 's.parcours = s_p.id');
                    $joinedSParcours = true;
                }
                $qb->addOrderBy('s_p.libelle', $direction);
            } else {
                $qb->addOrderBy('sem.' . $sort, $direction);
            }
        }

        return $qb->getQuery()->getResult();
    }

    public function findByParcours(array $options): array
    {
        return $this->findAllBy($options);
    }

    public function findFromAnneeUniversitaire(int $idCampagneCollecte) : array {
        return $this->createQueryBuilder('semestreMutu')
            ->select('semestreMutu.id')
            ->join('semestreMutu.parcours', 'parcours')
            ->join('parcours.dpeParcours', 'dpe')
            ->join('dpe.campagneCollecte', 'campagne')
            ->andWhere('campagne.id = :idCampagne')
            ->setParameter(':idCampagne', $idCampagneCollecte)
            ->getQuery()
            ->getResult();
    }
}
