<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Navigation/MenuItem.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 07/06/2026 15:48
 */

namespace App\Navigation;

final readonly class MenuItem
{
    public function __construct(
        public string              $key,
        public string              $label,
        public ?string             $route = null,
        public array               $routeParams = [],
        public ?string             $icon = null,
        public ?string             $role = null,
        public ?string             $permission = null,
        public mixed               $subject = null,
        public array               $children = [],
        public bool                $showInTopbar = true,
        public bool                $showInOverview = true,
        public ?string             $description = null,
        public MenuDisplayModeEnum $displayMode = MenuDisplayModeEnum::Dropdown,
        public ?string             $column = null,
        public int                 $position = 100
    )
    {
    }

    public static function link(
        string  $key,
        string  $label,
        string  $route,
        array   $routeParams = [],
        ?string $icon = null,
    ): self
    {
        return new self($key, $label, $route, $routeParams, $icon);
    }

    public static function section(
        string  $key,
        string  $label,
        ?string $route = null,
        ?string $icon = null,
        array   $children = [],
    ): self
    {
        return new self($key, $label, $route, [], $icon, children: $children);
    }

    public static function info(
        string  $key,
        string  $label,
        ?string $description = null,
        ?string $icon = null,
    ): self
    {
        return new self(
            key: $key,
            label: $label,
            icon: $icon,
            showInOverview: false,
            description: $description,
        );
    }

    public function withChildren(array $children): self
    {
        return new self(
            key: $this->key,
            label: $this->label,
            route: $this->route,
            routeParams: $this->routeParams,
            icon: $this->icon,
            role: $this->role,
            permission: $this->permission,
            subject: $this->subject,
            children: $children,
            showInTopbar: $this->showInTopbar,
            showInOverview: $this->showInOverview,
            description: $this->description,
            displayMode: $this->displayMode,
            column: $this->column,
            position: $this->position,
        );
    }

    public function withPosition(int $position): self
    {
        return new self(
            key: $this->key,
            label: $this->label,
            route: $this->route,
            routeParams: $this->routeParams,
            icon: $this->icon,
            role: $this->role,
            permission: $this->permission,
            subject: $this->subject,
            children: $this->children,
            showInTopbar: $this->showInTopbar,
            showInOverview: $this->showInOverview,
            description: $this->description,
            displayMode: $this->displayMode,
            column: $this->column,
            position: $position,
        );
    }

    public function requiresRole(string $role): self
    {
        return new self(
            key: $this->key,
            label: $this->label,
            route: $this->route,
            routeParams: $this->routeParams,
            icon: $this->icon,
            role: $role,
            children: $this->children,
            showInTopbar: $this->showInTopbar,
            showInOverview: $this->showInOverview,
            description: $this->description,
            displayMode: $this->displayMode,
            column: $this->column,
            position: $this->position,
        );
    }

    public function requires(string $permission, mixed $subject = null): self
    {
        return new self(
            key: $this->key,
            label: $this->label,
            route: $this->route,
            routeParams: $this->routeParams,
            icon: $this->icon,
            permission: $permission,
            subject: $subject,
            children: $this->children,
            showInTopbar: $this->showInTopbar,
            showInOverview: $this->showInOverview,
            description: $this->description,
        );
    }

    public function asMegaMenu(): self
    {
        return new self(
            key: $this->key,
            label: $this->label,
            route: $this->route,
            routeParams: $this->routeParams,
            icon: $this->icon,
            children: $this->children,
            description: $this->description,
            displayMode: MenuDisplayModeEnum::MegaMenu,
            column: $this->column,
            position: $this->position,
        );
    }

    public function inColumn(string $column): self
    {
        return new self(
            key: $this->key,
            label: $this->label,
            route: $this->route,
            routeParams: $this->routeParams,
            icon: $this->icon,
            children: $this->children,
            description: $this->description,
            displayMode: $this->displayMode,
            column: $column,
        );
    }

    public function description(): string
    {
        return $this->label . '.description';
    }
}
