<?php

declare(strict_types=1);

namespace App\Tests\Fixtures;

use DateTime;

/**
 * Trait pour générer rapidement des fixtures d'entités métier
 * Évite la répétition dans les tests
 */
trait EntityFixturesTrait
{
    /**
     * Crée un Parcours minimal avec données par défaut
     */
    protected function createMinimalParcours(array $overrides = [])
    {
        $class = 'App\Entity\Parcours';

        $defaults = [
            'libelle' => 'Parcours Test',
            'libelleCourt' => 'PT',
            'actif' => true,
            'dateCreation' => new DateTime(),
        ];

        $data = array_merge($defaults, $overrides);

        return $this->createEntity($class, $data);
    }

    /**
     * Crée une FicheMatiere minimale
     */
    protected function createMinimalFicheMatiere(array $overrides = [])
    {
        $class = 'App\Entity\FicheMatiere';

        $defaults = [
            'libelle' => 'Matière Test',
            'code' => 'MAT001',
            'ects' => 3.0,
            'heuresCm' => 12.0,
            'heuresTd' => 24.0,
            'heuresHybride' => 0.0,
            'heuresDistanciel' => 0.0,
            'heuresExamens' => 2.0,
        ];

        $data = array_merge($defaults, $overrides);

        return $this->createEntity($class, $data);
    }

    /**
     * Crée un DpeParcours minimal
     */
    protected function createMinimalDpeParcours(array $overrides = [])
    {
        $class = 'App\Entity\DpeParcours';

        $defaults = [
            'nom' => 'DPE Test',
            'etat' => 'brouillon',
        ];

        $data = array_merge($defaults, $overrides);

        return $this->createEntity($class, $data);
    }
}
