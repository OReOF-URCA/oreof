<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/DTO/StructureEc.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 01/10/2023 10:39
 */

namespace App\DTO;

use App\Classes\GetElementConstitutif;
use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\Parcours;
use Doctrine\Common\Collections\Collection;

class StructureEcVersioning
{
    public ElementConstitutif $elementConstitutif;
    public bool $raccroche;
    public array $elementsConstitutifsEnfants = [];
    public HeuresEctsEc $heuresEctsEc;
    public ElementConstitutif|FicheMatiere|null $elementRaccroche = null;
    public array $heuresEctsEcEnfants = [];
    public Collection $mcccs;
    public ?string $typeMccc;
    public ?Collection $bccs;


    public function __construct(ElementConstitutif $elementConstitutif, Parcours $parcours)
    {
        $getElement = new GetElementConstitutif($elementConstitutif, $parcours);
        $this->raccroche = $getElement->isRaccroche();

        // $this->raccroche = GetElementConstitutif::isRaccroche($elementConstitutif, $parcours);
        $this->elementRaccroche = $getElement->getElementConstitutif();

        $this->elementConstitutif = $elementConstitutif;
        $this->heuresEctsEc = new HeuresEctsEc();
        $this->typeMccc = $getElement->getTypeMcccFromFicheMatiere();
        $this->heuresEctsEc->addEc($getElement->getFicheMatiereHeures());
        $this->heuresEctsEc->addEcts($getElement->getFicheMatiereEcts());
        $this->mcccs = $getElement->getMcccsFromFicheMatiereCollection();
        $this->bccs = $getElement->getBccs();
    }

    public function addEcEnfant(?int $idEc, StructureEcVersioning $structureEc): void
    {
        if($idEc !== null){
            $this->elementsConstitutifsEnfants[$idEc] = $structureEc;
        }else {
            $this->elementsConstitutifsEnfants[] = $structureEc;
        }
        $this->heuresEctsEcEnfants[] = $structureEc->getHeuresEctsEc();
        //gérer pour prendre le max des heures et ects sur tous les enfants de l'EC
    }

    public function getHeuresEctsEc(): HeuresEctsEc
    {
        if (count($this->heuresEctsEcEnfants) > 0) {
            // Sélection du "coût" max sur le présentiel, avec TE max indépendant.
            $heuresRetenues = clone $this->heuresEctsEc;
            $teMax = $heuresRetenues->tePres;

            foreach ($this->heuresEctsEcEnfants as $heuresEctsEcEnfant) {
                if ($heuresEctsEcEnfant->sommeEcTotalPres() > $heuresRetenues->sommeEcTotalPres()) {
                    $heuresRetenues = clone $heuresEctsEcEnfant;
                }
                $teMax = max($teMax, $heuresEctsEcEnfant->tePres);
            }

            $heuresRetenues->tePres = $teMax;
            $this->heuresEctsEc = $heuresRetenues;
        }

        return $this->heuresEctsEc;
    }
}
