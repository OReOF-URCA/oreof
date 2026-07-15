<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base test case pour tous les tests
 * Offre accès au conteneur Symfony et utilitaires courants
 */
abstract class TestCase extends KernelTestCase
{
    protected ContainerInterface $container;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->container = static::getContainer();
    }

    /**
     * Récupère un service du conteneur
     *
     * @template T
     * @param class-string<T> $id
     * @return T
     */
    protected function getService(string $id)
    {
        return $this->container->get($id);
    }

    /**
     * Helper pour créer des entités sans persister
     */
    protected function createEntity(string $class, array $data = [])
    {
        $entity = new $class();
        foreach ($data as $property => $value) {
            $reflection = new \ReflectionClass($entity);
            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);
            $prop->setValue($entity, $value);
        }
        return $entity;
    }
}
