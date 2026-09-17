<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/DTO/Workflow/WorkflowTransitionMetaDto.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 12/02/2026 16:27
 */

namespace App\DTO\Workflow;

use App\DTO\Workflow\ModalFormMetaDto;

final class WorkflowTransitionMetaDto
{
    /**
     * @param list<string> $recipients
     */
    public function __construct(
        public readonly string            $label,
        public readonly ?string           $description,
        public readonly ?string           $buttonClass,
        public readonly ?string           $buttonIcon,
        public readonly ?string           $type,
        public readonly array             $recipients,

        public readonly ?string           $handlerCode,
        public readonly ?ModalFormMetaDto $form,
        public readonly ?string           $viewTemplate,
        public readonly ?string           $validationStep,
    )
    {
    }
}

