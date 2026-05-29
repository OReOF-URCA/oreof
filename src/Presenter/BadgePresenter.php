<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Presenter/BadgePresenter.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 21/05/2026 15:20
 */

declare(strict_types=1);

namespace App\Presenter;

use App\DTO\BadgeView;
use App\DTO\DotView;
use App\Enums\BadgeEnumInterface;
use App\Enums\EtatChangeRfEnum;
use App\Enums\EtatDpeEnum;
use App\Enums\TypeModificationDpeEnum;
use App\Enums\ValidationStatusEnum;

final class BadgePresenter
{
    public function fromEnum(?BadgeEnumInterface $value, string $fallbackLabel = 'Non renseigné', string $fallbackVariant = 'danger'): BadgeView
    {
        if ($value === null) {
            return new BadgeView(label: $fallbackLabel, variant: $fallbackVariant);
        }

        return new BadgeView(
            label: $value->getLibelle(),
            variant: $value->getBadgeVariant(),
        );
    }

    public function fromBoolean(?bool $value = false, string $trueLabel = 'Oui', string $falseLabel = 'Non'): BadgeView
    {
        return $value
            ? new BadgeView(label: $trueLabel, variant: 'success')
            : new BadgeView(label: $falseLabel, variant: 'danger');
    }

    public function fromStatus(?string $value): BadgeView
    {
        return match ($value) {
            'finished' => new BadgeView(label: 'Terminé', variant: 'success'),
            'running' => new BadgeView(label: 'En cours', variant: 'warning'),
            'error' => new BadgeView(label: 'Erreur', variant: 'danger'),
            default => new BadgeView(label: 'Inconnu', variant: 'secondary'),
        };
    }

    public function fromText(string $texte, string $variant): BadgeView
    {
        return new BadgeView(label: $texte, variant: $variant);
    }

    public function fromValide(?string $etat): BadgeView
    {
        return match ($etat) {
            'complet' => new BadgeView(label: 'Complet', variant: 'success'),
            'incomplet' => new BadgeView(label: 'Incomplet', variant: 'warning'),
            'incomplet_ects' => new BadgeView(label: 'Incomplet ECTS', variant: 'warning'),
            'erreur' => new BadgeView(label: 'Erreur de saisie', variant: 'danger'),
            'vide' => new BadgeView(label: 'Non complété', variant: 'danger'),
            'non_concerne' => new BadgeView(label: 'Non concerné', variant: 'info'),
            null => new BadgeView(label: 'NULL?', variant: 'warning'),
            default => new BadgeView(label: (string)$etat, variant: 'secondary'),
        };
    }

    public function fromTypeModification(?TypeModificationDpeEnum $typeModificationDpe): BadgeView
    {
        if ($typeModificationDpe === null) {
            return new BadgeView(label: 'Pas de demande', variant: 'success');
        }

        return new BadgeView(
            label: $typeModificationDpe->getLibelle(),
            variant: $typeModificationDpe->getBadgeVariant(),
        );
    }

    public function fromStep(?bool $etatStep): BadgeView
    {
        return $etatStep
            ? new BadgeView(label: 'Complet', variant: 'success')
            : new BadgeView(label: 'Incomplet', variant: 'warning');
    }

    /**
     * @return list<BadgeView>
     */
    public function fromEtatDpeStates(array $etats): array
    {
        $stateValues = $this->normalizeStates($etats);
        if ($stateValues === []) {
            return [new BadgeView(label: 'Initialisé', variant: 'secondary')];
        }

        return array_map(
            static fn(string $etat): BadgeView => new BadgeView(
                label: EtatDpeEnum::from(strtolower($etat))->libelle(),
                variant: EtatDpeEnum::from(strtolower($etat))->getBadgeVariant(),
            ),
            $stateValues,
        );
    }

    /**
     * @return list<string>
     */
    private function normalizeStates(array $states): array
    {
        if ($states === []) {
            return [];
        }

        if (array_is_list($states)) {
            return array_values(array_filter(
                array_map(static fn(mixed $value): string => (string)$value, $states),
                static fn(string $value): bool => $value !== '',
            ));
        }

        return array_values(array_filter(
            array_map(static fn(int|string $key): string => (string)$key, array_keys($states)),
            static fn(string $value): bool => $value !== '',
        ));
    }

    /**
     * @return list<BadgeView>
     */
    public function fromEtatChangeRfStates(array $etats): array
    {
        $stateValues = $this->normalizeStates($etats);
        if ($stateValues === []) {
            return [new BadgeView(label: 'Initialisé', variant: 'secondary')];
        }

        return array_map(
            static fn(string $etat): BadgeView => new BadgeView(
                label: EtatChangeRfEnum::from(strtolower($etat))->libelle(),
                variant: EtatChangeRfEnum::from(strtolower($etat))->getBadgeVariant(),
            ),
            $stateValues,
        );
    }

    public function fromValidationStatusLong(ValidationStatusEnum $status): BadgeView
    {
        return match ($status) {
            ValidationStatusEnum::VALID => new BadgeView(
                label: 'Conforme aux règles',
                variant: 'success',
                icon: 'icon:check:bold',
                soft: false,
                pill: true,
            ),
            ValidationStatusEnum::INVALID => new BadgeView(
                label: 'Non conforme aux règles',
                variant: 'danger',
                icon: 'mdi:close-outline',
                soft: false,
                pill: true,
            ),
            ValidationStatusEnum::INCOMPLETE => new BadgeView(
                label: 'Incomplet',
                variant: 'warning',
                icon: 'mdi:alert-outline',
                soft: false,
                pill: true,
            ),
            ValidationStatusEnum::NA => new BadgeView(
                label: 'Non applicable',
                variant: 'secondary',
                soft: false,
                pill: true,
            ),
        };
    }

    public function fromValidationStatusShort(ValidationStatusEnum $status, string $size = '1.5'): BadgeView
    {
        return match ($status) {
            ValidationStatusEnum::VALID => new BadgeView(
                label: '',
                variant: 'success',
                size: $size,
                soft: false,
                pill: true,
                extraClass: "inline-block w-{$size} h-{$size} rounded-full",
            ),
            ValidationStatusEnum::INVALID => new BadgeView(
                label: '',
                variant: 'danger',
                size: $size,
                soft: false,
                pill: true,
                extraClass: "inline-block w-{$size} h-{$size} rounded-full",
            ),
            ValidationStatusEnum::INCOMPLETE => new BadgeView(
                label: '',
                variant: 'warning',
                size: $size,
                soft: false,
                pill: true,
                extraClass: "inline-block w-{$size} h-{$size} rounded-full",
            ),
            ValidationStatusEnum::NA => new BadgeView(
                label: '',
                variant: 'secondary',
                size: $size,
                soft: false,
                pill: true,
                extraClass: "inline-block w-{$size} h-{$size} rounded-full",
            ),
        };
    }

    /**
     * Retourne un DotView pour le composant Dot (simple rond coloré avec tooltip)
     */
    public function fromValidationStatusDot(ValidationStatusEnum $status, string $size = 'md', string $tooltip = ''): DotView
    {
        $variantMapping = match ($status) {
            ValidationStatusEnum::VALID => 'success',
            ValidationStatusEnum::INVALID => 'danger',
            ValidationStatusEnum::INCOMPLETE => 'warning',
            ValidationStatusEnum::NA => 'secondary',
        };

        $tooltipMapping = match ($status) {
            ValidationStatusEnum::VALID => 'Conforme aux règles',
            ValidationStatusEnum::INVALID => 'Non conforme aux règles',
            ValidationStatusEnum::INCOMPLETE => 'Incomplet',
            ValidationStatusEnum::NA => 'Non applicable',
        };

        return new DotView(
            variant: $variantMapping,
            size: $size,
            tooltip: $tooltip ?: $tooltipMapping,
        );
    }
}
