<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/LogViewerController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 20/03/2026 09:58
 */

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/logs', name: 'app_log_viewer_')]
class LogViewerController extends AbstractController
{
    public function __construct(
        #[Autowire('%kernel.logs_dir%')] private readonly string    $logsDir,
        #[Autowire('%kernel.environment%')] private readonly string $environment,
    )
    {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $files = $this->listLogFiles();

        $selectedFile = $request->query->get('file');
        $levelFilter = $request->query->get('level', 'all');

        $entries = [];
        $selectedFileInfo = null;
        $stats = ['error' => 0, 'critical' => 0, 'total' => 0];

        if ($selectedFile !== null && isset($files[$selectedFile])) {
            $selectedFileInfo = $files[$selectedFile];
            $entries = $this->parseLogFile($selectedFileInfo['path'], $levelFilter);

            foreach ($entries as $entry) {
                $lvl = strtolower($entry['level']);
                if (isset($stats[$lvl])) {
                    ++$stats[$lvl];
                }
                ++$stats['total'];
            }
        }

        return $this->render('log_viewer/index.html.twig', [
            'files' => $files,
            'selectedFile' => $selectedFile,
            'selectedFileInfo' => $selectedFileInfo,
            'entries' => $entries,
            'levelFilter' => $levelFilter,
            'stats' => $stats,
        ]);
    }

    /**
     * Liste tous les fichiers de logs error et critical du répertoire var/log/
     *
     * @return array<string, array{path: string, filename: string, level: string, date: string, size: int}>
     */
    private function listLogFiles(): array
    {
        $files = [];
        $pattern = sprintf('%s/%s.{error,critical}-*.log', $this->logsDir, $this->environment);

        foreach (glob($pattern, GLOB_BRACE) ?: [] as $filePath) {
            $filename = basename($filePath);
            // Format attendu : dev.error-2026-03-18.log  ou  dev.critical-2026-03-18.log
            if (preg_match('/^.+\.(error|critical)-(\d{4}-\d{2}-\d{2})\.log$/', $filename, $m)) {
                $files[$filename] = [
                    'path' => $filePath,
                    'filename' => $filename,
                    'level' => strtoupper($m[1]),
                    'date' => $m[2],
                    'size' => filesize($filePath) ?: 0,
                ];
            }
        }

        // Trier par date décroissante, puis par type
        uasort($files, static function (array $a, array $b): int {
            $cmp = strcmp($b['date'], $a['date']);
            return $cmp !== 0 ? $cmp : strcmp($a['level'], $b['level']);
        });

        return $files;
    }

    /**
     * Parse un fichier de log et retourne un tableau d'entrées structurées.
     * Chaque ligne du format Monolog : [timestamp] channel.LEVEL: message {context} {extra}
     *
     * @return array<int, array{timestamp: string, channel: string, level: string, message: string}>
     */
    private function parseLogFile(string $filePath, string $levelFilter = 'all'): array
    {
        if (!is_readable($filePath)) {
            return [];
        }

        $entries = [];
        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            return [];
        }

        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line);
            if ($line === '') {
                continue;
            }

            // Format Monolog : [2026-03-18T19:43:20.627392+00:00] channel.LEVEL: message...
            if (preg_match(
                '/^\[(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[^]]+)]\s+([^.]+)\.([A-Z]+):\s+(.+)$/s',
                $line,
                $m
            )) {
                $level = $m[3];

                // Filtrage par niveau
                if ($levelFilter !== 'all' && strtolower($level) !== strtolower($levelFilter)) {
                    continue;
                }

                // Séparer le message du contexte JSON en fin de ligne
                $raw = $m[4];
                $message = $raw;
                $context = '';

                // Tenter d'extraire le contexte JSON final : "... {…} []"
                if (preg_match('/^(.*?)(\{.*})\s+(\[.*])\s*$/s', $raw, $jm)) {
                    $message = rtrim($jm[1]);
                    $context = $jm[2];
                }

                $entries[] = [
                    'timestamp' => $m[1],
                    'channel' => $m[2],
                    'level' => $level,
                    'message' => $message,
                    'context' => $context,
                ];
            } elseif (!empty($entries)) {
                // Ligne de continuation (stack trace, etc.)
                $entries[array_key_last($entries)]['message'] .= "\n" . $line;
            }
        }

        fclose($handle);

        // Entrées les plus récentes en premier
        return array_reverse($entries);
    }
}

