<?php

declare(strict_types=1);

namespace App\DTO\Recherche;

final readonly class ResultatsRechercheParcours
{
    /**
     * @param list<ResultatRechercheParcours> $resultats                triés par pertinence décroissante
     * @param array<string, string>           $corrections              mot saisi => mot proche trouvé dans les textes
     * @param list<string>                    $termesSansCorrespondance mots saisis sans aucun mot proche
     */
    public function __construct(
        public array $resultats = [],
        public array $corrections = [],
        public array $termesSansCorrespondance = [],
    ) {
    }
}
