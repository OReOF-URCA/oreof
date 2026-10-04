<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Repository/ComposanteRepository.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 25/02/2023 23:24
 */

namespace App\Repository;

use App\Entity\Composante;
use App\Entity\UserProfil;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<Composante>
 *
 * @method Composante|null find($id, $lockMode = null, $lockVersion = null)
 * @method Composante|null findOneBy(array $criteria, array $orderBy = null)
 * @method Composante[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ComposanteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Composante::class);
    }

    public function findAll(): array
    {
        return $this->findBy([], ['libelle' => 'ASC']);
    }

    public function save(Composante $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Composante $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByCentreGestion(UserInterface $user): array
    {
        $qb = $this->createQueryBuilder('c');
        $qb->innerJoin(UserProfil::class, 'cg', 'WITH', 'c.id = cg.composante');
        $qb->where('cg.user = :user');
        $qb->setParameter('user', $user);

        return $qb->getQuery()->getResult();
    }

    public function findPorteuse(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.inscriptionUniquement = 0')
            ->orWhere('c.inscriptionUniquement IS NULL')
            ->orderBy('c.libelle', 'ASC')
            ->getQuery()->getResult();
    }

    public function findAllId(): array
    {
        $qb = $this->createQueryBuilder('c');
        $qb->select('c.id, c.libelle, c.sigle');
        return $qb->getQuery()->getResult();
    }
}
