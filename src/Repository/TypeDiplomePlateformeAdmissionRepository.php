<?php

namespace App\Repository;

use App\Entity\CampagneCollecte;
use App\Entity\TypeDiplomePlateformeAdmission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TypeDiplomePlateformeAdmission>
 */
class TypeDiplomePlateformeAdmissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TypeDiplomePlateformeAdmission::class);
    }

    /**
     * @return array<int, list<TypeDiplomePlateformeAdmission>>
     */
    public function findByCampagneIndexedByTypeDiplome(CampagneCollecte $campagne): array
    {
        $results = $this->createQueryBuilder('tpa')
            ->join('tpa.plateforme', 'p')
            ->join('tpa.typeDiplome', 'td')
            ->addSelect('p', 'td')
            ->where('tpa.campagne = :campagne')
            ->setParameter('campagne', $campagne)
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($results as $item) {
            if ($item->getTypeDiplome() !== null) {
                $map[$item->getTypeDiplome()->getId()][] = $item;
            }
        }

        return $map;
    }
}
