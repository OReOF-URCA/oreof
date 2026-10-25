<?php

declare(strict_types=1);

namespace App\TypeDiplome\Synchronisation;

class ButSyncOptions
{
    /**
     * Synchroniser les parcours et la structure des semestres (tronc commun vs parcours).
     */
    public bool $syncParcours = true;

    /**
     * Synchroniser le référentiel de compétences (compétences, niveaux, apprentissages critiques et déclinaisons par parcours).
     */
    public bool $syncCompetences = true;

    /**
     * Synchroniser les ressources et SAÉ pour la maquette (fiches matières, volumes PPN, coefficients, rattachements UE).
     */
    public bool $syncMaquette = true;

    /**
     * Sigle spécifique pour ORéBUT si différent de celui de la formation (ex: 'INFO' pour 'BUT INFO').
     */
    public ?string $sigleDepartement = null;

    /**
     * Codes parcours spécifiques à importer ou activer (vide = tous).
     *
     * @var array<int, string>
     */
    public array $selectedParcoursCodes = [];

    /**
     * Année de version du programme national ORéBUT (2021 ou 2027).
     */
    public int $annee = 2021;

    public function __construct(
        bool $syncParcours = true,
        bool $syncCompetences = true,
        bool $syncMaquette = true,
        ?string $sigleDepartement = null,
        array $selectedParcoursCodes = [],
        int $annee = 2021,
    ) {
        $this->syncParcours = $syncParcours;
        $this->syncCompetences = $syncCompetences;
        $this->syncMaquette = $syncMaquette;
        $this->sigleDepartement = $sigleDepartement;
        $this->selectedParcoursCodes = $selectedParcoursCodes;
        $this->annee = $annee;
    }
}
