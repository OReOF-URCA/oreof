<?php

declare(strict_types=1);

namespace App\DTO\Recherche;

final readonly class ResultatsFuzzy
{
    /**
     * @param list<ResultatFuzzy>   $resultats   triés par pertinence décroissante
     * @param array<string, string> $corrections terme saisi (normalisé) => mot proche trouvé dans les textes
     * @param list<string>          $termesSansCorrespondance
     */
    public function __construct(
        public array $resultats = [],
        public array $corrections = [],
        public array $termesSansCorrespondance = [],
    ) {
    }

    /**
     * @return list<int|string>
     */
    public function ids(): array
    {
        return array_map(static fn (ResultatFuzzy $resultat): int|string => $resultat->id, $this->resultats);
    }
}
