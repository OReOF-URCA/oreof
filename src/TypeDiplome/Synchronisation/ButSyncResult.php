<?php

declare(strict_types=1);

namespace App\TypeDiplome\Synchronisation;

class ButSyncResult
{
    public bool $success = true;

    /** @var array<int, string> */
    public array $messages = [];

    /** @var array<int, string> */
    public array $errors = [];

    public int $parcoursCreated = 0;
    public int $parcoursUpdated = 0;

    public int $competencesCount = 0;
    public int $niveauxCount = 0;
    public int $acsCount = 0;

    public int $uesCreated = 0;
    public int $ressourcesCount = 0;
    public int $saesCount = 0;
    public int $ecsCount = 0;

    public function addError(string $error): self
    {
        $this->errors[] = $error;
        $this->success = false;

        return $this;
    }

    public function addMessage(string $message): self
    {
        $this->messages[] = $message;

        return $this;
    }

    public function getSummary(): string
    {
        $parts = [];
        if ($this->parcoursCreated > 0 || $this->parcoursUpdated > 0) {
            $parts[] = sprintf('%d parcours (%d créés, %d mis à jour)', $this->parcoursCreated + $this->parcoursUpdated, $this->parcoursCreated, $this->parcoursUpdated);
        }
        if ($this->competencesCount > 0) {
            $parts[] = sprintf('%d compétences (%d niveaux, %d AC)', $this->competencesCount, $this->niveauxCount, $this->acsCount);
        }
        if ($this->uesCreated > 0) {
            $parts[] = sprintf('%d UEs déclinées', $this->uesCreated);
        }
        if ($this->ressourcesCount > 0) {
            $parts[] = sprintf('%d ressources', $this->ressourcesCount);
        }
        if ($this->saesCount > 0) {
            $parts[] = sprintf('%d SAÉ', $this->saesCount);
        }
        if ($this->ecsCount > 0) {
            $parts[] = sprintf('%d EC rattachés', $this->ecsCount);
        }

        if (empty($parts)) {
            return 'Aucun élément synchronisé.';
        }

        return 'Synchronisation réussie : ' . implode(', ', $parts) . '.';
    }
}
