<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/DTO/Workflow/ModalFormMetaDto.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 12/02/2026 16:28
 */

namespace App\DTO\Workflow;

class ModalFormMetaDto
{
    /**
     * @param list<FieldMetaDto> $fields
     * @param list<array<string, mixed>> $rules
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string $title,
        public readonly string $submitLabel,
        public readonly string $formId,
        public readonly array  $fields,
        public readonly array  $rules = [],
        public readonly ?string $formType = null,
        public readonly array  $options = [],
    )
    {
    }
}
