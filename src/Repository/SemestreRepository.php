<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Repository/SemestreRepository.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 25/01/2023 20:31
 */

namespace App\Repository;

use App\Entity\Parcours;
use App\Entity\Semestre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Semestre>
 *
 * @method Semestre|null find($id, $lockMode = null, $lockVersion = null)
 * @method Semestre|null findOneBy(array $criteria, array $orderBy = null)
 * @method Semestre[]    findAll()
 * @method Semestre[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SemestreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Semestre::class);
    }

    public function save(Semestre $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Semestre $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByParcoursOrdre(?int $ordreDestination, Parcours $parcours): ?Semestre
    {
        return $this->createQueryBuilder('s')
            ->join('s.semestreParcours', 'sp')
            ->where('sp.parcours = :parcours')
            ->andWhere('s.ordre = :ordre')
            ->setParameter('parcours', $parcours)
            ->setParameter('ordre', $ordreDestination)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countDuplicatesCode() : array {
        return $this->createQueryBuilder('s')
            ->select('count(s.codeApogee)')
            ->where('s.codeApogee IS NOT NULL')
            ->groupBy('s.codeApogee')
            ->having('count(s.codeApogee) > 1')
            ->getQuery()
            ->getResult();
    }

    public function findFromAnneeUniversitaire(int $idCampagneCollecte) : array {
        $qb = $this->createQueryBuilder('semestre');

        $subquerySemestreParcours = $this->createQueryBuilder('sem')
            ->select('sem.id')
            ->join('sem.semestreParcours', 'semP')
            ->join('semP.parcours', 'parcours')
            ->join('parcours.dpeParcours', 'dpe')
            ->join('dpe.campagneCollecte', 'campagne')
            ->andWhere('campagne.id = :idCampagne');

        $subQuerySemestreMutualisable = $this->createQueryBuilder('semM')
            ->select('semM.id')
            ->join('semM.semestreMutualisables', 'semMutu')
            ->join('semMutu.parcours', 'parcoursMutu')
            ->join('parcoursMutu.dpeParcours', 'dpeMutu')
            ->join('dpeMutu.campagneCollecte', 'campagneMutu')
            ->andWhere('campagneMutu.id = :idCampagne');
        
        return $qb
            ->select('DISTINCT semestre.id')
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->in(
                        'semestre.id', $subquerySemestreParcours->getDQL()
                    ),
                    $qb->expr()->in(
                        'semestre.id', $subQuerySemestreMutualisable->getDQL()
                    )
                )
            )
            ->setParameter(':idCampagne', $idCampagneCollecte)
            ->getQuery()
            ->getResult();
    }
}
