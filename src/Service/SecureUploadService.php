<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Service/SecureUploadService.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 23/03/2026 10:53
 */

declare(strict_types=1);

namespace App\Service;

use App\DTO\UploadedFileMetadata;
use App\Exception\FileUploadException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;

class SecureUploadService
{
    /**
     * @param array<string, array{target_dir:string,allowed_extensions:array<int,string>,allowed_mime_types:array<int,string>,max_size?:int}> $uploadContexts
     */
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly array      $uploadContexts,
        private readonly \Imagine\Gd\Imagine $imagine = new \Imagine\Gd\Imagine(),
        private readonly int        $logoMaxWidth = 200,
        private readonly int        $logoMaxHeight = 200,
        private readonly bool       $logoAllowUpscale = false,
    )
    {
    }

    public function uploadFromRequest(Request $request, string $field, string $context): ?UploadedFileMetadata
    {
        $file = $request->files->get($field);
        if (!$file instanceof UploadedFile) {
            return null;
        }

        return $this->upload($file, $context);
    }

    public function upload(UploadedFile $file, string $context): UploadedFileMetadata
    {
        $config = $this->uploadContexts[$context] ?? null;
        if ($config === null) {
            throw FileUploadException::contextNotConfigured($context);
        }

        if (!$file->isValid()) {
            throw FileUploadException::invalidFile();
        }

        $size = (int)$file->getSize();
        if ($size <= 0) {
            throw FileUploadException::emptyFile();
        }

        $maxSize = (int)($config['max_size'] ?? 10485760);
        if ($size > $maxSize) {
            throw FileUploadException::fileTooLarge($size, $maxSize);
        }

        $extension = strtolower((string)$file->guessExtension());
        if ($extension === '') {
            $extension = strtolower((string)$file->getClientOriginalExtension());
        }
        $extension = preg_replace('/[^a-z0-9]/', '', $extension ?? '') ?? '';

        if ($extension === '' || !in_array($extension, $config['allowed_extensions'], true)) {
            throw FileUploadException::extensionNotAllowed($extension === '' ? '(vide)' : $extension);
        }

        $mimeType = (string)(new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname());
        if ($mimeType === '') {
            throw FileUploadException::missingMimeType();
        }

        if (!in_array($mimeType, $config['allowed_mime_types'], true)) {
            throw FileUploadException::mimeTypeNotAllowed($mimeType);
        }

        // Controle croise extension/MIME pour eviter un contournement par renommage.
        $expectedMimes = $this->mimesForExtension($extension);
        if (!in_array($mimeType, $expectedMimes, true)) {
            throw FileUploadException::extensionMimeMismatch($extension, $mimeType);
        }

        $targetDir = rtrim($config['target_dir'], '/');
        $this->filesystem->mkdir($targetDir, 0750);

        $storedFilename = bin2hex(random_bytes(24)) . '.' . $extension;

        try {
            $file->move($targetDir, $storedFilename);
        } catch (FileException) {
            throw FileUploadException::uploadMoveFailed();
        }

        if (in_array($context, self::RESIZABLE_CONTEXTS, true)) {
            $this->resizeImage($targetDir . '/' . $storedFilename, $this->logoMaxWidth, $this->logoMaxHeight, $this->logoAllowUpscale);
        }

        return new UploadedFileMetadata(
            $storedFilename,
            $this->sanitizeOriginalFilename((string)$file->getClientOriginalName()),
            $extension,
            $mimeType,
            $size,
            $targetDir,
        );
    }

    /**
     * @return array<int, string>
     */
    private function mimesForExtension(string $extension): array
    {
        return match ($extension) {
            'pdf' => ['application/pdf'],
            'png' => ['image/png'],
            'jpg', 'jpeg' => ['image/jpeg'],
            default => [],
        };
    }

    private function sanitizeOriginalFilename(string $originalName): string
    {
        $basename = basename($originalName);
        $safe = preg_replace('/[^A-Za-z0-9._ -]/', '_', $basename) ?? 'document';
        $safe = trim($safe);

        return $safe !== '' ? $safe : 'document';
    }

    public function resolveStoredFilePath(string $context, string $storedFilename): string
    {
        $config = $this->uploadContexts[$context] ?? null;
        if ($config === null) {
            throw FileUploadException::contextNotConfigured($context);
        }

        $safeFilename = basename($storedFilename);
        if ($safeFilename !== $storedFilename || !preg_match('/^[a-f0-9]{48}\.[a-z0-9]+$/', $safeFilename)) {
            throw FileUploadException::invalidFile();
        }

        return rtrim($config['target_dir'], '/') . '/' . $safeFilename;
    }

    public function getDownloadFilename(?string $originalName, string $storedFilename): string
    {
        if ($originalName !== null && trim($originalName) !== '') {
            return $this->sanitizeOriginalFilename($originalName);
        }

        return $this->sanitizeOriginalFilename($storedFilename);
    }

    //region Section pour les logos

    private const DELETABLE_CONTEXTS = ['logos']; // La méthode ne marche que pour les logos, car je n'ai pas encore regardé si l'upload de PDF en a besoin.
    private const RESIZABLE_CONTEXTS = ['logos'];

    public function delete(string $context, string $storedFilename): void
    {
        if (!in_array($context, self::DELETABLE_CONTEXTS, true)) {
            throw FileUploadException::contextNotConfigured($context);
        }

        $filePath = $this->resolveStoredFilePath($context, $storedFilename);
        if ($this->filesystem->exists($filePath)) {
            $this->filesystem->remove($filePath);
        }
    }

    private function resizeImage(string $filePath, int $maxWidth, int $maxHeight, bool $allowUpscale): void
    {
        $image = $this->imagine->open($filePath);
        $size = $image->getSize();

        $widthRatio = $maxWidth / $size->getWidth();
        $heightRatio = $maxHeight / $size->getHeight();

        // Prend le ratio le plus contraignant, et plafonne à 1.0 si l'upscale est désactivé
        $ratio = min($widthRatio, $heightRatio);
        if (!$allowUpscale) {
            $ratio = min($ratio, 1.0);
        }

        // Aucun redimensionnement nécessaire
        if (abs($ratio - 1.0) < 1e-9) {
            return;
        }

        $newWidth = (int)round($size->getWidth() * $ratio);
        $newHeight = (int)round($size->getHeight() * $ratio);
        $image->resize(new Box($newWidth, $newHeight))
            ->save($filePath);
    }

    //endregion
}