<?php

namespace App\DTO\Assistance;

final readonly class PieceJointeEntrante
{
    public function __construct(
        public string $nom,
        public string $mimeType,
        public string $contenu,
    ) {
    }
}
