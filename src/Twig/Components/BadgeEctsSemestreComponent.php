<?php

namespace App\Twig\Components;

use App\Classes\GetUeEcts;
use App\Entity\Parcours;
use App\Entity\Semestre;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

/*
 * <span id="ects_ue_{{ ue.id }}_{{ parcours.id }}">

                                            </span>
 */

#[AsTwigComponent('badge_ects_semestre')]
final class BadgeEctsSemestreComponent
{
    public ?Semestre $semestre = null;
    public ?Parcours $parcours = null;
    public null|float|string $ects = 0.0;
    public bool $hasEcts = true;
    public ?int $nbEctsParSemestre = 30;

    #[PostMount]
    public function mounted(): void
    {
        $typeDiplome = $this->parcours->getTypeDiplome();
        $this->hasEcts = $typeDiplome?->isHasEcts() ?? true;
        $this->nbEctsParSemestre = $typeDiplome?->getNbEctsParSemestre();

        foreach ($this->semestre->getUes() as $ue) {
            if ($ue->getUeParent() === null) {
                $this->ects += GetUeEcts::getEcts($ue, $this->parcours, $typeDiplome);
            }
        }
    }
}
