<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/DTO/UploadedFileMetadata.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 23/03/2026 10:53
 */

declare(strict_types=1);

namespace App\DTO;

final readonly class UploadedFileMetadata
{
    public function __construct(
        private string $storedFilename,
        private string $originalFilename,
        private string $extension,
        private string $mimeType,
        private int    $size,
        private string $targetDirectory,
    )
    {
    }

    public function getStoredFilename(): string
    {
        return $this->storedFilename;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getTargetDirectory(): string
    {
        return $this->targetDirectory;
    }
}

