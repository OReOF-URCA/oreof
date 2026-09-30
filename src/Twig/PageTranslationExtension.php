<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Twig/PageTranslationExtension.php
 * @author davidannebicque
 * @project oreofv2
 */

namespace App\Twig;

use App\I18n\KeyModeContext;
use App\I18n\PageTranslationCollector;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PageTranslationExtension extends AbstractExtension
{
    public function __construct(
        private readonly PageTranslationCollector $collector,
        private readonly KeyModeContext $keyModeContext
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('page_translations', [$this, 'getPageTranslations']),
            new TwigFunction('page_translations_count', [$this, 'getPageTranslationsCount']),
            new TwigFunction('is_key_mode_enabled', [$this, 'isKeyModeEnabled']),
        ];
    }

    /**
     * @return array<string, array<string, array{value: string, locale: string, filename: string}>>
     */
    public function getPageTranslations(): array
    {
        $result = [];
        $raw = $this->collector->getTranslations();

        foreach ($raw as $domain => $keys) {
            $result[$domain] = [];
            foreach ($keys as $key => $info) {
                $locale = $info['locale'] ?: 'fr';
                $filename = sprintf('%s.%s.yaml', $domain, $locale);
                $result[$domain][$key] = [
                    'value' => $info['value'],
                    'locale' => $locale,
                    'filename' => $filename,
                ];
            }
        }

        return $result;
    }

    public function getPageTranslationsCount(): int
    {
        return $this->collector->getCount();
    }

    public function isKeyModeEnabled(): bool
    {
        return $this->keyModeContext->isEnabled();
    }
}
