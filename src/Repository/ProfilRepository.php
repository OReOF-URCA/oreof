<?php

namespace App\Repository;

use App\Entity\Profil;
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

    public function findByPermission(string $attribute): array
    {
        $t = [];
        $all = $this->findAll();
        foreach ($all as $role) {
            if (in_array($attribute, $role->getDroits(), true)) {
                $t[] = $role->getCodeRole();
            }
        }

        return array_unique($t);
    }

    public function findByAll(): array
    {
        return $this->findBy(['porte' => 'All'], ['libelle' => 'ASC']);
    }

    public function findByDpe(): array
    {
        return $this->findBy(['onlyAdmin' => false], ['libelle' => 'ASC']);
    }

    public function findByCentre(string $centre): array
    {
        return $this->findBy(['centre' => $centre], ['libelle' => 'ASC']);
    }

    public function findByCentreDpe(string $centre): array
    {
        return $this->findBy(['centre' => $centre, 'onlyAdmin' => false], ['libelle' => 'ASC']);
    }
}
