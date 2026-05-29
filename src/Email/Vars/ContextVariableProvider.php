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
        $motif = null;

        if (is_array($context)) {
            $motif = $f['motif'] ?? null;
        }

        return [
            'motif' => $motif ?: null,
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
