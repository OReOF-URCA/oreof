<?php

namespace App\Repository;

use App\Entity\CampagneCollecte;
use App\Entity\Composante;
use App\Entity\Parcours;
use App\Entity\PlateformeAdmissionParametre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlateformeAdmissionParametre>
 */
class PlateformeAdmissionParametreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlateformeAdmissionParametre::class);
    }

    public function findByParcours(Parcours $parcours): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.parcours = :val')
            ->setParameter('val', $parcours)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, list<PlateformeAdmissionParametre>>
     */
    public function findByCampagneIndexedByAnnee(CampagneCollecte $campagne, ?Composante $composante = null): array
    {
        $qb = $this->createQueryBuilder('pap')
            ->join('pap.plateforme', 'p')
            ->join('pap.annee', 'a')
            ->addSelect('p', 'a')
            ->where('pap.campagne = :campagne')
            ->setParameter('campagne', $campagne);

        if ($composante !== null) {
            $qb->join('a.parcours', 'par')
                ->join('par.formation', 'f')
                ->andWhere('f.composantePorteuse = :composante')
                ->setParameter('composante', $composante);
        }

        $results = $qb->getQuery()->getResult();

        $map = [];
        foreach ($results as $item) {
            if ($item->getAnnee() !== null) {
                $map[$item->getAnnee()->getId()][] = $item;
            }
        }

        return $map;
    }
}
