<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Composante;
use App\Entity\FicheMatiere;
use App\Entity\Formation;
use App\Entity\Parcours;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Route;

/**
 * Resolves only parameters for which the test dataset has an unambiguous value.
 * Unknown parameters are deliberately left unresolved and reported as skipped.
 */
final class RouteParameterResolver
{
    private const ENTITY_PARAMETERS = [
        'composante' => Composante::class,
        'formation' => Formation::class,
        'parcours' => Parcours::class,
        'ficheMatiere' => FicheMatiere::class,
        'fiche_matiere' => FicheMatiere::class,
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{parameters: array<string, int|string>, unresolved: list<string>}
     */
    public function resolve(Route $route): array
    {
        $parameters = [];
        $unresolved = [];

        foreach ($route->compile()->getVariables() as $variable) {
            $default = $route->getDefault($variable);
            if (null !== $default && '' !== $default) {
                $parameters[$variable] = $default;
                continue;
            }

            $entityClass = self::ENTITY_PARAMETERS[$variable] ?? null;
            if (null === $entityClass) {
                $unresolved[] = $variable;
                continue;
            }

            $entity = $this->entityManager->getRepository($entityClass)->findOneBy([]);
            if (null === $entity || !method_exists($entity, 'getId') || null === $entity->getId()) {
                $unresolved[] = $variable;
                continue;
            }

            $parameters[$variable] = $entity->getId();
        }

        return [
            'parameters' => $parameters,
            'unresolved' => $unresolved,
        ];
    }
}
