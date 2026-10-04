<?php

namespace App\Twig\Components;

use App\Entity\DpeParcours;
use App\Entity\ElementConstitutif;
use App\Entity\Parcours;
use App\Entity\Ue;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsTwigComponent('badge_heures')]
/** @deprecated */
final class BadgeHeuresComponent
{
    public ?ElementConstitutif $elementConstitutif = null;
    public ?Parcours $parcours = null;
    public ?DpeParcours $dpeParcours = null;
    public bool $deplacer = false;
    public bool $editable = false;
    public ?Ue $ue = null;
    public ?bool $etatHeuresComplet = false;
    public ?bool $isHeuresSpecifiques = false;
    public ?bool $isParcoursProprietaire = false;

    public ?bool $texte = false;
    public bool $ecDesEnfants = false;

    #[PostMount]
    public function mounted(): void
    {
        $this->isParcoursProprietaire = $this->elementConstitutif?->getFicheMatiere()?->getParcours()?->getId() === $this->parcours?->getId() || $this->elementConstitutif?->getNatureUeEc()?->isChoix() || $this->elementConstitutif?->getNatureUeEc()?->isLibre();
        $this->isHeuresSpecifiques = $this->elementConstitutif?->isHeuresSpecifiques();
        if ($this->elementConstitutif?->isHeuresSpecifiques() === true) {
            $this->etatHeuresComplet = $this->elementConstitutif->etatStructure() === 'Complet';
        } elseif ($this->elementConstitutif?->getFicheMatiere() !== null) {
            $this->etatHeuresComplet = $this->elementConstitutif->getFicheMatiere()->etatStructure() === 'Complet';
        } elseif ($this->elementConstitutif !== null) {
            $this->etatHeuresComplet = $this->elementConstitutif->etatStructure() === 'Complet';
        }

        if ($this->elementConstitutif?->getEcParent() !== null && $this->elementConstitutif->getEcParent()->isHeuresEnfantsIdentiques() === true) {
            $this->editable = false;
            if ($this->elementConstitutif->getEcParent()->getFicheMatiere() !== null) {
                $this->etatHeuresComplet = $this->elementConstitutif->getEcParent()->getFicheMatiere()->etatStructure() === 'Complet';
            } else {
                $this->etatHeuresComplet = $this->elementConstitutif->getEcParent()->etatStructure() === 'Complet';
            }
        }

        if ($this->elementConstitutif->getEcParent() === null && $this->elementConstitutif->getNatureUeEc()?->isChoix() && $this->elementConstitutif->isHeuresEnfantsIdentiques() === false) {
            $this->etatHeuresComplet = true;
            $this->ecDesEnfants = true;
        }
    }
}
