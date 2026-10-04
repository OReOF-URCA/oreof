<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Entity/Traits/ValidatableTrait.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 22/01/2026 06:57
 */

namespace App\Entity\Traits;

use App\Enums\ValidationStatusEnum;
use Doctrine\ORM\Mapping as ORM;

trait ValidatableTrait
{
    #[ORM\Column(
        type: 'string',
        length: 16,
        nullable: true,
        enumType: ValidationStatusEnum::class
    )]
    private ?ValidationStatusEnum $validationStatus = ValidationStatusEnum::INCOMPLETE;

    #[ORM\Column(type: 'boolean')]
    private bool $validationDirty = true;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $validationUpdatedAt = null;

    public function getValidationStatus(): ?ValidationStatusEnum
    {
        return $this->validationStatus ?? ValidationStatusEnum::INCOMPLETE;
    }

    public function setValidationStatus(ValidationStatusEnum $status): self
    {
        $this->validationStatus = $status;
        return $this;
    }

    public function isValidationDirty(): bool
    {
        return $this->validationDirty;
    }

    public function setValidationDirty(bool $dirty): self
    {
        $this->validationDirty = $dirty;
        return $this;
    }

    public function markValidationDirty(): self
    {
        $this->validationDirty = true;
        $this->validationStatus = ValidationStatusEnum::INCOMPLETE;
        return $this;
    }

    public function getValidationUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->validationUpdatedAt;
    }

    public function setValidationUpdatedAt(?\DateTimeImmutable $at): self
    {
        $this->validationUpdatedAt = $at;
        return $this;
    }

    // Helpers lisibles
    public function isValid(): bool
    {
        return $this->validationStatus === ValidationStatusEnum::VALID;
    }

    public function isInvalid(): bool
    {
        return $this->validationStatus === ValidationStatusEnum::INVALID;
    }

    public function isIncomplete(): bool
    {
        return $this->validationStatus === ValidationStatusEnum::INCOMPLETE;
    }
}
