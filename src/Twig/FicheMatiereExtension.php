<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Twig/AppExtension.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Twig;

use App\Classes\GetElementConstitutif;
use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\Parcours;
use App\Entity\Ue;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Class AppExtension.
 */
class FicheMatiereExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('hasParcours', $this->hasParcours(...)),
        ];
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('getElementFromEc', $this->getElementFromEc(...)),
            new TwigFunction('sortUeByOrder', $this->sortUeByOrder(...))
        ];
    }

    public function hasParcours(FicheMatiere $ficheMatiere, Parcours $parcours): bool
    {
       foreach($ficheMatiere->getElementConstitutifs() as $elementConstitutif) {
           if($elementConstitutif->getParcours() === $parcours) {
               return true;
           }
       }

       return false;
    }

    public function getElementFromEc(ElementConstitutif $ec, Parcours $p) {
        return new GetElementConstitutif($ec, $p);
    }

    public function sortUeByOrder(Ue $a, Ue $b) {
        return $this->getNumericOrderForUe($a) <=> $this->getNumericOrderForUe($b);
    }

    private function getNumericOrderForUe(Ue $ue) {
        // Deux niveaux d'UE Parents
        if($ue->getUeParent()?->getUeParent() !== null) {
            return ($ue->getUeParent()->getUeParent()->getOrdre() * 100)
                + ($ue->getUeParent()->getOrdre() * 10)
                + $ue->getOrdre();
        }

        // Cas Classique
        return $ue->getUeParent() !== null 
            ? ($ue->getUeParent()->getOrdre() * 100) + $ue->getOrdre()
            : $ue->getOrdre() * 100;
    }
}
