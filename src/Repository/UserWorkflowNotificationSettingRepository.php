<?php

namespace App\Repository;

use App\Entity\UserWorkflowNotificationSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserWorkflowNotificationSetting>
 */
class UserWorkflowNotificationSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserWorkflowNotificationSetting::class);
    }
}
