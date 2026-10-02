<?php

namespace App\Repository;

use App\Entity\Parcours;
use App\Entity\ParcoursVersioning;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ParcoursVersioning>
 *
 * @method ParcoursVersioning|null find($id, $lockMode = null, $lockVersion = null)
 * @method ParcoursVersioning|null findOneBy(array $criteria, array $orderBy = null)
 * @method ParcoursVersioning[]    findAll()
 * @method ParcoursVersioning[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ParcoursVersioningRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ParcoursVersioning::class);
    }


    /**
     * @return list<ParcoursVersioning>
     */
    public function findLastVersion(Parcours $parcours): array
    {
        return $this->createQueryBuilder('pv')
            ->orderBy('pv.version_timestamp', 'DESC')
            ->where('pv.parcours = :parcours')
            ->setParameter('parcours', $parcours)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<ParcoursVersioning>
     */
    public function findLastCfvuVersion(Parcours $parcours): array
    {
        return $this->createQueryBuilder('pv')
            ->orderBy('pv.version_timestamp', 'DESC')
            ->join('pv.parcours', 'p', 'WITH', 'pv.parcours = :parcours')
            ->where('pv.cvfuFlag = 1')
            ->setParameter('parcours', $parcours)
            ->getQuery()
            ->getResult();
    }

    public function countSavedParcours(){
        return $this->createQueryBuilder('pv')
            ->select("count(DISTINCT (pv.parcours)) AS nb_parcours")
            ->getQuery()->getResult();
    }

//    /**
//     * @return ParcoursVersioning[] Returns an array of ParcoursVersioning objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?ParcoursVersioning
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
