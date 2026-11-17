<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @project oreofv2
 */

declare(strict_types=1);

namespace App\Twig\Components\UI;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Bouton loupe ouvrant un aperçu du document dans une modale (PDF, vidéo, audio, image).
 * Word (.docx) est rendu côté navigateur par la lib `docx-preview`.
 * Excel (.xlsx) est rendu en tableau par `read-excel-file`.
 * Les autres formats bureautiques (.doc, .xls, PowerPoint…) : la modale propose le téléchargement.
 *
 *   <twig:DocumentPreview url="{{ asset('docs/guide.pdf') }}" title="Guide" type="pdf" />
 */
#[AsTwigComponent('DocumentPreview', template: 'components/_ui/document_preview.html.twig')]
final class DocumentPreview
{
    /** URL du document (même origine). */
    public string $url = '';

    /** Titre affiché dans l'en-tête de la modale. */
    public string $title = '';

    /** word | excel | pdf | powerpoint | archive | video | audio | image | file */
    public string $type = 'file';

    /** Libellé de l'infobulle du bouton. */
    public string $tooltip = 'Aperçu';

    public string $size = 'md';

    public string $variant = 'secondary';

    /** Identifiant HTML stable (sinon aléatoire). */
    public string $id = '';

    private const PREVIEWABLE = ['pdf', 'video', 'audio', 'image', 'word', 'excel'];

    public function isPreviewable(): bool
    {
        return in_array($this->type, self::PREVIEWABLE, true);
    }
}
