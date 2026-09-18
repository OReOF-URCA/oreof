<?php

namespace App\Repository;

use App\Entity\CampagneCollecte;
use App\Entity\Composante;
use App\Entity\DpeDemande;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Enums\EtatDpeEnum;
use App\Enums\TypeModificationDpeEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DpeDemande>
 *
 * @method DpeDemande|null find($id, $lockMode = null, $lockVersion = null)
 * @method DpeDemande|null findOneBy(array $criteria, array $orderBy = null)
 * @method DpeDemande[]    findAll()
 * @method DpeDemande[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DpeDemandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DpeDemande::class);
    }

    public function findLastOpenedDemande(Parcours $parcours, EtatDpeEnum $etat, ?TypeModificationDpeEnum $typeModificationDpeEnum = null): ?DpeDemande
    {
        $query = $this->createQueryBuilder('d')
            ->where('d.parcours = :parcours')
            ->andWhere('d.etatDemande = :etat')
            ->andWhere('d.dateCloture IS NULL')
            ->setParameter('parcours', $parcours)
            ->setParameter('etat', $etat);

        if (null !== $typeModificationDpeEnum) {
            $query->andWhere('d.niveauModification = :typeModificationDpeEnum')
                ->setParameter('typeModificationDpeEnum', $typeModificationDpeEnum);
        }

        return $query->getQuery()
            ->getOneOrNullResult();
    }

    public function findLastUnclosedDemande(Parcours $parcours): ?DpeDemande
    {
        return $this->createQueryBuilder('d')
            ->where('d.parcours = :parcours')
            ->andWhere('d.dateCloture IS NULL')
            ->setParameter('parcours', $parcours)
            ->orderBy('d.updated', 'DESC')
            ->addOrderBy('d.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLastOpenedDemandeMention(Formation $formation, EtatDpeEnum $etat): ?DpeDemande
    {
        return $this->createQueryBuilder('d')
            ->where('d.formation = :formation')
            ->andWhere('d.parcours IS NULL')
            ->andWhere('d.etatDemande = :etat')
            ->andWhere('d.dateCloture IS NULL')
            ->setParameter('formation', $formation)
            ->setParameter('etat', $etat)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByComposante(Composante $composante): array
    {
        return $this->createQueryBuilder('d')
            ->innerJoin('d.parcours', 'p')
            ->innerJoin('p.formation', 'f')
            ->where('f.composantePorteuse = :composante')
            ->setParameter('composante', $composante)
            ->getQuery()
            ->getResult();
    }

    public function findByCampagneWithModification(CampagneCollecte $campagneCollecte): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.parcours', 'p')
            ->innerJoin('p.formation', 'f')
            ->where('d.campagneCollecte = :campagne')
            ->andWhere('d.niveauModification IS NOT NULL')
            ->setParameter('campagne', $campagneCollecte)
            ->getQuery()
            ->getResult();
    }

    public function findParcoursByComposante(CampagneCollecte $campagneCollecte, Composante $composante): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.parcours', 'p')
            ->innerJoin('p.formation', 'f')
            ->where('d.campagneCollecte = :campagne')
            ->addSelect('f')
            ->addSelect('p')
            ->where('d.campagneCollecte = :campagne')
            ->andWhere('f.composantePorteuse = :composante')
            ->andWhere('d.niveauModification IS NOT NULL')
            ->setParameter('campagne', $campagneCollecte)
            ->setParameter('composante', $composante)
            ->getQuery()
            ->getResult();
    }

    public function findByComposanteAndSearch(Composante $composante, CampagneCollecte $campagneCollecte, array $params): ?array
    {
        $query = $this->createQueryBuilder('d')
            ->leftJoin('d.parcours', 'p')
            ->leftJoin('p.formation', 'f')
            ->andWhere('f.composantePorteuse = :composante')
            ->setParameter('composante', $composante)
            ->andWhere('d.campagneCollecte = :campagne')
            ->setParameter('campagne', $campagneCollecte);

        if (isset($params['composantePorteuse']) && $params['composantePorteuse'] !== '') {
            $query->andWhere('f.composantePorteuse = :composante')
                ->setParameter('composante', $params['composantePorteuse']);
        }

        if (isset($params['mention']) && $params['mention'] !== '') {
            $query->andWhere('f.mention = :mention')
                ->setParameter('mention', $params['mention']);
        }

        if (isset($params['niveauModification']) && $params['niveauModification'] !== '') {
            $query->andWhere('d.niveauModification = :niveauModification')
                ->setParameter('niveauModification', $params['niveauModification']);
        }


        $data = $query->getQuery()
            ->getResult();

        if (isset($params['etatValidation']) && $params['etatValidation'] !== '') {
            $etatValidation = EtatDpeEnum::tryFrom($params['etatValidation']);
            if ($etatValidation !== null) {
                return array_filter($data, function (DpeDemande $demande) use ($etatValidation) {
                    return $demande->getEtatDemande() === $etatValidation;
                });
            }

            return [];
        }

        return $data;
    }

    public function findBySearch(CampagneCollecte $campagneCollecte, array $params): ?array
    {
        $query = $this->createQueryBuilder('d')
            ->leftJoin('d.parcours', 'p')
            ->leftJoin('p.formation', 'f')
            ->where('d.campagneCollecte = :campagne')
            ->setParameter('campagne', $campagneCollecte);

        if (isset($params['composantePorteuse']) && $params['composantePorteuse'] !== '') {
            $query->andWhere('f.composantePorteuse = :composante')
                ->setParameter('composante', $params['composantePorteuse']);
        }

        if (isset($params['mention']) && $params['mention'] !== '') {
            $query->andWhere('f.mention = :mention')
                ->setParameter('mention', $params['mention']);
        }

        if (isset($params['niveauModification']) && $params['niveauModification'] !== '') {
            $query->andWhere('d.niveauModification = :niveauModification')
                ->setParameter('niveauModification', $params['niveauModification']);
        }


        $data = $query->getQuery()
            ->getResult();

        if (isset($params['etatValidation']) && $params['etatValidation'] !== '') {
            $etatValidation = EtatDpeEnum::tryFrom($params['etatValidation']);
            if ($etatValidation !== null) {
                return array_filter($data, function (DpeDemande $demande) use ($etatValidation) {
                    return $demande->etatValidation() === $etatValidation;
                });
            }

            return [];
        }

        return $data;
    }
}
