<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/GetAvailableWorkflows.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 12/10/2025 08:20
 */

namespace App\Controller;

use App\Classes\ValidationProcess;
use App\Classes\ValidationProcessChangeRf;
use App\Classes\ValidationProcessFicheMatiere;

class GetAvailableWorkflows
{

    public function __construct(
        private ValidationProcess             $validationProcess,
        private ValidationProcessChangeRf     $validationProcessChangeRf,
        private ValidationProcessFicheMatiere $validationProcessFicheMatiere,
    )
    {
    }

    /**
     * Centralisez vos clés fonctionnelles ici (ou lisez depuis config)
     */
    public function availableWorkflows(): array
    {
        $process = array_keys($this->validationProcess->getTransitionsAll());
        $processRf = array_keys($this->validationProcessChangeRf->getTransitionsAll());
        $processFiches = array_keys($this->validationProcessFicheMatiere->getTransitionsAll());

        return [
            'dpe' => array_combine(
                array_map(fn($v) => 'dpeParcours_' . $v, $process),
                array_map(fn($v) => 'dpeParcours_' . $v, $process)
            ),
            'rf' => array_combine(
                array_map(fn($v) => 'rf_' . $v, $processRf),
                array_map(fn($v) => 'rf_' . $v, $processRf)
            ),
            'fiche' => array_combine(
                array_map(fn($v) => 'fiche_' . $v, $processFiches),
                array_map(fn($v) => 'fiche_' . $v, $processFiches)
            ),
        ];
    }
}
