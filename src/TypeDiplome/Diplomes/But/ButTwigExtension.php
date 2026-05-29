<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/TypeDiplome/But/ButTwigExtension.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 07/06/2025 11:51
 */

namespace App\TypeDiplome\Diplomes\But;

use App\DTO\StructureSemestre;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ButTwigExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('getElementsConstitutifs', $this->getElementsConstitutifs(...), ['is_safe' => ['html']]),
            new TwigFunction('getElementsConstitutifsVersioning', $this->getElementsConstitutifsVersioning(...), ['is_safe' => ['html']])
        ];
    }

    public function getElementsConstitutifs(StructureSemestre $semestre): array
    {
        //parcours les UE, pour construire un tableau des EC, avec pour chaque EC, les infos de l'UE et les coefficients sur chacune des UE ou se trouve l'EC
        $elementsConstitutifs = [];
        foreach ($semestre->ues as $ue) {
            foreach ($ue->elementConstitutifs as $ec) {
                if (!isset($elementsConstitutifs[$ec->elementConstitutif->getCode()])) {
                    $elementsConstitutifs[$ec->elementConstitutif->getCode()] = [
                        'ec' => $ec,
                        'ues' => [],
                        'coefficients' => []
                    ];
                }
                $elementsConstitutifs[$ec->elementConstitutif->getCode()]['ues'][$ue->ue->getId()] = $ec->elementConstitutif->getEcts();
                $elementsConstitutifs[$ec->elementConstitutif->getCode()]['coefficients'][$ue->ue->getId()] = $ue->getHeuresEctsUe()->sommeUeEcts;
            }
        }

        // trier les EC par clé (code de l'EC)
        ksort($elementsConstitutifs, SORT_NATURAL);


        return $elementsConstitutifs;
    }

    public function getElementsConstitutifsVersioning(StructureSemestre $semestre) : array {
        $elementsConstitutifs = [];
        foreach ($semestre->ues as $ue) {
            foreach ($ue->elementConstitutifs as $ec){
                if(!isset($elementsConstitutifs[$ec->elementConstitutif->getCode()])){
                    $elementsConstitutifs[$ec->elementConstitutif->getCode()] = [
                        'ec' => $ec,
                        'ues' => [],
                        'coefficients' => []
                    ];
                }
                $elementsConstitutifs[$ec->elementConstitutif->getCode()]['ues'][$ue->ue->getDeserializedId()] = $ec->heuresEctsEc->ects;
                $elementsConstitutifs[$ec->elementConstitutif->getCode()]['coefficients'][$ue->ue->getDeserializedId()] = $ue->heuresEctsUe->sommeUeEcts;
            }
        }

        ksort($elementsConstitutifs);
        return $elementsConstitutifs;
    }
}
