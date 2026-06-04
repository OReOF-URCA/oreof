<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Message/GenerationFormationMessage.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 18/06/2025 16:35
 */

namespace App\Message;

class ProcessGenerationJobMessage
{
    public function __construct(private int $generationJobId)
    {
    }

    public function getGenerationJobId(): int
    {
        return $this->generationJobId;
    }
}
