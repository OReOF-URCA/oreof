<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Events/McccUpdateEvent.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 07/03/2025 21:01
 */

namespace App\Events;

use App\Entity\ElementConstitutif;
use App\Entity\Parcours;
use Symfony\Contracts\EventDispatcher\Event;

class McccUpdateEvent extends Event
{
    public const UPDATE_MCCC = 'mccc.update';

    public ElementConstitutif $elementConstitutif;
    public Parcours $parcours;
    public string $newMcccToText = '';
    public string $oldMcccToText = '';
    public string $newEctsToText = '';
    public string $oldEctsToText = '';
    public string $newStructureToText = '';
    public string $oldStructureToText = '';

    public function __construct(
        ElementConstitutif $elementConstitutif,
        Parcours $parcours,
    ) {
        $this->elementConstitutif = $elementConstitutif;
        $this->parcours = $parcours;
    }

    public function setNewMccc(
        string $oldMcccToText,
        string $newMcccToText
    ): void
    {
        $this->oldMcccToText = $oldMcccToText;
        $this->newMcccToText = $newMcccToText;
    }

    public function setNewEcts(
        string $oldEctsToText,
        string $newEctsToText
    ): void
    {
        $this->oldEctsToText = $oldEctsToText;
        $this->newEctsToText = $newEctsToText;
    }

    public function setNewStructure(
        string $oldStructureToText,
        string $newStructureToText
    ): void
    {
        $this->oldStructureToText = $oldStructureToText;
        $this->newStructureToText = $newStructureToText;
    }

    public function getElementConstitutif(): ElementConstitutif
    {
        return $this->elementConstitutif;
    }

    public function getParcours(): Parcours
    {
        return $this->parcours;
    }

    public function getNewMcccToText(): string
    {
        return $this->newMcccToText;
    }

    public function getOldMcccToText(): string
    {
        return $this->oldMcccToText;
    }

    public function hasDiff(): bool
    {
        return $this->oldMcccToText !== $this->newMcccToText || $this->oldEctsToText !== $this->newEctsToText || $this->oldStructureToText !== $this->newStructureToText;
    }
}
