<?php

declare(strict_types=1);

namespace App\DTO\Comparison;

final class FicheMatiereChangeItem
{
    public const CATEGORY_IDENTITE = 'identite';
    public const CATEGORY_DESCRIPTIFS = 'descriptifs';
    public const CATEGORY_VOLUMES = 'volumes_horaires';
    public const CATEGORY_MCCC = 'mccc';
    public const CATEGORY_COMPETENCES = 'competences';

    public function __construct(
        public readonly string $category,
        public readonly string $field,
        public readonly string $label,
        public readonly string $before,
        public readonly string $after,
        public readonly ?string $htmlDiff = null,
    ) {
    }

    public function getCategoryLabel(): string
    {
        return self::labelForCategory($this->category);
    }

    public static function labelForCategory(string $category): string
    {
        return match ($category) {
            self::CATEGORY_IDENTITE => 'Identité & organisation',
            self::CATEGORY_DESCRIPTIFS => 'Descriptifs pédagogiques',
            self::CATEGORY_VOLUMES => 'Volumes horaires',
            self::CATEGORY_MCCC => 'ECTS & MCCC',
            self::CATEGORY_COMPETENCES => 'Compétences & AC',
            default => 'Autre',
        };
    }
}
