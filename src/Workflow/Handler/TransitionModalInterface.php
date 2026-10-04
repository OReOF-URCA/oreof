<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Workflow/Handler/TransitionModalInterface.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 12/02/2026 18:19
 */

namespace App\Workflow\Handler;

use App\Entity\DpeParcours;
use App\Workflow\ModalView\TransitionModalView;

interface TransitionModalInterface
{
    public function supports(string $code): bool;

    /**
     * Prépare le contenu de la modal : validations, texte, données à afficher, etc.
     */
    public function buildModal(DpeParcours $dpeParcours, array $meta): TransitionModalView;
}
