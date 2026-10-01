<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\HelpImportResult;
use App\Entity\Faq;
use App\Entity\Help;
use App\Entity\HelpImage;
use App\Enums\CentreGestionEnum;
use App\Exception\FileUploadException;
use App\Repository\FaqRepository;
use App\Repository\HelpImageRepository;
use App\Repository\HelpRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Export / import des aides contextuelles, de la FAQ et de la galerie d'images entre deux instances
 * (ex. local → pré-production), sans passer par un dump SQL.
 *
 * Archive ZIP : manifest.json (métadonnées) + un fichier Markdown par aide/question (relisible tel quel)
 * + images/<fichier>. Clés de rapprochement à l'import : Help::routeSlug, Faq::question, HelpImage::fichier
 * (le nom de fichier est conservé pour que les liens /uploads/help_images/... du Markdown restent valides).
 */
class HelpTransferService
{
    public const FORMAT = 'oreof-help-export';
    public const VERSION = 1;

    private const UPLOAD_CONTEXT = 'help_images';
    private const MAX_ENTRIES = 5000;
    private const MAX_TEXT_SIZE = 2097152;
    private const MAX_IMAGE_SIZE = 10485760;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HelpRepository         $helpRepository,
        private readonly FaqRepository          $faqRepository,
        private readonly HelpImageRepository    $helpImageRepository,
        private readonly SecureUploadService    $secureUploadService,
        private readonly RouterInterface        $router,
    )
    {
    }

    /**
     * @return string chemin d'un fichier ZIP temporaire (à supprimer par l'appelant)
     */
    public function export(bool $withImages = true): string
    {
        $path = tempnam(sys_get_temp_dir(), 'help_export_');
        if ($path === false) {
            throw new \RuntimeException('Impossible de créer le fichier temporaire d\'export.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Impossible de créer l\'archive d\'export.');
        }

        $manifest = [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exportedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'helps' => [],
            'faqs' => [],
            'images' => [],
        ];

        foreach ($this->helpRepository->findBy([], ['routeSlug' => 'ASC', 'id' => 'ASC']) as $index => $help) {
            $file = sprintf('helps/%03d-%s.md', $index + 1, $this->slugify((string)$help->getRouteSlug()));
            $zip->addFromString($file, $this->normalizeNewlines((string)$help->getContent()));
            $manifest['helps'][] = [
                'routeSlug' => $help->getRouteSlug(),
                'title' => $help->getTitle(),
                'isActive' => (bool)$help->isActive(),
                'centresShow' => array_values($help->getCentresShow()),
                'file' => $file,
            ];
        }

        foreach ($this->faqRepository->findBy([], ['ordre' => 'ASC', 'id' => 'ASC']) as $index => $faq) {
            $file = sprintf('faqs/%03d.md', $index + 1);
            $zip->addFromString($file, $this->normalizeNewlines((string)$faq->getReponse()));
            $manifest['faqs'][] = [
                'question' => $faq->getQuestion(),
                'isActive' => (bool)$faq->isActive(),
                'centresShow' => array_values($faq->getCentresShow()),
                'ordre' => $faq->getOrdre(),
                'file' => $file,
            ];
        }

        if ($withImages) {
            foreach ($this->helpImageRepository->findBy([], ['id' => 'ASC']) as $image) {
                $fichier = (string)$image->getFichier();
                $hasFile = false;
                try {
                    $imagePath = $this->secureUploadService->resolveStoredFilePath(self::UPLOAD_CONTEXT, $fichier);
                    $hasFile = is_file($imagePath) && $zip->addFile($imagePath, 'images/' . $fichier);
                } catch (FileUploadException) {
                    // nom de fichier hors format : l'entrée est exportée sans fichier
                }

                $manifest['images'][] = [
                    'nom' => $image->getNom(),
                    'fichier' => $fichier,
                    'dateCreation' => $image->getDateCreation()?->format(DATE_ATOM),
                    'hasFile' => $hasFile,
                ];
            }
        }

        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        if ($zip->close() !== true) {
            throw new \RuntimeException('Impossible de finaliser l\'archive d\'export.');
        }

        return $path;
    }

    /**
     * @param bool $overwrite true : les aides/questions/images déjà présentes sont remplacées par celles de l'archive ;
     *                        false : seules les entrées absentes sont ajoutées
     *
     * @throws \InvalidArgumentException archive illisible ou qui n'est pas un export ORéOF
     */
    public function import(string $zipPath, bool $overwrite = false): HelpImportResult
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::RDONLY) !== true) {
            throw new \InvalidArgumentException('Le fichier transmis n\'est pas une archive ZIP lisible.');
        }

        try {
            if ($zip->numFiles > self::MAX_ENTRIES) {
                throw new \InvalidArgumentException('L\'archive contient trop de fichiers.');
            }

            $manifest = $this->readManifest($zip);
            $result = new HelpImportResult();

            $this->importImages($zip, $manifest['images'] ?? [], $overwrite, $result);
            $this->importHelps($zip, $manifest['helps'] ?? [], $overwrite, $result);
            $this->importFaqs($zip, $manifest['faqs'] ?? [], $overwrite, $result);

            $this->em->flush();

            return $result;
        } finally {
            $zip->close();
        }
    }

    private function readManifest(\ZipArchive $zip): array
    {
        $raw = $this->readText($zip, 'manifest.json');
        if ($raw === null) {
            throw new \InvalidArgumentException('Archive invalide : manifest.json introuvable. Utilisez une archive produite par le bouton « Exporter ».');
        }

        try {
            $manifest = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \InvalidArgumentException('Archive invalide : manifest.json illisible.');
        }

        if (!is_array($manifest) || ($manifest['format'] ?? null) !== self::FORMAT) {
            throw new \InvalidArgumentException('Archive invalide : ce n\'est pas un export d\'aides ORéOF.');
        }

        if ((int)($manifest['version'] ?? 0) > self::VERSION) {
            throw new \InvalidArgumentException('Archive produite par une version plus récente de l\'application : mettez à jour cette instance avant d\'importer.');
        }

        foreach (['helps', 'faqs', 'images'] as $key) {
            if (isset($manifest[$key]) && !is_array($manifest[$key])) {
                throw new \InvalidArgumentException('Archive invalide : section « ' . $key . ' » mal formée.');
            }
        }

        return $manifest;
    }

    private function importImages(\ZipArchive $zip, array $rows, bool $overwrite, HelpImportResult $result): void
    {
        $seen = [];
        foreach ($rows as $row) {
            $fichier = is_array($row) && is_string($row['fichier'] ?? null) ? $row['fichier'] : '';
            $nom = is_array($row) && is_string($row['nom'] ?? null) && trim($row['nom']) !== '' ? $this->truncate(trim($row['nom']), 255) : $fichier;

            try {
                $targetPath = $this->secureUploadService->resolveStoredFilePath(self::UPLOAD_CONTEXT, $fichier);
            } catch (FileUploadException) {
                $result->warnings[] = sprintf('Image « %s » ignorée : nom de fichier invalide.', $fichier);
                continue;
            }

            // Une même image listée plusieurs fois ne doit être ni recopiée ni créée deux fois.
            if (isset($seen[$fichier])) {
                continue;
            }
            $seen[$fichier] = true;

            $entity = $this->helpImageRepository->findOneBy(['fichier' => $fichier]);
            $targetExists = is_file($targetPath);
            $fileCopied = false;

            if ((!$targetExists || $overwrite) && $zip->statName('images/' . $fichier) !== false) {
                try {
                    $this->copyImage($zip, $fichier);
                    $fileCopied = true;
                    $targetExists = true;
                } catch (FileUploadException $e) {
                    $result->warnings[] = sprintf('Image « %s » refusée : %s', $nom, $e->getMessage());
                    continue;
                }
            }

            if (!$targetExists) {
                $result->warnings[] = sprintf('Image « %s » (%s) : fichier absent de l\'archive et du serveur.', $nom, $fichier);
                continue;
            }

            if ($entity === null) {
                $entity = (new HelpImage())->setNom($nom)->setFichier($fichier);
                $date = $this->parseDate($row['dateCreation'] ?? null);
                if ($date !== null) {
                    $entity->setDateCreation($date);
                }
                $this->em->persist($entity);
                $result->imagesCreated++;
            } elseif ($fileCopied || ($overwrite && $entity->getNom() !== $nom)) {
                $entity->setNom($overwrite ? $nom : (string)$entity->getNom());
                $result->imagesUpdated++;
            } else {
                $result->imagesSkipped++;
            }
        }
    }

    private function copyImage(\ZipArchive $zip, string $fichier): void
    {
        $entry = 'images/' . $fichier;
        $stat = $zip->statName($entry);
        if ($stat === false || $stat['size'] > self::MAX_IMAGE_SIZE) {
            throw FileUploadException::fileTooLarge((int)($stat['size'] ?? 0), self::MAX_IMAGE_SIZE);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'help_img_');
        $in = $zip->getStream($entry);
        if ($tmp === false || $in === false) {
            throw FileUploadException::invalidFile();
        }

        try {
            $out = fopen($tmp, 'wb');
            if ($out === false) {
                throw FileUploadException::invalidFile();
            }
            // Limite dure à la lecture : la taille annoncée par l'archive n'est pas digne de confiance.
            stream_copy_to_stream($in, $out, self::MAX_IMAGE_SIZE + 1);
            fclose($out);

            $this->secureUploadService->importStoredFile($tmp, $fichier, self::UPLOAD_CONTEXT);
        } finally {
            fclose($in);
            @unlink($tmp);
        }
    }

    private function importHelps(\ZipArchive $zip, array $rows, bool $overwrite, HelpImportResult $result): void
    {
        $seen = [];
        foreach ($rows as $row) {
            $routeSlug = is_array($row) && is_string($row['routeSlug'] ?? null) ? trim($row['routeSlug']) : '';
            if ($routeSlug === '' || mb_strlen($routeSlug) > 255 || isset($seen[$routeSlug])) {
                $result->warnings[] = sprintf('Aide ignorée : page cible absente, invalide ou en double (« %s »).', $routeSlug);
                continue;
            }
            $seen[$routeSlug] = true;

            $content = $this->readMarkdown($zip, $row['file'] ?? null, 'helps');
            if ($content === null) {
                $result->warnings[] = sprintf('Aide « %s » ignorée : contenu introuvable dans l\'archive.', $routeSlug);
                continue;
            }

            $help = $this->helpRepository->findOneBy(['routeSlug' => $routeSlug]);
            if ($help !== null && !$overwrite) {
                $result->helpsSkipped++;
                continue;
            }

            if ($help === null) {
                $help = (new Help())->setRouteSlug($routeSlug);
                $this->em->persist($help);
                $result->helpsCreated++;
            } else {
                $result->helpsUpdated++;
            }

            $help->setTitle(is_string($row['title'] ?? null) ? $this->truncate($row['title'], 255) : null)
                ->setContent($content)
                ->setIsActive((bool)($row['isActive'] ?? true))
                ->setCentresShow($this->sanitizeCentres($row['centresShow'] ?? []));

            if ($this->router->getRouteCollection()->get($routeSlug) === null) {
                $result->warnings[] = sprintf('Aide « %s » importée, mais cette page n\'existe pas sur cette instance.', $routeSlug);
            }
        }
    }

    private function importFaqs(\ZipArchive $zip, array $rows, bool $overwrite, HelpImportResult $result): void
    {
        $seen = [];
        foreach ($rows as $row) {
            $question = is_array($row) && is_string($row['question'] ?? null) ? trim($row['question']) : '';
            if ($question === '' || mb_strlen($question) > 500 || isset($seen[$question])) {
                $result->warnings[] = sprintf('Question ignorée : intitulé absent, trop long ou en double (« %s »).', $this->truncate($question, 80));
                continue;
            }
            $seen[$question] = true;

            $reponse = $this->readMarkdown($zip, $row['file'] ?? null, 'faqs');
            if ($reponse === null) {
                $result->warnings[] = sprintf('Question « %s » ignorée : réponse introuvable dans l\'archive.', $this->truncate($question, 80));
                continue;
            }

            $faq = $this->faqRepository->findOneBy(['question' => $question]);
            if ($faq !== null && !$overwrite) {
                $result->faqsSkipped++;
                continue;
            }

            if ($faq === null) {
                $faq = (new Faq())->setQuestion($question);
                $this->em->persist($faq);
                $result->faqsCreated++;
            } else {
                $faq->setUpdatedAt(new \DateTimeImmutable());
                $result->faqsUpdated++;
            }

            $faq->setReponse($reponse)
                ->setIsActive((bool)($row['isActive'] ?? true))
                ->setCentresShow($this->sanitizeCentres($row['centresShow'] ?? []))
                ->setOrdre(max(0, (int)($row['ordre'] ?? 0)));
        }
    }

    private function readMarkdown(\ZipArchive $zip, mixed $file, string $directory): ?string
    {
        if (!is_string($file) || !preg_match('#^' . $directory . '/[A-Za-z0-9._-]+\.md$#', $file)) {
            return null;
        }

        $content = $this->readText($zip, $file);

        return $content === null ? null : $this->normalizeNewlines($content);
    }

    private function readText(\ZipArchive $zip, string $entry): ?string
    {
        $stat = $zip->statName($entry);
        if ($stat === false || $stat['size'] > self::MAX_TEXT_SIZE) {
            return null;
        }

        $in = $zip->getStream($entry);
        if ($in === false) {
            return null;
        }

        $content = stream_get_contents($in, self::MAX_TEXT_SIZE + 1);
        fclose($in);

        if ($content === false || strlen($content) > self::MAX_TEXT_SIZE || !mb_check_encoding($content, 'UTF-8')) {
            return null;
        }

        return $content;
    }

    /** @return string[] */
    private function sanitizeCentres(mixed $centres): array
    {
        if (!is_array($centres)) {
            return [];
        }

        $allowed = [];
        foreach (CentreGestionEnum::cases() as $centre) {
            if ($centre !== CentreGestionEnum::CENTRE_GESTION_NULL) {
                $allowed[] = $centre->value;
            }
        }

        return array_values(array_unique(array_filter($centres, static fn ($c) => is_string($c) && in_array($c, $allowed, true))));
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function normalizeNewlines(string $content): string
    {
        return str_replace(["\r\n", "\r"], "\n", $content);
    }

    private function truncate(string $value, int $length): string
    {
        return mb_strlen($value) > $length ? mb_substr($value, 0, $length) : $value;
    }

    private function slugify(string $value): string
    {
        $slug = trim((string)preg_replace('/[^A-Za-z0-9._-]+/', '-', $value), '-.');

        return $slug !== '' ? mb_substr($slug, 0, 80) : 'aide';
    }
}
