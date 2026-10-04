<?php

namespace App\Repository;

use App\Entity\Composante;
use App\Entity\Formation;
use App\Entity\Mention;
use App\Entity\Parcours;
use App\Entity\Semestre;
use App\Entity\TypeUe;
use App\Entity\UeMutualisable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<UeMutualisable>
 *
 * @method UeMutualisable|null find($id, $lockMode = null, $lockVersion = null)
 * @method UeMutualisable|null findOneBy(array $criteria, array $orderBy = null)
 * @method UeMutualisable[]    findAll()
 * @method UeMutualisable[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UeMutualisableRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UeMutualisable::class);
    }

    public function save(UeMutualisable $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UeMutualisable $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findAllBy(array $options): array
    {
        $qb = $this->createQueryBuilder('u')
            ->join('u.ue', 'ue');

        $joinedUeSemestre = false;
        $joinedUeSemestreParcours = false;
        $joinedUeParcours = false;
        $joinedUeFormation = false;
        $joinedUeMention = false;

        $joinedUParcours = false;
        $joinedUFormation = false;
        $joinedUMention = false;
        $joinedUComposante = false;
        $joinedTypeUe = false;

        foreach ($options as $sort => $direction) {
            if ($sort === 'formation') {
                if (!$joinedUeSemestre) {
                    $qb->leftJoin(Semestre::class, 'ue_s', 'WITH', 'ue.semestre = ue_s.id');
                    $joinedUeSemestre = true;
                }
                if (!$joinedUeSemestreParcours) {
                    $qb->join('ue_s.semestreParcours', 'ue_sp');
                    $joinedUeSemestreParcours = true;
                }
                if (!$joinedUeParcours) {
                    $qb->leftJoin(Parcours::class, 'ue_p', 'WITH', 'ue_sp.parcours = ue_p.id');
                    $joinedUeParcours = true;
                }
                if (!$joinedUeFormation) {
                    $qb->leftJoin(Formation::class, 'ue_fo', 'WITH', 'ue_p.formation = ue_fo.id');
                    $joinedUeFormation = true;
                }
                if (!$joinedUeMention) {
                    $qb->leftJoin(Mention::class, 'ue_m', 'WITH', 'ue_fo.mention = ue_m.id');
                    $joinedUeMention = true;
                }
                $qb->addOrderBy(
                    'CASE
                        WHEN ue_fo.mention IS NOT NULL THEN ue_m.libelle
                        WHEN ue_fo.mentionTexte IS NOT NULL THEN ue_fo.mentionTexte
                        ELSE ue_fo.mentionTexte
                        END',
                    $direction
                );
            } elseif ($sort === 'composante') {
                if (!$joinedUParcours) {
                    $qb->leftJoin(Parcours::class, 'u_p', 'WITH', 'u.parcours = u_p.id');
                    $joinedUParcours = true;
                }
                if (!$joinedUFormation) {
                    $qb->innerJoin(Formation::class, 'u_fo', 'WITH', 'u_p.formation = u_fo.id');
                    $joinedUFormation = true;
                }
                if (!$joinedUComposante) {
                    $qb->innerJoin(Composante::class, 'u_co', 'WITH', 'u_fo.composantePorteuse = u_co.id');
                    $joinedUComposante = true;
                }
                $qb->addOrderBy('u_co.libelle', $direction);
            } elseif ($sort === 'mention') {
                if (!$joinedUParcours) {
                    $qb->leftJoin(Parcours::class, 'u_p', 'WITH', 'u.parcours = u_p.id');
                    $joinedUParcours = true;
                }
                if (!$joinedUFormation) {
                    $qb->leftJoin(Formation::class, 'u_fo', 'WITH', 'u_p.formation = u_fo.id');
                    $joinedUFormation = true;
                }
                if (!$joinedUMention) {
                    $qb->leftJoin(Mention::class, 'u_m', 'WITH', 'u_fo.mention = u_m.id');
                    $joinedUMention = true;
                }
                $qb->addOrderBy(
                    'CASE
                        WHEN u_fo.mention IS NOT NULL THEN u_m.libelle
                        WHEN u_fo.mentionTexte IS NOT NULL THEN u_fo.mentionTexte
                        ELSE u_fo.mentionTexte
                        END',
                    $direction
                );
            } elseif ($sort === 'parcours') {
                if (!$joinedUParcours) {
                    $qb->leftJoin(Parcours::class, 'u_p', 'WITH', 'u.parcours = u_p.id');
                    $joinedUParcours = true;
                }
                $qb->addOrderBy('u_p.libelle', $direction);
            } elseif ($sort === 'typeUe') {
                if (!$joinedTypeUe) {
                    $qb->leftJoin(TypeUe::class, 'tu', 'WITH', 'ue.typeUe = tu.id');
                    $joinedTypeUe = true;
                }
                $qb->addOrderBy('tu.libelle', $direction);
            } else {
                $qb->addOrderBy('ue.' . $sort, $direction);
            }
        }

        return $qb->getQuery()->getResult();
    }

    public function findByParcours(UserInterface $user, array $options): array
    {
        //todo: filtrer selon les parcours de l'utilisateur
        return $this->findAllBy($options);
    }

    public function findFromAnneeUniversitaire(int $idCampagneCollecte) : array {
        $qb = $this->createQueryBuilder('ueMutu');

        $subQueryCheckUeSemestre = $this->createQueryBuilder('ueSemAnnee')
            ->select('ueSemAnnee.id')
            ->join('ueSemAnnee.ue', 'firstUe')
            ->join('firstUe.semestre', 'firstSemestre')
            ->join('firstSemestre.semestreParcours', 'firstSemParcours')
            ->join('firstSemParcours.parcours', 'firstParc')
            ->join('firstParc.dpeParcours','firstDpe')
            ->join('firstDpe.campagneCollecte', 'firstCampagne')
            ->andWhere('firstCampagne.id = :idCampagne');

        $subQueryCheckUeParcours = $this->createQueryBuilder('ueParcoursAnnee')
            ->select('ueParcoursAnnee.id')
            ->join('ueParcoursAnnee.parcours', 'secondParc')
            ->join('secondParc.dpeParcours', 'secondDpe')
            ->join('secondDpe.campagneCollecte', 'secondCampagne')
            ->andWhere('secondCampagne.id = :idCampagne');

        return $qb->select('DISTINCT ueMutu.id')
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->in(
                        'ueMutu.id', $subQueryCheckUeSemestre->getDQL()
                    ),
                    $qb->expr()->in(
                        'ueMutu.id', $subQueryCheckUeParcours->getDQL()
                    )
                )
            )->setParameter(':idCampagne', $idCampagneCollecte)
            ->getQuery()
            ->getResult();
    }
}
