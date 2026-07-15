<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Trait pour les tests d'intégration qui utilisent la base de données
 * Gère les transactions et le nettoyage
 */
trait DatabaseTrait
{
    protected EntityManagerInterface $entityManager;

    protected function setUpDatabase(): void
    {
        $this->entityManager = $this->getService(EntityManagerInterface::class);
        $this->entityManager->beginTransaction();
    }

    protected function tearDownDatabase(): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }
        $this->entityManager->close();
    }

    /**
     * Persiste plusieurs entités
     */
    protected function persistAll(array $entities): void
    {
        foreach ($entities as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();
    }

    /**
     * Persiste et flush une entité
     */
    protected function persist($entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * Recharge une entité depuis la DB
     */
    protected function refresh($entity): void
    {
        $this->entityManager->refresh($entity);
    }

    /**
     * Vide le cache d'identité
     */
    protected function clearEntityManager(): void
    {
        $this->entityManager->clear();
    }
}
