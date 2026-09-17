<?php

namespace App\Message;

readonly class ChangeRfWorkflowNotification
{
    public function __construct(
        private int $changeRfId,
        private string $transition,
        private ?string $motif = null,
        private ?string $date = null,
    ) {
    }

    public function getChangeRfId(): int
    {
        return $this->changeRfId;
    }

    public function getTransition(): string
    {
        return $this->transition;
    }

    public function getMotif(): ?string
    {
        return $this->motif;
    }

    public function getDate(): ?string
    {
        return $this->date;
    }
}
