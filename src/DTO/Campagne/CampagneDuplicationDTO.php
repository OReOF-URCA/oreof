<?php

declare(strict_types=1);

namespace App\DTO\Campagne;

use App\Entity\CampagneCollecte;
use DateTimeInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class CampagneDuplicationDTO
{
    #[Assert\NotNull(message: 'Veuillez sélectionner une campagne source.')]
    public ?CampagneCollecte $sourceCampagne = null;

    #[Assert\NotBlank(message: "Le libellé de l'année universitaire est requis.")]
    #[Assert\Regex(pattern: '/^[0-9]{4}-[0-9]{4}$/', message: 'Le format doit être YYYY-YYYY (ex: 2026-2027).')]
    public ?string $libelleAnneeUniversitaire = null;

    #[Assert\NotNull(message: "L'année universitaire de référence est requise.")]
    #[Assert\Range(min: 2020, max: 2050)]
    public ?int $anneeUniversitaire = null;

    #[Assert\NotBlank(message: 'Le libellé de la campagne de collecte est requis.')]
    public ?string $libelleCampagne = null;

    #[Assert\NotNull(message: "L'année de la campagne est requise.")]
    #[Assert\Range(min: 2020, max: 2050)]
    public ?int $anneeCampagne = null;

    #[Assert\Length(max: 1, maxMessage: 'Le code Apogée ne doit comporter qu’un seul caractère.')]
    public ?string $codeApogeeCampagne = '6';

    #[Assert\NotBlank(message: 'Le suffixe de slug est requis.')]
    public ?string $slugSuffix = '-2026';

    public string $couleur = 'primary';

    public bool $setCampagneDefaut = false;

    public ?DateTimeInterface $dateOuvertureDpe = null;

    public ?DateTimeInterface $dateClotureDpe = null;

    public ?DateTimeInterface $dateTransmissionSes = null;

    public ?DateTimeInterface $dateCfvu = null;

    public ?DateTimeInterface $datePublication = null;

    public bool $duplicateCompetences = true;

    public bool $duplicateMutualisations = true;

    public bool $duplicateContacts = true;

    public bool $duplicateMccc = true;

    public bool $duplicateDroits = true;

    public bool $reconductionOuvertsSeulement = false;
}
