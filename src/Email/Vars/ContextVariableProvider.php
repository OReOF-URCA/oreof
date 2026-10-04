<?php
declare(strict_types=1);

namespace App\Email\Vars;

use App\Entity\Formation;

final class ContextVariableProvider implements VariableProviderInterface
{
    public function getNamespace(): string
    {
        return 'context';
    }

    public function provide(array $context): array
    {
        $motif = isset($context['motif']) && is_string($context['motif']) && $context['motif'] !== ''
            ? $context['motif']
            : null;

        return [
            'motif' => $motif,
        ];
    }

    public function describe(): array
    {
        return [
            'context.motif' => 'Motif de refus ou de réserves',
        ];
    }

    public function previewDefaults(): array
    {
        return [
            'motif' => 'Exemple d\'un motif de refus ou de réserves',
        ];
    }
}
