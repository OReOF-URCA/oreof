<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class ParcoursDuplicationConfig
{
    #[Assert\NotBlank(message: 'Le libellé du nouveau parcours est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le libellé doit comporter au moins {{ limit }} caractères.',
        maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères.'
    )]
    public string $nouveauLibelle = '';

    #[Assert\Length(
        max: 15,
        maxMessage: 'Le sigle ne peut pas dépasser {{ limit }} caractères.'
    )]
    public ?string $nouveauSigle = null;

    public bool $dupliquerContacts = true;

    public bool $dupliquerFichesMatieres = true;

    public bool $conserverResponsables = true;
}
