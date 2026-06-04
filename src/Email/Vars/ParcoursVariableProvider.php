<?php
declare(strict_types=1);

namespace App\Email\Vars;

final class ParcoursVariableProvider implements VariableProviderInterface
{
    public function getNamespace(): string
    {
        return 'parcours';
    }

    public function provide(array $context): array
    {
        $f = $context['parcours'] ?? null;

        $display = null;
        $libelle = null;

        if (is_object($f)) {
            $display = $f->getDisplay() ?? null;
            $libelle = $f->getLibelle() ?? null;
        } elseif (is_array($f)) {
            $display = $f['display'] ?? null;
            $libelle = $f['libelle'] ?? null;
        }

        return [
            'display' => $display ?: null,
            'libelle' => $libelle ?: null,
        ];
    }

    public function describe(): array
    {
        return [
            'parcours.display' => 'Intitulé du parcours + type le cas échéant',
            'parcours.libelle' => 'Libellé du parcours',
        ];
    }

    public function previewDefaults(): array
    {
        return [
            'display' => 'Création numérique (Alternance)',
            'libelle' => 'Création numérique',
        ];
    }
}
