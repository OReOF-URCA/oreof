<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Service/McccCompletionCheckerInterface.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 14/10/2025 18:08
 */

namespace App\Service;

interface McccCompletionCheckerInterface
{
    public function setEtatMccc(?string $etatMccc): static;
}
