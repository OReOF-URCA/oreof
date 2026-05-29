<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/I18n/KeyModeTranslator.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 24/10/2025 10:01
 */

namespace App\I18n;

use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Component\Translation\MessageCatalogueInterface;

final class KeyModeTranslator implements TranslatorInterface, TranslatorBagInterface, LocaleAwareInterface
{
    public function __construct(
        // IMPORTANT : mêmes interfaces que celles exigées par DataCollectorTranslator
        private TranslatorInterface&TranslatorBagInterface&LocaleAwareInterface $inner,
        private KeyModeContext                                                  $context
    )
    {
    }

    public function trans(string $id, array $parameters = [], string $domain = null, string $locale = null): string
    {
        if ($this->context->isEnabled()) {
            $label = $id . ($domain ? " ($domain)" : '');
            return "⟦{$label}⟧";
        }
        return $this->inner->trans($id, $parameters, $domain, $locale);
    }

    // LocaleAwareInterface
    public function setLocale(string $locale): void
    {
        $this->inner->setLocale($locale);
    }

    public function getLocale(): string
    {
        return $this->inner->getLocale();
    }

    // TranslatorBagInterface
    public function getCatalogue(string $locale = null): MessageCatalogueInterface
    {
        return $this->inner->getCatalogue($locale);
    }

    public function getCatalogues(): array
    {
        // TODO: Implement getCatalogues() method.
    }
}
