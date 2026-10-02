<?php

declare(strict_types=1);

namespace App\DTO\Recherche;

final readonly class ResultatFuzzy
{
    /**
     * @param list<string>                                  $champs  champs contenant au moins un terme
     * @param list<array{texte: string, surligne: bool}>    $extrait passage du texte, termes trouvés surlignés
     */
    public function __construct(
        public int|string $id,
        public float $score,
        public array $champs,
        public array $extrait = [],
        public ?string $champExtrait = null,
    ) {
    }
}
