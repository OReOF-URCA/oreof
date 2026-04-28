<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Exception/FileUploadException.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 23/03/2026 10:53
 */

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

class FileUploadException extends RuntimeException
{
    public static function contextNotConfigured(string $context): self
    {
        return new self(sprintf("Contexte d'upload inconnu: %s", $context));
    }

    public static function invalidFile(): self
    {
        return new self('Le fichier televerse est invalide.');
    }

    public static function missingMimeType(): self
    {
        return new self('Impossible de determiner le type MIME du fichier.');
    }

    public static function extensionNotAllowed(string $extension): self
    {
        return new self(sprintf('Extension non autorisee: %s', $extension));
    }

    public static function mimeTypeNotAllowed(string $mimeType): self
    {
        return new self(sprintf('Type MIME non autorise: %s', $mimeType));
    }

    public static function extensionMimeMismatch(string $extension, string $mimeType): self
    {
        return new self(sprintf('Incoherence extension/MIME: .%s / %s', $extension, $mimeType));
    }

    public static function fileTooLarge(int $size, int $maxSize): self
    {
        return new self(sprintf('Fichier trop volumineux (%d > %d octets).', $size, $maxSize));
    }

    public static function emptyFile(): self
    {
        return new self('Le fichier est vide.');
    }

    public static function uploadMoveFailed(): self
    {
        return new self('Le televersement a echoue lors de l\'ecriture du fichier.');
    }

    public function getPublicMessage(): string
    {
        return 'Le fichier transmis est invalide ou non autorise.';
    }
}

