<?php

namespace App\Twig\Components;

use App\Classes\GetElementConstitutif;
use App\Entity\DpeParcours;
use App\Entity\ElementConstitutif;
use App\Entity\Parcours;
use App\Entity\ParcoursVersioning;
use App\Entity\Ue;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsTwigComponent('badge_mccc')]
final class BadgeMcccComponent
{
    public ?ElementConstitutif $elementConstitutif = null;
    public ?ElementConstitutif $elementConstitutifParent = null;
    public ?Parcours $parcours = null;
    public ?DpeParcours $dpeParcours = null;
    public bool $deplacer = false;
    public bool $editable = false;
    public ?Ue $ue = null;
    public ?bool $etatMcccComplet = false;
    public ?bool $isMcccSpecifiques = false;
    public ?bool $texte = false;
    public ?bool $isParcoursProprietaire = false;

    public ?ParcoursVersioning $parcoursVersioning = null;

    #[PostMount]
    public function mounted(): void
    {
        $this->isParcoursProprietaire = $this->elementConstitutif->getFicheMatiere()?->getParcours()?->getId() === $this->parcours->getId() || $this->elementConstitutif->getNatureUeEc()?->isChoix() || $this->elementConstitutif->getNatureUeEc()?->isLibre();

        $this->isMcccSpecifiques = $this->elementConstitutif->isMcccSpecifiques();
        if ($this->elementConstitutif !== null) {
            $typeDiplome = $this->parcours->getTypeDiplome();
            $hasEcts = $typeDiplome?->isHasEcts() ?? true;
            $ectsObligatoire = $typeDiplome?->isEctsObligatoireSurEc() ?? true;

            if ($this->elementConstitutif->getNatureUeEc()?->isLibre()) {
                if (!$hasEcts || !$ectsObligatoire) {
                    // ECTS non utilisés ou non obligatoires : un EC libre est complet par défaut
                    $this->etatMcccComplet = true;
                } else {
                    $this->etatMcccComplet = $this->elementConstitutif->getEcts() !== 0; // sur un EC libre, juste des ECTS
                }
            } else {
                $getElement = new GetElementConstitutif($this->elementConstitutif, $this->parcours);
                $this->etatMcccComplet = $this->elementConstitutif->isControleAssiduite() === true || $getElement->getEtatsMccc() === 'Complet';
            }
        }
    }
}
