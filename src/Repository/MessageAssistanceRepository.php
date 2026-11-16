<?php

namespace App\Repository;

use App\Entity\MessageAssistance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MessageAssistance>
 */
class MessageAssistanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageAssistance::class);
    }

    /**
     * Premier message connu parmi une liste d'identifiants de mail (In-Reply-To, References).
     *
     * @param array<string|null> $ids
     */
    public function findOneByMessageIds(array $ids, ?bool $annonce = null): ?MessageAssistance
    {
        $ids = array_values(array_unique(array_filter(array_map(MessageAssistance::normaliserId(...), $ids))));
        if ($ids === []) {
            return null;
        }

        $qb = $this->createQueryBuilder('m')
            ->where('m.messageId IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('m.dateEnvoi', 'DESC')
            ->setMaxResults(1);

        if ($annonce !== null) {
            $qb->join('m.demande', 'd')->andWhere('d.annonce = :annonce')->setParameter('annonce', $annonce);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function existe(string $messageId): bool
    {
        return $this->count(['messageId' => MessageAssistance::normaliserId($messageId)]) > 0;
    }
}
