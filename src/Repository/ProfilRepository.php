<?php

namespace App\Repository;

use App\Entity\Profil;
use App\Enums\CentreGestionEnum;
use App\Enums\PermissionEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Profil>
 */
class ProfilRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Profil::class);
    }

    public function deleteAll(): void
    {
        $this->getEntityManager()->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $this->getEntityManager()->createQuery('DELETE FROM App\Entity\Profil')->execute();
        $this->getEntityManager()->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function findByPermission(PermissionEnum|string $permission): array
    {
        $permissionEnum = is_string($permission) ? PermissionEnum::tryFrom($permission) ?? $permission : $permission;
        return $this->createQueryBuilder('p')
            ->join('p.profilDroits', 'pd')
            ->andWhere('pd.permission = :permission')
            ->setParameter('permission', $permissionEnum)
            ->select('DISTINCT p.code')
            ->getQuery()
            ->getSingleColumnResult();
    }

    public function findByAll(): array
    {
        return $this->findBy([], ['libelle' => 'ASC']);
    }

    public function findByDpe(): array
    {
        return $this->findBy(['onlyAdmin' => false], ['libelle' => 'ASC']);
    }

    public function findByCentre(CentreGestionEnum|string $centre): array
    {
        $centreEnum = is_string($centre) ? CentreGestionEnum::tryFrom($centre) ?? $centre : $centre;
        return $this->findBy(['centre' => $centreEnum], ['libelle' => 'ASC']);
    }

    public function findByCentreDpe(CentreGestionEnum|string $centre): array
    {
        $centreEnum = is_string($centre) ? CentreGestionEnum::tryFrom($centre) ?? $centre : $centre;
        return $this->findBy(['centre' => $centreEnum, 'onlyAdmin' => false], ['libelle' => 'ASC']);
    }
}
