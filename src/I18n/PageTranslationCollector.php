<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/I18n/PageTranslationCollector.php
 * @author davidannebicque
 * @project oreofv2
 */

namespace App\I18n;

final class PageTranslationCollector
{
    /**
     * @var array<string, array<string, array{value: string, locale: string}>>
     * Structure: [domain => [key => ['value' => string, 'locale' => string]]]
     */
    private array $translations = [];

    public function add(string $domain, string $key, string $value, string $locale = 'fr'): void
    {
        $domain = $domain !== '' ? $domain : 'messages';
        if (!isset($this->translations[$domain])) {
            $this->translations[$domain] = [];
        }

        $this->translations[$domain][$key] = [
            'value' => $value,
            'locale' => $locale !== '' ? $locale : 'fr',
        ];
    }

    /**
     * @return array<string, array<string, array{value: string, locale: string}>>
     */
    public function getTranslations(): array
    {
        return $this->translations;
    }

    public function getCount(): int
    {
        $count = 0;
        foreach ($this->translations as $keys) {
            $count += count($keys);
        }
        return $count;
    }

    public function reset(): void
    {
        $this->translations = [];
    }
}
