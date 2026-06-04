<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Repository/VolumeHoraireParcoursRepository.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 07/04/2026 12:10
 */

namespace App\Repository;

use App\Entity\CampagneCollecte;
use App\Entity\Parcours;
use App\Entity\VolumeHoraireParcours;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VolumeHoraireParcours>
 *
 * @method VolumeHoraireParcours|null find($id, $lockMode = null, $lockVersion = null)
 * @method VolumeHoraireParcours|null findOneBy(array $criteria, array $orderBy = null)
 * @method VolumeHoraireParcours[]    findAll()
 * @method VolumeHoraireParcours[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VolumeHoraireParcoursRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VolumeHoraireParcours::class);
    }

    public function findOneByReferenceParcoursAndCampagne(Parcours $parcours, CampagneCollecte $campagneCollecte): ?VolumeHoraireParcours
    {
        return $this->findOneByParcoursAndCampagne($parcours->getParcoursOrigineCopie() ?? $parcours, $campagneCollecte);
    }

    public function findOneByParcoursAndCampagne(Parcours $parcours, CampagneCollecte $campagneCollecte): ?VolumeHoraireParcours
    {
        return $this->findOneBy([
            'parcours' => $parcours,
            'campagneCollecte' => $campagneCollecte,
        ]);
    }

    /**
     * Retourne un tableau indexé par l'id du parcours pour une campagne donnée.
     * Utile pour un accès rapide lors de l'export.
     *
     * @return array<int, VolumeHoraireParcours>
     */
    public function findIndexedByParcoursForCampagne(CampagneCollecte $campagneCollecte): array
    {
        $results = $this->createQueryBuilder('v')
            ->where('v.campagneCollecte = :campagne')
            ->setParameter('campagne', $campagneCollecte)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($results as $volume) {
            $indexed[$volume->getParcours()?->getId()] = $volume;
        }

        return $indexed;
    }

    /**
     * Trouve la campagne précédente (annee - 1) et retourne les volumes indexés par parcours_id.
     *
     * @return array<int, VolumeHoraireParcours>
     */
    public function findIndexedByParcoursForPreviousCampagne(CampagneCollecte $campagneCollecte): array
    {
        $annee = $campagneCollecte->getAnnee();

        $results = $this->createQueryBuilder('v')
            ->join('v.campagneCollecte', 'c')
            ->where('c.annee = :annee')
            ->setParameter('annee', $annee - 1)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($results as $volume) {
            $indexed[$volume->getParcours()?->getId()] = $volume;
        }

        return $indexed;
    }

    public function save(VolumeHoraireParcours $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(VolumeHoraireParcours $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

