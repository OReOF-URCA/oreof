<?php

namespace App\Repository;

use App\Entity\WorkflowRoleNotificationConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkflowRoleNotificationConfig>
 */
class WorkflowRoleNotificationConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkflowRoleNotificationConfig::class);
    }


    public function getRolesFor(string $workflow, string $place): ?WorkflowRoleNotificationConfig
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.workflowName = :w')->setParameter('w', $workflow)
            ->andWhere('c.placeName = :p')->setParameter('p', $place)
            ->getQuery()->getOneOrNullResult();
    }
}
