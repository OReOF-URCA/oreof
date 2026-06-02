<?php

namespace App\Repository;

use App\Entity\ButNiveau;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ButNiveau>
 *
 * @method ButNiveau|null find($id, $lockMode = null, $lockVersion = null)
 * @method ButNiveau|null findOneBy(array $criteria, array $orderBy = null)
 * @method ButNiveau[]    findAll()
 * @method ButNiveau[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ButNiveauxRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ButNiveau::class);
    }

    public function save(ButNiveau $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ButNiveau $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findFromAnneeUniversitaire(int $idCampagneCollecte, array $exclusionEtatDpe) : array {
        $qb = $this->createQueryBuilder('butNiveau');

        $subqueryCheck = $this->createQueryBuilder('butN')
            ->select('butN.id')
            ->join('butN.competence', 'butCompetence')
            ->join('butCompetence.formation', 'formation')
            ->join('formation.parcours', 'parcours')
            ->join('parcours.dpeParcours', 'dpe')
            ->join('dpe.campagneCollecte', 'campagneCollecte')
            ->andWhere('campagneCollecte.id = :idCampagne')
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->isNull('parcours.isSoftDeleted'),
                    $qb->expr()->eq('parcours.isSoftDeleted', 0)
                )
            )
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->isNull('formation.isSoftDeleted'),
                    $qb->expr()->eq('formation.isSoftDeleted', 0)
                )
            )
            ->andWhere(
                $qb->expr()->notIn('dpe.etatReconduction', ':exclusionEtatDpe')
            );

        return $qb
            ->select('DISTINCT butNiveau.id')
            ->andWhere(
                $qb->expr()->in(
                    'butNiveau.id', $subqueryCheck->getDQL()
                )
            )
            ->setParameter(':idCampagne', $idCampagneCollecte)
            ->setParameter(':exclusionEtatDpe', $exclusionEtatDpe)
            ->getQuery()
            ->getResult();
    }

//    /**
//     * @return ButNiveaux[] Returns an array of ButNiveaux objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('b')
//            ->andWhere('b.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('b.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?ButNiveaux
//    {
//        return $this->createQueryBuilder('b')
//            ->andWhere('b.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
