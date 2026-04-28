<?php
// src/Domain/Mccc/McccCompletionChecker.php
namespace App\Service;

use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\TypeDiplome\TypeDiplomeResolver;

final class McccCompletionChecker
{
    public function __construct(
        private TypeDiplomeResolver $typeDiplomeResolver)
    {
    }

    /**
     * Calcule, pour un owner, la somme des poids par type et retourne true si
     * chaque type présent totalise exactement 100.0.
     *
     * Adapte ici si ta règle métier diffère (ex: seulement certains types requis).
     */
    public function isCompletedForOwner(FicheMatiere|ElementConstitutif $owner): bool
    {
        if ($owner instanceof FicheMatiere && $owner->isHorsDiplome()) {
            return true;
        }
        if ($owner instanceof FicheMatiere) {
            $parcours = $owner->getParcours();
        } elseif ($owner instanceof ElementConstitutif) {
            $parcours = $owner->getParcours();
        } else {
            throw new \InvalidArgumentException('Invalid owner type');
        }

        $typeD = $this->typeDiplomeResolver->fromParcours($parcours);

        if ($typeD === null) {
            return false;
        }

        return $typeD->checkIfMcccValide($owner);
    }
}
