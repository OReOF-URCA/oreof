<?php
declare(strict_types=1);

namespace App\Email\Vars;

use App\Entity\Formation;

final class FormationVariableProvider implements VariableProviderInterface
{
    public function getNamespace(): string
    {
        return 'formation';
    }

    public function provide(array $context): array
    {
        /** @var ?Formation $f */
        $f = $context['formation'] ?? null;

        $display = null;
        $displayLong = null;
        $hasParcours = null;

        if (is_object($f)) {
            $display = $f->getDisplay() ?? null;
            $displayLong = $f->getDisplayLong() ?? null;
            $hasParcours = $f->isHasParcours() ?? null;
        } elseif (is_array($f)) {
            $display = $f['display'] ?? null;
            $displayLong = $f['displayLong'] ?? null;
            $hasParcours = $f['hasParcours'] ?? null;
        }

        return [
            'display' => $display ?: null,
            'displayLong' => $displayLong ?: null,
            'hasParcours' => $hasParcours ?: null,
        ];
    }

    public function describe(): array
    {
        return [
            'formation.display' => 'Intitulé de la formation',
            'formation.displayLong' => 'Type et intitulé de la formation',
            'formation.hasParcours' => 'Indique si la formation possède des parcours associés',
        ];
    }

    public function previewDefaults(): array
    {
        return [
            'display' => 'Métiers du Multimédia et de l\'Internet (MMI)',
            'displayLong' => 'BUT Métiers du Multimédia et de l\'Internet (MMI)',
            'hasParcours' => false,
        ];
    }
}
