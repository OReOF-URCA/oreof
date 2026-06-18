<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Repository/PlateformeAdmissionRepository.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 09/06/2026 22:31
 */

namespace App\Repository;

use App\Entity\PlateformeAdmission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PlateformeAdmissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlateformeAdmission::class);
    }
}
