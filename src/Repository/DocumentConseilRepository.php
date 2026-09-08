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

    /**
     * @param int[] $formationIds
     * @return array<int, array{hasPv: bool, pv: ?DocumentConseil, hasNote: bool, note: ?DocumentConseil}>
     */
    public function findIndexedByFormationIds(array $formationIds): array
    {
        if (empty($formationIds)) {
            return [];
        }

        /** @var DocumentConseil[] $docs */
        $docs = $this->createQueryBuilder('d')
            ->innerJoin('d.formations', 'f')
            ->addSelect('f')
            ->where('f.id IN (:ids)')
            ->setParameter('ids', $formationIds)
            ->orderBy('d.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($docs as $doc) {
            foreach ($doc->getFormations() as $f) {
                $fId = $f->getId();
                if ($fId === null || !in_array($fId, $formationIds, true)) {
                    continue;
                }
                if (!isset($result[$fId])) {
                    $result[$fId] = [
                        'hasPv' => false,
                        'pv' => null,
                        'hasNote' => false,
                        'note' => null,
                    ];
                }
                if ($doc->getType() === 'pv' && !$result[$fId]['hasPv']) {
                    $result[$fId]['hasPv'] = true;
                    $result[$fId]['pv'] = $doc;
                } elseif ($doc->getType() === 'note_explicative' && !$result[$fId]['hasNote']) {
                    $result[$fId]['hasNote'] = true;
                    $result[$fId]['note'] = $doc;
                }
            }
        }

        return $result;
    }
}
