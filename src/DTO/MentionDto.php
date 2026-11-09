<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/DTO/MentionDto.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 12/05/2025 22:38
 */

namespace App\DTO;

use App\Entity\Accreditation;
use App\Entity\Mention;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO (Data Transfer Object) pour les opérations sur les mentions.
 * Utilisé pour la création et la mise à jour des mentions.
 */
class MentionDto
{
    /**
     * @var string Le libellé de la mention
     */
    #[Assert\NotBlank(message: 'Le libellé ne peut pas être vide')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le libellé doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères'
    )]
    public string $libelle;

    /**
     * @var string|null Le sigle de la mention (optionnel)
     */
    #[Assert\Length(
        max: 20,
        maxMessage: 'Le sigle ne peut pas dépasser {{ limit }} caractères'
    )]
    public ?string $sigle = null;

    /**
     * @var string|null Le code Apogée de la mention
     */
    #[Assert\NotBlank(message: 'Le code Apogée ne peut pas être vide')]
    #[Assert\Length(
        exactly: 1,
        exactMessage: 'Le code Apogée doit contenir exactement {{ limit }} caractère'
    )]
    public ?string $codeApogee = null;

    /**
     * @var Accreditation|null La période d'accréditation associée
     */
    #[Assert\NotNull(message: 'La période d\'accréditation est obligatoire')]
    public ?Accreditation $accreditation = null;

    #[Assert\Count(min: 1, minMessage: 'Au moins un domaine doit être sélectionné')]
    public Collection $domaines;

    /**
     * @var Collection<int, \App\Entity\TypeDiplome> Les types de diplômes possibles
     */
    public Collection $typeDiplomes;

    public function __construct()
    {
        $this->domaines = new ArrayCollection();
        $this->typeDiplomes = new ArrayCollection();
    }

    /**
     * Crée un DTO à partir d'une entité Mention existante.
     *
     * @param Mention $mention L'entité Mention source
     * @return self Le DTO créé
     */
    public static function createFromEntity(Mention $mention): self
    {
        $dto = new self();
        $dto->libelle = $mention->getLibelle() ?? '';
        $dto->sigle = $mention->getSigle();
        $dto->codeApogee = $mention->getCodeApogee();
        $dto->accreditation = $mention->getAccreditation();
        $dto->domaines = $mention->getDomaines();
        $dto->typeDiplomes = $mention->getTypeDiplomes();

        return $dto;
    }

    /**
     * Crée un DTO vide pour une nouvelle mention.
     *
     * @return self Le DTO créé
     */
    public static function createEmpty(): self
    {
        return new self();
    }
}
