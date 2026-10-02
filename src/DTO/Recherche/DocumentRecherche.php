<?php

declare(strict_types=1);

namespace App\DTO\Recherche;

/**
 * Document soumis à la recherche approchée : un identifiant et des champs texte (HTML accepté).
 */
final readonly class DocumentRecherche
{
    /**
     * @param array<string, ?string> $champs
     */
    public function __construct(
        public int|string $id,
        public array $champs,
    ) {
    }
}
