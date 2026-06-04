<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Service/Synthese/Dto/SyntheseButton.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 12/03/2026 10:12
 */

declare(strict_types=1);

namespace App\Service\Synthese\Dto;

final class SyntheseButton
{
    /**
     * @param array<string, scalar> $routeParams
     */
    public function __construct(
        private readonly string  $label,
        private readonly string  $route,
        private readonly array   $routeParams = [],
        private readonly string  $classes = 'btn btn-outline-primary d-block mt-1',
        private readonly ?string $icon = null,
    )
    {
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    /**
     * @return array<string, scalar>
     */
    public function getRouteParams(): array
    {
        return $this->routeParams;
    }

    public function getClasses(): string
    {
        return $this->classes;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }
}

