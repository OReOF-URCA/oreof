<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @project oreofv2
 */

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Bibliothèque de documents de la page /documents (dossier public/docs).
 * Les fichiers sont servis tels quels : seules les extensions de la liste blanche sont acceptées.
 */
final class DocumentLibrary
{
    public const MAX_SIZE = 104857600; // 100 Mo

    public const ALLOWED_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'odt', 'xls', 'xlsx', 'ods', 'csv', 'ppt', 'pptx', 'odp',
        'zip', 'mp4', 'webm', 'mov', 'mp3', 'png', 'jpg', 'jpeg', 'gif',
    ];

    /** Types MIME jamais acceptés, quelle que soit l'extension annoncée. */
    private const FORBIDDEN_MIMES = ['text/html', 'text/x-php', 'application/x-httpd-php', 'application/x-sh', 'application/javascript', 'text/javascript', 'image/svg+xml'];

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/docs')] private readonly string $directory,
        private readonly Filesystem $filesystem,
    ) {
    }

    /** @throws \InvalidArgumentException message affichable à l'utilisateur */
    public function add(UploadedFile $file): string
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Le fichier n\'a pas pu être reçu (taille limite du serveur dépassée ?).');
        }

        $size = (int) $file->getSize();
        if ($size <= 0) {
            throw new \InvalidArgumentException('Le fichier est vide.');
        }
        if ($size > self::MAX_SIZE) {
            throw new \InvalidArgumentException('Le fichier dépasse la taille maximale de 100 Mo.');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException(sprintf('Extension « %s » non autorisée.', $extension !== '' ? $extension : 'vide'));
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname());
        if (in_array($mime, self::FORBIDDEN_MIMES, true)) {
            throw new \InvalidArgumentException('Le contenu du fichier ne correspond pas à un document autorisé.');
        }

        $base = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $base = trim((string) preg_replace('/[^\p{L}\p{N}._ \-()\']/u', '_', $base));
        $base = ltrim($base, '.') ?: 'document';
        $name = $base . '.' . $extension;

        if ($this->filesystem->exists($this->directory . '/' . $name)) {
            $name = $base . '_' . date('Ymd_His') . '.' . $extension;
        }

        $this->filesystem->mkdir($this->directory, 0755);
        $file->move($this->directory, $name);

        return $name;
    }

    public function remove(string $name): bool
    {
        $path = $this->resolve($name);
        if ($path === null) {
            return false;
        }

        $this->filesystem->remove($path);

        return true;
    }

    private function resolve(string $name): ?string
    {
        if ($name === '' || $name !== basename($name) || str_starts_with($name, '.')) {
            return null;
        }
        if (!in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::ALLOWED_EXTENSIONS, true)) {
            return null;
        }

        $path = $this->directory . '/' . $name;

        return is_file($path) ? $path : null;
    }
}
