<?php

namespace App\Twig\Components;

use App\Classes\verif\FormationValide;
use App\Entity\Formation;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsLiveComponent('formation_parcours')]
/** @deprecated */
final class FormationParcoursComponent
{
    use DefaultActionTrait;

    #[LiveProp]
    public ?Formation $formation = null;
    public string $allSubmitted = 'btn-muted';
    public string $allValidated = 'btn-muted';

    public function __construct()
    {

    }

    #[PostMount]
    public function postMount(): void
    {
        $formationValide = new FormationValide($this->formation);
        $this->allSubmitted = $formationValide->allSubmitted() ? 'btn-success' : 'btn-info';
        $this->allValidated = $formationValide->allValidated() ? 'btn-success' : 'btn-info';
    }
}
