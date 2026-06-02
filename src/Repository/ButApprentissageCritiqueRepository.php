<?php

namespace App\Repository;

use App\Entity\ButApprentissageCritique;
use App\Entity\ButCompetence;
use App\Entity\Semestre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ButApprentissageCritique>
 *
 * @method ButApprentissageCritique|null find($id, $lockMode = null, $lockVersion = null)
 * @method ButApprentissageCritique|null findOneBy(array $criteria, array $orderBy = null)
 * @method ButApprentissageCritique[]    findAll()
 * @method ButApprentissageCritique[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ButApprentissageCritiqueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ButApprentissageCritique::class);
    }

    public function save(ButApprentissageCritique $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ButApprentissageCritique $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByCompetenceSemestre(ButCompetence $competence, Semestre $semestre): array
    {
        if ($semestre->getOrdre() === 1 || $semestre->getOrdre() === 2) {
            $annee = 'BUT1';
        } else  if ($semestre->getOrdre() === 3 || $semestre->getOrdre() === 4) {
            $annee = 'BUT2';
        } else {
            $annee = 'BUT3';
        }

        return $this->createQueryBuilder('ac')
            ->innerJoin('ac.niveau', 'an')
            ->innerJoin('an.competence', 'comp')
            ->where('an.annee = :annee')
            ->andWhere('comp.id = :competence')
            ->setParameter('annee', $annee)
            ->setParameter('competence', $competence->getId())
            ->getQuery()
            ->getResult();
    }

    public function findFromAnneeUniversitaire(int $idCampagneCollecte, array $exclusionEtatDpe) : array {
        $qb = $this->createQueryBuilder('butAppCrit');
        return $qb
            ->select('DISTINCT butAppCrit.id')
            ->join('butAppCrit.niveau', 'butNiveau')
            ->join('butNiveau.competence', 'butCompetence')
            ->join('butCompetence.formation', 'formation')
            ->join('formation.parcours', 'parcours')
            ->join('parcours.dpeParcours', 'dpeP')
            ->join('dpeP.campagneCollecte', 'campagneC')
            ->andWhere('campagneC.id = :idCampagne')
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->isNull('parcours.isSoftDeleted'),
                    $qb->expr()->eq('parcours.isSoftDeleted', 0)
                )
            )
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->isNull('formation.isSoftDeleted'),
                    $qb->expr()->eq('formation.isSoftDeleted', 0)
                )
            )
            ->andWhere(
                $qb->expr()->notIn('dpeP.etatReconduction', ':exclusionEtatDpe')
            )
            ->setParameter(':idCampagne', $idCampagneCollecte)
            ->setParameter(':exclusionEtatDpe', $exclusionEtatDpe)
            ->getQuery()
            ->getResult();
    }
}
