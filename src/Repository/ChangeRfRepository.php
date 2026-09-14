<?php

namespace App\Repository;

use App\Entity\CampagneCollecte;
use App\Entity\ChangeRf;
use App\Entity\Composante;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChangeRf>
 *
 * @method ChangeRf|null find($id, $lockMode = null, $lockVersion = null)
 * @method ChangeRf|null findOneBy(array $criteria, array $orderBy = null)
 * @method ChangeRf[]    findAll()
 * @method ChangeRf[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ChangeRfRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChangeRf::class);
    }

    public function findByComposanteTypeValidation(
        Composante $composante,
        CampagneCollecte $campagneCollecte,
        ?string $etat): array
    {
        if ($etat === null || $etat === 'all') {
            $qb = $this->createQueryBuilder('c')
                ->innerJoin('c.formation', 'f')
                ->andWhere('f.composantePorteuse = :composante')
                ->andWhere('c.campagneCollecte = :campagneCollecte')
                ->setParameter('composante', $composante)
                ->setParameter('campagneCollecte', $campagneCollecte)
                ->orderBy('c.dateDemande', 'DESC')
            ;
        } else {
            $qb = $this->createQueryBuilder('c')
                ->innerJoin('c.formation', 'f')
                ->andWhere('c.campagneCollecte = :campagneCollecte')
                ->andWhere('f.composantePorteuse = :composante')
                ->setParameter('composante', $composante)
                ->andWhere("JSON_CONTAINS(c.etatDemande, :typeValidation) = 1")
                ->setParameter('campagneCollecte', $campagneCollecte)
                ->setParameter('typeValidation', json_encode([$etat => 1]))
                ->orderBy('c.dateDemande', 'DESC');
        }

        return $qb->getQuery()->getResult();
    }

    public function findByTypeValidation(?string $etat,
    CampagneCollecte $campagneCollecte): array
    {
        if ($etat === null || $etat === 'all') {
            $qb = $this->createQueryBuilder('c')
                ->innerJoin('c.formation', 'f')
                ->andWhere('c.campagneCollecte = :campagneCollecte')
                ->setParameter('campagneCollecte', $campagneCollecte)
                ->orderBy('c.dateDemande', 'DESC')
            ;
        } else {
            $qb = $this->createQueryBuilder('c')
                ->innerJoin('c.formation', 'f')
                ->andWhere('c.campagneCollecte = :campagneCollecte')
                ->andWhere("JSON_CONTAINS(c.etatDemande, :typeValidation) = 1")
                ->setParameter('typeValidation', json_encode([$etat => 1]))
                ->setParameter('campagneCollecte', $campagneCollecte)
                ->orderBy('c.dateDemande', 'DESC');
        }

        return $qb->getQuery()->getResult();
    }

    public function findByCampagneCollecteForStats(CampagneCollecte $campagne)
    {
        $qb = $this->createQueryBuilder('f')
            ->select('f.etatDemande AS etat, COUNT(f.id) AS nb')
            ->andWhere('f.campagneCollecte = :campagne')
            ->setParameter('campagne', $campagne)
            ->groupBy('etat');

        return $qb->getQuery()->getArrayResult();
    }

    public function findByComposanteAndCampagneForStats(Composante $composante, CampagneCollecte $campagne): array
    {
        $qb = $this->createQueryBuilder('f')
            ->select('f.etatDemande AS etat, COUNT(f.id) AS nb')
            ->innerJoin('f.formation', 'form')
            ->andWhere('form.composantePorteuse = :composante')
            ->andWhere('f.campagneCollecte = :campagne')
            ->setParameter('composante', $composante)
            ->setParameter('campagne', $campagne)
            ->groupBy('etat');

        return $qb->getQuery()->getArrayResult();
    }
}
