<?php

declare(strict_types=1);

namespace App\DataTable\Column;

use Pentiminax\UX\DataTables\Column\ChoiceColumn;

class YesNoBadgeColumn extends ChoiceColumn
{
    public static function new(
        string $name,
        string $title = '',
        string $yesLabel = 'Oui',
        string $noLabel = 'Non',
        string $yesVariant = 'success',
        string $noVariant = 'danger'
    ): static {
        $column = parent::new($name, $title);

        $column->setCustomOption(self::OPTION_CHOICES, [
            '1' => $yesLabel,
            'true' => $yesLabel,
            '0' => $noLabel,
            'false' => $noLabel,
            '' => $noLabel,
        ]);

        /** @var array<string, string> $badges */
        $badges = [
            '1' => $yesVariant,
            'true' => $yesVariant,
            '0' => $noVariant,
            'false' => $noVariant,
            '' => $noVariant,
        ];
        $column->renderAsBadges($badges, $noVariant);

        return $column;
    }
}
