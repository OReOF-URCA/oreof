<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Twig/Components/RemplissageProgressComponent.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 05/05/2026 10:38
 */

declare(strict_types=1);

namespace App\Twig\Components;

use App\DTO\Remplissage;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsTwigComponent('remplissage_progress', template: 'components/_ui/remplissage_progress.html.twig')]
final class RemplissageProgressComponent
{
    public ?Remplissage $value = null;

    public int $percent = 0;

    public string $label = 'Non complété';

    public string $tone = 'danger';

    #[PostMount]
    public function mount(): void
    {
        // Si un objet Remplissage est fourni, recalculer automatiquement
        if ($this->value !== null) {
            $this->percent = $this->normalizePercent($this->value);

            if ($this->percent <= 10) {
                $this->label = 'Non complété';
                $this->tone = 'danger';
                return;
            }

            if ($this->percent >= 100) {
                $this->percent = 100;
                $this->label = 'Complet';
                $this->tone = 'success';
                return;
            }

            $this->label = '';
            $this->tone = 'info';
        }
        // Sinon (value=null), garder simplement les valeurs fournies en paramètres
    }

    private function normalizePercent(?Remplissage $value): int
    {
        if (null === $value) {
            return 0;
        }

        return max(0, min(100, (int)round($value->calcul())));
    }
}

