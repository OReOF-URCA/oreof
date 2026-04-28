<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Workflow/StepHandlerInterface.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 03/10/2025 13:08
 */

// src/Workflow/StepHandlerInterface.php
namespace App\Workflow;

interface StepHandlerInterface
{
    public function validate(object $subject, array $data): void;

    public function persist(object $subject, array $data): void;
}
