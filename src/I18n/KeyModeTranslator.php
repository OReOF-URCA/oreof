<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/I18n/KeyModeTranslator.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 24/10/2025 10:01
 */

namespace App\I18n;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Component\Translation\MessageCatalogueInterface;

final class KeyModeTranslator implements TranslatorInterface, TranslatorBagInterface, LocaleAwareInterface
{
    /** @var TranslatorInterface&TranslatorBagInterface&LocaleAwareInterface */
    private $inner;
    private KeyModeContext $context;
    private LoggerInterface $logger;
    private bool $kernelDebug;

    /**
     * @param TranslatorInterface&TranslatorBagInterface&LocaleAwareInterface $inner
     */
    public function __construct(
        $inner,
        KeyModeContext $context,
        LoggerInterface $logger,
        bool $kernelDebug
    )
    {
        $this->inner = $inner;
        $this->context = $context;
        $this->logger = $logger;
        $this->kernelDebug = $kernelDebug;
    }

    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        // si en mode "key mode" on retourne la clé au rendu immédiatement (on ne veut pas évaluer la traduction)
        if ($this->context->isEnabled()) {
            $label = $id . ($domain ? " ($domain)" : '');
            return "⟦{$label}⟧";
        }

        $translated = $this->inner->trans($id, $parameters, $domain, $locale);

        // journaliser les traductions manquantes uniquement en debug
        if ($this->kernelDebug) {
            // on considère 'manquante' si le résultat est identique à l'id ou vide
            if ($translated === $id || $translated === '') {
                // ne pas appeler debug_backtrace pour éviter warnings d'analyse statique
                $this->logger->warning('Missing translation', [
                    'id' => $id,
                    'domain' => $domain,
                    'locale' => $locale ?? $this->inner->getLocale(),
                ]);
            }
        }

        return $translated;
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
    public function getCatalogue(?string $locale = null): MessageCatalogueInterface
    {
        return $this->inner->getCatalogue($locale);
    }

    public function getCatalogues(): array
    {
        return $this->inner->getCatalogues();
    }
}
