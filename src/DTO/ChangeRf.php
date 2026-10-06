<?php
/*
 * Copyright (c) 2024. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/DTO/ChangeRf.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 10/05/2024 17:25
 */

namespace App\DTO;

use App\Entity\User;
use App\Enums\TypeRfEnum;
use Symfony\Component\Validator\Constraints as Assert;

class ChangeRf {
    #[Assert\NotNull(message: 'Veuillez sélectionner un (co-)responsable.')]
    private ?User $user = null;

    private ?string $commentaire = '';

    #[Assert\NotNull(message: 'La date de prise de fonction est obligatoire.')]
    #[Assert\Type(type: \DateTimeInterface::class, message: 'La date de prise de fonction n’est pas valide.')]
    private ?\DateTimeInterface $datePriseFonction = null;

    #[Assert\NotNull(message: 'Veuillez sélectionner le type de fonction.')]
    private ?TypeRfEnum $typeRf = TypeRfEnum::RF;


    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user = null): void
    {
        $this->user = $user;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire = ''): void
    {
        $this->commentaire = $commentaire;
    }

    public function getDatePriseFonction(): ?\DateTimeInterface
    {
        return $this->datePriseFonction;
    }

    public function setDatePriseFonction(?\DateTimeInterface $datePriseFonction): void
    {
        $this->datePriseFonction = $datePriseFonction;
    }

    public function getTypeRf(): ?TypeRfEnum
    {
        return $this->typeRf;
    }

    public function setTypeRf(?TypeRfEnum $typeRf = null): void
    {
        $this->typeRf = $typeRf;
    }

    public function getAnneeUniversitaireDebut(): ?int
    {
        if ($this->datePriseFonction === null) {
            return null;
        }

        $month = (int) $this->datePriseFonction->format('n');
        $year = (int) $this->datePriseFonction->format('Y');

        return $month >= 9 ? $year : $year - 1;
    }

    public function getAnneeUniversitaireLibelle(): ?string
    {
        $debut = $this->getAnneeUniversitaireDebut();
        if ($debut === null) {
            return null;
        }

        return sprintf('%d-%d', $debut, $debut + 1);
    }
}

