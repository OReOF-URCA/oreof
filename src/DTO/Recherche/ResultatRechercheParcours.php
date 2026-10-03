<?php

declare(strict_types=1);

namespace App\DTO\Recherche;

final readonly class ResultatRechercheParcours
{
    /**
     * @param list<string>                               $champs  champs contenant la recherche
     * @param list<array{texte: string, surligne: bool}> $extrait
     */
    public function __construct(
        public int $parcoursId,
        public bool $estParcoursDefaut,
        public ?string $formationSlug,
        public string $titre,
        public ?string $sigle,
        public ?string $typeParcoursLibelle,
        public array $champs,
        public array $extrait,
        public ?string $champExtrait,
        public int $nbFichesMatieres = 0,
    ) {
    }
}
