<?php

namespace App\Repository;

use App\Entity\Composante;
use App\Entity\FicheMatiere;
use App\Entity\FicheMatiereMutualisable;
use App\Entity\Formation;
use App\Entity\Mention;
use App\Entity\Parcours;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<FicheMatiereMutualisable>
 *
 * @method FicheMatiereMutualisable|null find($id, $lockMode = null, $lockVersion = null)
 * @method FicheMatiereMutualisable|null findOneBy(array $criteria, array $orderBy = null)
 * @method FicheMatiereMutualisable[]    findAll()
 * @method FicheMatiereMutualisable[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FicheMatiereMutualisableRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FicheMatiereMutualisable::class);
    }

    public function save(FicheMatiereMutualisable $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(FicheMatiereMutualisable $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findAllBy(array $options, string|null $q): array
    {
        $qb = $this->createQueryBuilder('f')
            ->join('f.ficheMatiere', 'fm');

        if ($q) {
            $qb->andWhere('fm.libelle LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        $joinedFmParcours = false;
        $joinedFmFormation = false;
        $joinedFmMention = false;

        $joinedFParcours = false;
        $joinedFFormation = false;
        $joinedFMention = false;
        $joinedFComposante = false;

        foreach ($options as $sort => $direction) {
            if ($sort === 'mention') {
                if (!$joinedFmParcours) {
                    $qb->leftJoin(Parcours::class, 'fm_p', 'WITH', 'fm.parcours = fm_p.id');
                    $joinedFmParcours = true;
                }
                if (!$joinedFmFormation) {
                    $qb->leftJoin(Formation::class, 'fm_fo', 'WITH', 'fm_p.formation = fm_fo.id');
                    $joinedFmFormation = true;
                }
                if (!$joinedFmMention) {
                    $qb->leftJoin(Mention::class, 'fm_m', 'WITH', 'fm_fo.mention = fm_m.id');
                    $joinedFmMention = true;
                }
                $qb->addOrderBy(
                    'CASE
                        WHEN fm_fo.mention IS NOT NULL THEN fm_m.libelle
                        WHEN fm_fo.mentionTexte IS NOT NULL THEN fm_fo.mentionTexte
                        ELSE fm_fo.mentionTexte
                        END',
                    $direction
                );
            } elseif ($sort === 'composante') {
                if (!$joinedFParcours) {
                    $qb->leftJoin(Parcours::class, 'f_p', 'WITH', 'f.parcours = f_p.id');
                    $joinedFParcours = true;
                }
                if (!$joinedFFormation) {
                    $qb->innerJoin(Formation::class, 'f_fo', 'WITH', 'f_p.formation = f_fo.id');
                    $joinedFFormation = true;
                }
                if (!$joinedFComposante) {
                    $qb->innerJoin(Composante::class, 'f_co', 'WITH', 'f_fo.composantePorteuse = f_co.id');
                    $joinedFComposante = true;
                }
                $qb->addOrderBy('f_co.libelle', $direction);
            } elseif ($sort === 'mentionmutualisable') {
                if (!$joinedFParcours) {
                    $qb->leftJoin(Parcours::class, 'f_p', 'WITH', 'f.parcours = f_p.id');
                    $joinedFParcours = true;
                }
                if (!$joinedFFormation) {
                    $qb->leftJoin(Formation::class, 'f_fo', 'WITH', 'f_p.formation = f_fo.id');
                    $joinedFFormation = true;
                }
                if (!$joinedFMention) {
                    $qb->leftJoin(Mention::class, 'f_m', 'WITH', 'f_fo.mention = f_m.id');
                    $joinedFMention = true;
                }
                $qb->addOrderBy(
                    'CASE
                        WHEN f_fo.mention IS NOT NULL THEN f_m.libelle
                        WHEN f_fo.mentionTexte IS NOT NULL THEN f_fo.mentionTexte
                        ELSE f_fo.mentionTexte
                        END',
                    $direction
                );
            } elseif ($sort === 'parcours') {
                if (!$joinedFParcours) {
                    $qb->leftJoin(Parcours::class, 'f_p', 'WITH', 'f.parcours = f_p.id');
                    $joinedFParcours = true;
                }
                $qb->addOrderBy('f_p.libelle', $direction);
            } else {
                $qb->addOrderBy('fm.' . $sort, $direction);
            }
        }

        return $qb->getQuery()->getResult();
    }

    public function findByParcours(
        ?UserInterface $user,
        array $options, string|null $q
    ): array {
        //todo: ajouter les bons parcours uniquement...
        return $this->findAllBy($options, $q);
    }

    public function findByFicheMatieres(FicheMatiere $ficheMatiere): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.ficheMatiere = :ficheMatiere')
            ->join('f.parcours', 'p')
            ->addSelect('p')
            ->setParameter('ficheMatiere', $ficheMatiere)
            ->orderBy('p.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findFromAnneeUniversitaire(int $idCampagneCollecte) : array {
        return $this->createQueryBuilder('fmMutu')
            ->select('fmMutu.id')
            ->join('fmMutu.parcours', 'parcours')
            ->join('parcours.dpeParcours', 'dpe')
            ->join('dpe.campagneCollecte', 'campagneC')
            ->andWhere('campagneC.id = :idCampagne')
            ->setParameter(':idCampagne', $idCampagneCollecte)
            ->getQuery()
            ->getResult();
    }
}
