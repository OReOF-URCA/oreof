<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Events/HistoriqueParcoursEvent.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 16/08/2023 08:41
 */

namespace App\Events;

use App\Entity\Parcours;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

class HistoriqueParcoursEvent extends AbstractHistoriqueEvent
{

    public const ADD_HISTORIQUE_PARCOURS = 'add.historique.parcours';

    private Parcours $parcours;
    private ?string $fileName;
    private ?string $fileNameNote;
    private ?string $originalFileName;
    private ?string $originalFileNameNote;
    /** @var array<string, mixed> */
    private array $input;

    public function __construct(Parcours $parcours, UserInterface $user, string $etape, string $etat, Request $request, ?string $fileName = null, ?string $fileNameNote = null, ?string $originalFileName = null, ?string $originalFileNameNote = null, array $input = [])
    {
        parent::__construct($user, $etape, $etat, $request);

        $this->parcours = $parcours;
        $this->fileName = $fileName;
        $this->fileNameNote = $fileNameNote;
        $this->originalFileName = $originalFileName;
        $this->originalFileNameNote = $originalFileNameNote;
        $this->input = $input;
    }

    public function getParcours(): Parcours
    {
        return $this->parcours;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function getFileNameNote(): ?string
    {
        return $this->fileNameNote;
    }

    public function getOriginalFileName(): ?string
    {
        return $this->originalFileName;
    }

    public function getOriginalFileNameNote(): ?string
    {
        return $this->originalFileNameNote;
    }

    /** @return array<string, mixed> */
    public function getInput(): array
    {
        return $this->input;
    }
}
