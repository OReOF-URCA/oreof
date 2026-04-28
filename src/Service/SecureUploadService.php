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

class SecureUploadService
{
    /**
     * @param array<string, array{target_dir:string,allowed_extensions:array<int,string>,allowed_mime_types:array<int,string>,max_size?:int}> $uploadContexts
     */
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly array      $uploadContexts,
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

        $storedFilename = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;

        try {
            $file->move($targetDir, $storedFilename);
        } catch (FileException) {
            throw FileUploadException::uploadMoveFailed();
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

    private function doUpload(string $context, UploadedFile $file, array $config): string
    {
        $this->validateFile($file, $config['max_size'], $config['allowed_extensions'], $config['allowed_mime_types']);

        $extension = $file->guessExtension() ?? $file->getClientOriginalExtension();
        $targetDir = rtrim($config['target_dir'], '/');
        $this->filesystem->mkdir($targetDir, 0750);

        $storedFilename = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;

        try {
            $file->move($targetDir, $storedFilename);
        } catch (\Exception $e) {
            throw FileUploadException::uploadFailed($e->getMessage());
        }

        return $storedFilename;
    }

    public function resolveStoredFilePath(string $context, string $storedFilename): string
    {
        $config = $this->uploadContexts[$context] ?? null;
        if ($config === null) {
            throw FileUploadException::contextNotConfigured($context);
        }

        $safeFilename = basename($storedFilename);
        if ($safeFilename !== $storedFilename || !preg_match('/^(\d{8}_\d{6}_[a-f0-9]{12}|[a-f0-9]{48})\.[a-z0-9]+$/', $safeFilename)) {
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
}

