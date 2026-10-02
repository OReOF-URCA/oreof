<?php

namespace App\TypeDiplome;

interface McccDisplayInterface
{
    public function getDisplayMccc(array $mcccs, string $typeMccc): array;
}
