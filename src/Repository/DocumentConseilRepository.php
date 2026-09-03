<?php

namespace App\Repository;

use App\Entity\DocumentConseil;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentConseil>
 *
 * @method DocumentConseil|null find($id, $lockMode = null, $lockVersion = null)
 * @method DocumentConseil|null findOneBy(array $criteria, array $orderBy = null)
 * @method DocumentConseil[]    findAll()
 * @method DocumentConseil[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DocumentConseilRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentConseil::class);
    }
}
