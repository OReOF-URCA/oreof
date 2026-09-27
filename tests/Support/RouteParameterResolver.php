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
