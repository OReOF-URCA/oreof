<?php

namespace App\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Ue;

/**
 * @template T of object
 * @extends EntityRepository<T>
 */
class ElementConstitutifCopyRepository extends EntityRepository {

    /**
     * @param class-string<T> $className
     */
    public function __construct(EntityManagerInterface $em, string $className){
        parent::__construct($em, $em->getClassMetadata($className));
    }

    public function getByUe(?Ue $ue): array
    {
        return $this->createQueryBuilder('ec')
            ->leftJoin('ec.ficheMatiere', 'fm')
            ->leftJoin('ec.typeEc', 'te')
            ->addSelect('fm')
            ->addSelect('te')
            ->andWhere('ec.ue = :ue')
            ->setParameter('ue', $ue)
            ->orderBy('ec.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
