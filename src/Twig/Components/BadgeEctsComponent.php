<?php

namespace App\Twig\Components;

use App\Classes\GetElementConstitutif;
use App\Entity\ElementConstitutif;
use App\Entity\Parcours;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsTwigComponent('badge_ects')]
final class BadgeEctsComponent
{
    public ?ElementConstitutif $elementConstitutif = null;
    public ?Parcours $parcours = null;
    public ?bool $isEctsSpecifique = false;
    public ?bool $texte = false;
    public ?string $etatEcts = 'danger';
    public null|float|string $ects = null;
    public ?bool $isParcoursProprietaire = false;

    #[PostMount]
    public function mounted(): void
    {
        $this->isParcoursProprietaire = $this->elementConstitutif->getFicheMatiere()?->getParcours()?->getId() === $this->parcours->getId();
        $this->isEctsSpecifique = $this->elementConstitutif->isEctsSpecifiques();

        $typeDiplome = $this->parcours->getTypeDiplome();

        if (!($typeDiplome?->isHasEcts() ?? true)) {
            // Cas sans ECTS (DU-DIU, etc.) : badge vert fixe
            $this->etatEcts = 'success';
            $this->ects = 'N/A';
            return;
        }

        $getElement = new GetElementConstitutif($this->elementConstitutif, $this->parcours);
        $this->ects = $getElement->getFicheMatiereEcts();

        if ($this->ects > 0.0 && $this->ects <= 30.0) {
            $this->etatEcts = 'success';
        } elseif ($this->ects === 0.0 && !($typeDiplome?->isEctsObligatoireSurEc() ?? true)) {
            // ECTS non obligatoires sur cet EC : 0 autorisé, badge vert neutre
            $this->etatEcts = 'success';
            $this->ects = '—';
        } else {
            $this->etatEcts = 'danger';
            $this->ects = 'erreur';
        }
    }
}
