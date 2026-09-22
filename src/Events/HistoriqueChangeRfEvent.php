<?php

namespace App\Events;

use App\Entity\ChangeRf;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\EventDispatcher\Event;

final class HistoriqueChangeRfEvent extends Event
{
    public const ADD_HISTORIQUE_CHANGE_RF = 'add.historique.formation.change_rf';

    /** @param array<string, mixed> $input */
    public function __construct(
        private readonly ChangeRf $changeRf,
        private readonly UserInterface $user,
        private readonly string $etape,
        private readonly string $etat,
        private readonly array $input = [],
        private readonly ?string $fileName = null,
        private readonly ?string $originalFileName = null,
    ) {
    }

    public function getChangeRf(): ChangeRf
    {
        return $this->changeRf;
    }

    public function getUser(): UserInterface
    {
        return $this->user;
    }

    public function getEtape(): string
    {
        return $this->etape;
    }

    public function getEtat(): string
    {
        return $this->etat;
    }

    /** @return array<string, mixed> */
    public function getInput(): array
    {
        return $this->input;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function getOriginalFileName(): ?string
    {
        return $this->originalFileName;
    }
}
