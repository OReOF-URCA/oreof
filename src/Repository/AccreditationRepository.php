<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Accreditation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Accreditation>
 *
 * @method Accreditation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Accreditation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Accreditation[]    findAll()
 * @method Accreditation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AccreditationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Accreditation::class);
    }

    public function findActive(): ?Accreditation
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.actif = :actif')
            ->setParameter('actif', true)
            ->orderBy('a.anneeDebut', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function save(Accreditation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Accreditation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

