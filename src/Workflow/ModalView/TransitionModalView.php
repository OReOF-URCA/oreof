<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Workflow/ModalView/TransitionModalView.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 12/02/2026 18:36
 */


namespace App\Workflow\ModalView;

final class TransitionModalView
{
    /**
     * @param list<array{
     *     level: string,
     *     code: string,
     *     message: string,
     *     path: ?string,
     *     parameters: array<string, mixed>
     * }> $messages
     */
    public function __construct(
        public readonly string $mode, // 'form' | 'report'
        public readonly bool   $canSubmit,
        public readonly array  $messages = [],
    )
    {
    }
}
