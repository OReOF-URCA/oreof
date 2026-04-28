<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Workflow/StepHandlerRegistry.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 03/10/2025 13:09
 */

// src/Workflow/StepHandlerRegistry.php
namespace App\Workflow;

final class StepHandlerRegistry
{
    /** @var array<string, StepHandlerInterface> keyed by place name */
    public function __construct(private iterable $handlers = [])
    {
    }

    public function get(?string $place): ?StepHandlerInterface
    {
        if (!$place) return null;
        foreach ($this->handlers as $name => $handler) {
            if ($name === $place) return $handler;
        }
        return null;
    }
}
