<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file src/Twig/PageHeaderExtension.php
 * @project oreofv2
 */

declare(strict_types=1);

namespace App\Twig;

use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Résout la clé de traduction du titre/description d'une page sans passer par trans() : les appels ratés
 * seraient sinon collectés par le panneau « Textes de la page » comme des clés manquantes.
 */
final class PageHeaderExtension extends AbstractExtension
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('header_text_key', [$this, 'resolveKey']),
        ];
    }

    /**
     * Ordre : `<route>.<kind>` (domaine `header`), puis `page.<route>.<kind>` (domaine `menu`).
     *
     * @param 'title'|'description' $kind
     *
     * @return array{key: string, domain: string}|null
     */
    public function resolveKey(?string $route, string $kind): ?array
    {
        if (null === $route || '' === $route || !$this->translator instanceof TranslatorBagInterface) {
            return null;
        }

        $catalogue = $this->translator->getCatalogue();
        foreach ([[$route . '.' . $kind, 'header'], ['page.' . $route . '.' . $kind, 'menu']] as [$key, $domain]) {
            if ($catalogue->has($key, $domain)) {
                return ['key' => $key, 'domain' => $domain];
            }
        }

        return null;
    }
}
