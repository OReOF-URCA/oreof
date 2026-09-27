<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Composante;
use App\Entity\FicheMatiere;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\Semestre;
use App\Entity\SemestreParcours;
use App\Entity\Ue;
use App\Entity\ElementConstitutif;
use App\Entity\DpeParcours;
use App\Entity\ChangeRf;
use App\Entity\TypeDiplome;
use App\Entity\Profil;
use App\Entity\CampagneCollecte;
use App\Entity\AnneeUniversitaire;
use App\Entity\User;
use App\Entity\NatureUeEc;
use App\Entity\TypeEc;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Route;
use Doctrine\Persistence\ManagerRegistry;

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
        'semestre' => Semestre::class,
        'semestreParcours' => SemestreParcours::class,
        'ue' => Ue::class,
        'ec' => ElementConstitutif::class,
        'elementConstitutif' => ElementConstitutif::class,
        'dpeParcours' => DpeParcours::class,
        'changeRf' => ChangeRf::class,
        'changeRF' => ChangeRf::class,
        'typeDiplome' => TypeDiplome::class,
        'profil' => Profil::class,
        'campagneCollecte' => CampagneCollecte::class,
        'campagne' => CampagneCollecte::class,
        'anneeUniversitaire' => AnneeUniversitaire::class,
        'user' => User::class,
        'natureUeEc' => NatureUeEc::class,
        'nature' => NatureUeEc::class,
        'typeEc' => TypeEc::class,
    ];

    /** @var array<class-string, int|string|null> */
    private array $fixtureIdentifiers = [];

    /** @var array<class-string, true> */
    private array $managedEntityClasses = [];

    public function __construct(EntityManagerInterface $entityManager)
    {
        foreach ($entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            $this->managedEntityClasses[$metadata->getName()] = true;
        }

        foreach (array_unique(self::ENTITY_PARAMETERS) as $entityClass) {
            $entity = $entityManager->getRepository($entityClass)->findOneBy([]);
            $this->fixtureIdentifiers[$entityClass] =
                null !== $entity && method_exists($entity, 'getId')
                    ? $entity->getId()
                    : null;
        }
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
            if (null === $entityClass && 'id' === $variable) {
                $entityClass = $this->resolveEntityClassFromController($route);
            }
            if (null === $entityClass) {
                $unresolved[] = $variable;
                continue;
            }

            $identifier = $this->fixtureIdentifiers[$entityClass] ?? null;
            if (null === $identifier) {
                $unresolved[] = $variable;
                continue;
            }

            $parameters[$variable] = $identifier;
        }

        return [
            'parameters' => $parameters,
            'unresolved' => $unresolved,
        ];
    }

    /** @return class-string|null */
    private function resolveEntityClassFromController(Route $route): ?string
    {
        $controller = $route->getDefault('_controller');
        if (!is_string($controller) || !str_contains($controller, '::')) {
            return null;
        }

        [$class, $method] = explode('::', $controller, 2);
        if (!class_exists($class) || !method_exists($class, $method)) {
            return null;
        }

        $candidates = [];
        foreach ((new \ReflectionMethod($class, $method))->getParameters() as $parameter) {
            $type = $parameter->getType();
            if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $typeName = $type->getName();
            if (isset($this->managedEntityClasses[$typeName])) {
                $candidates[$typeName] = true;
            }
        }

        return 1 === count($candidates) ? array_key_first($candidates) : null;
    }
}
