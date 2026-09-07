<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @project oreofv2
 */

declare(strict_types=1);

namespace App\Twig\Components\UI;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Carte de téléchargement d'une ressource (document, tableur, vidéo, lien…).
 *
 *   <twig:DownloadCard
 *       title="Fiche EC / matière"
 *       description="Aide à la rédaction v2 - fiche ec matière 2024-2028.docx"
 *       href="{{ asset('docs/aide-fiche-ec.docx') }}"
 *       type="word" />
 */
#[AsTwigComponent('DownloadCard', template: 'components/_ui/download_card.html.twig')]
final class DownloadCard
{
    /** Libellé principal de la ressource. */
    public string $title = '';

    /** Sous-titre (nom de fichier, précision…). Optionnel. */
    public ?string $description = null;

    /** Métadonnée discrète affichée sous le titre (poids, date…). Optionnel. */
    public ?string $meta = null;

    /** URL de téléchargement / d'ouverture. */
    public string $href = '';

    /** word | excel | pdf | powerpoint | archive | video | audio | image | link | file */
    public string $type = 'file';

    /** Icône ux-icon forçant celle déduite de `type`. Optionnel. */
    public ?string $icon = null;

    /** Libellé du bouton forçant celui déduit de `type`. Optionnel. */
    public ?string $cta = null;

    /** Variante du bouton : primary | success | warning | danger | info | secondary */
    public string $variant = 'primary';

    /** Attribut target du lien. */
    public string $target = '_blank';

    /** Classes CSS supplémentaires sur la carte. */
    public string $extraClass = '';

    private const ICONS = [
        'word' => 'ph:file-doc',
        'excel' => 'ph:file-xls',
        'pdf' => 'ph:file-pdf',
        'powerpoint' => 'ph:file-ppt',
        'archive' => 'ph:file-zip',
        'video' => 'ph:file-video',
        'audio' => 'ph:file-audio',
        'image' => 'ph:file-image',
        'link' => 'ph:link-simple',
        'file' => 'ph:file',
    ];

    /** Teinte de l'icône, alignée sur la palette sémantique du projet. */
    private const TINTS = [
        'word' => 'text-info-600 dark:text-info-400',
        'excel' => 'text-success-600 dark:text-success-400',
        'pdf' => 'text-danger-600 dark:text-danger-400',
        'powerpoint' => 'text-warning-600 dark:text-warning-400',
        'archive' => 'text-secondary-500 dark:text-secondary-400',
        'video' => 'text-warning-600 dark:text-warning-400',
        'audio' => 'text-primary-600 dark:text-primary-400',
        'image' => 'text-info-600 dark:text-info-400',
        'link' => 'text-secondary-500 dark:text-secondary-400',
        'file' => 'text-secondary-500 dark:text-secondary-400',
    ];

    public function getResolvedIcon(): string
    {
        return $this->icon ?? self::ICONS[$this->type] ?? self::ICONS['file'];
    }

    public function getIconClasses(): string
    {
        return self::TINTS[$this->type] ?? self::TINTS['file'];
    }

    public function getResolvedCta(): string
    {
        if ($this->cta !== null) {
            return $this->cta;
        }

        return match ($this->type) {
            'video' => 'Télécharger la vidéo',
            'audio' => 'Télécharger le fichier audio',
            'link' => 'Ouvrir le lien',
            default => 'Télécharger le document',
        };
    }

    public function getButtonIcon(): string
    {
        return $this->type === 'link' ? 'icon:link' : 'icon:download';
    }
}
