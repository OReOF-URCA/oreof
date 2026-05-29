<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Repository/ProfilDroitsRepository.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 26/05/2025 16:32
 */

namespace App\Repository;

use App\Entity\Profil;
use App\Entity\ProfilDroits;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProfilDroits>
 */
class ProfilDroitsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProfilDroits::class);
    }

    public function hasDroit(?Profil $profile, string $attribute, mixed $route): bool
    {
        if ($profile === null) {
            return false;
        }

        $query = $this->createQueryBuilder('pd')
            ->where('pd.profil = :profil')
            ->andWhere('pd.permission = :droit')
            ->andWhere('pd.ressource = :route')
            ->setParameter('profil', $profile)
            ->setParameter('droit', strtoupper($attribute))
            ->setParameter('route', $route)
            ->getQuery();

        return (bool)$query->getOneOrNullResult();
    }
}
