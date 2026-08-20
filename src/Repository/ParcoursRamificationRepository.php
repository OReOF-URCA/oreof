<?php

namespace App\Repository;

use App\Entity\ParcoursRamification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ParcoursRamification>
 */
class ParcoursRamificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ParcoursRamification::class);
    }

    public function findAllRamificationsGroups() {
        return $this->createQueryBuilder('pr')
            ->select([
                'pOrigine.id AS parcours_origine_id',
                "CONCAT(
                    pOTd.libelle, 
                    ' - ', 
                    COALESCE(pOfM.libelle, pOf.mentionTexte), 
                    ' - ', 
                    pOrigine.libelle) 
                        AS parcours_origine_libelle_complet",
                'pOrigine.typeParcours AS parcours_origine_type_parcours',
                'pCible.id AS parcours_cible_id',
                "CONCAT(
                    pCTd.libelle, 
                    ' - ', 
                    COALESCE(pCfM.libelle, pCf.mentionTexte), 
                    ' - ', 
                    pCible.libelle) 
                        AS parcours_cible_libelle_complet",
                'pCible.typeParcours AS parcours_cible_type_parcours',
                'typeRamif.code as code_type_ramif'
            ])
            ->join('pr.parcoursCible', 'pCible')
            ->join('pr.parcoursOrigine', 'pOrigine')
            ->join('pOrigine.formation', 'pOf')
            ->join('pOf.typeDiplome', 'pOTd')
            ->leftJoin('pOf.mention', 'pOfM')
            ->join('pCible.formation', 'pCf')
            ->leftJoin('pCf.mention', 'pCfM')
            ->join('pCf.typeDiplome', 'pCTd')
            ->join('pr.typeRamification', 'typeRamif')
            ->groupBy('pOrigine.id, typeRamif.id')
            ->getQuery()->getResult();
    }
}
