<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file //wsl.localhost/Ubuntu/home/louca/oreof-stack/oreofv2/src/Service/UiCatalogProvider.php
 * @author louca
 * @project oreofv2
 * @lastUpdate 18/05/2026 15:24
 */

declare(strict_types=1);

namespace App\Service;

use App\DTO\Remplissage;
use DateTimeImmutable;
use Symfony\Component\Security\Core\User\UserInterface;

final class UiCatalogProvider
{
    public function build(?UserInterface $user = null): array
    {
        $projectDir = \dirname(__DIR__, 2);
        $templatesDir = $projectDir . '/templates/components';
        $classesDir = $projectDir . '/src/Twig/Components';

        $classFiles = $this->scanClassFiles($classesDir);
        $projectFiles = $this->scanProjectFiles($projectDir);
        $components = [];

        if (is_dir($templatesDir)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($templatesDir));
            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'twig') {
                    continue;
                }

                $absPath = $file->getPathname();
                $relativeTemplate = 'templates/components' . str_replace($templatesDir, '', $absPath);
                $templateContent = file_get_contents($absPath) ?: '';
                $templateBase = $this->templateBaseName($file->getBasename('.twig'));
                $lookupKey = $this->normalizeIdentifier($templateBase);
                $classInfo = $classFiles[$lookupKey] ?? null;
                $componentName = $this->resolveComponentName($classInfo['abs'] ?? null, $templateBase);
                $category = $this->resolveCategory($relativeTemplate);
                $preview = $this->buildPreview($componentName, $relativeTemplate, $templateContent, $user);
                [$usageCount, $usageScore] = $this->countUsage($projectFiles, $absPath, $classInfo['abs'] ?? null, $componentName, $category);
                $importanceGroup = $this->resolveImportanceGroup($usageCount, $category);

                $components[$category][] = [
                    'name' => $componentName,
                    'componentName' => $componentName,
                    'template' => $relativeTemplate,
                    'class' => $classInfo['rel'] ?? null,
                    'preview' => true,
                    'previewProps' => $preview['props'],
                    'previewHtml' => $preview['html'],
                    'component' => $preview['snippet'],
                    'note' => $preview['note'],
                    'usageCount' => $usageCount,
                    'importanceScore' => $usageScore,
                    'importanceGroup' => $importanceGroup,
                    'importanceLabel' => ucfirst($importanceGroup),
                ];
            }
        }

        $this->sortComponents($components);

        return [
            'sections' => $this->buildSections($components),
            'all_components' => $components,
            'featured_components' => $this->buildFeaturedComponents($components),
        ];
    }

    private function scanProjectFiles(string $projectDir): array
    {
        $files = [];
        foreach (['templates', 'src'] as $subDir) {
            $baseDir = $projectDir . '/' . $subDir;
            if (!is_dir($baseDir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($baseDir));
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                if (!in_array($file->getExtension(), ['twig', 'php'], true)) {
                    continue;
                }

                $path = $file->getPathname();
                $content = file_get_contents($path) ?: '';
                $files[] = [
                    'path' => $path,
                    'lower' => strtolower($content),
                ];
            }
        }

        return $files;
    }

    private function scanClassFiles(string $classesDir): array
    {
        $classFiles = [];
        if (!is_dir($classesDir)) {
            return $classFiles;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($classesDir));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $abs = $file->getPathname();
            $basename = $this->normalizeIdentifier($file->getBasename('.php'));
            $classFiles[$basename] = [
                'abs' => $abs,
                'rel' => 'src/Twig/Components' . str_replace($classesDir, '', $abs),
                'content' => file_get_contents($abs) ?: '',
            ];
        }

        return $classFiles;
    }

    private function resolveComponentName(?string $classPath, string $fallback): string
    {
        if ($classPath !== null && is_file($classPath)) {
            $content = file_get_contents($classPath) ?: '';

            if (preg_match('/As(?:Twig|Live)Component\((?:\s*name\s*:\s*)?["\']([^"\']+)["\']/', $content, $matches)) {
                return $matches[1];
            }

            $basename = pathinfo($classPath, PATHINFO_FILENAME);
            return str_ends_with($basename, 'Component') ? substr($basename, 0, -9) : $basename;
        }

        return str_replace(' ', '', ucwords(str_replace(['-', '_', '.'], ' ', $fallback)));
    }

    private function templateBaseName(string $fileName): string
    {
        return str_contains($fileName, '.') ? strstr($fileName, '.', true) : $fileName;
    }

    private function normalizeIdentifier(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/component$/', '', $value) ?? $value;

        return preg_replace('/[^a-z0-9]+/', '', $value) ?? $value;
    }

    private function resolveCategory(string $templatePath): string
    {
        return match (true) {
            str_contains($templatePath, '/_ui/') => 'UI',
            str_contains($templatePath, '/_layout/') => 'Layout',
            str_contains($templatePath, '/_domain/') => 'Domain',
            default => 'Utilities',
        };
    }

    private function buildSections(array $components): array
    {
        $descriptions = [
            'Domain' => 'Composants liés au domaine ORéOF.',
            'Layout' => 'Blocs de structure et d’actions.',
            'UI' => 'Composants réutilisables dans toute l’application.',
            'Utilities' => 'Composants utilitaires ou d’affichage secondaire.',
        ];

        $order = ['UI', 'Layout', 'Utilities', 'Domain'];

        $sections = [];
        foreach ($order as $category) {
            if (!isset($components[$category])) {
                continue;
            }

            $sections[] = [
                'title' => $category,
                'description' => $descriptions[$category] ?? 'Composants détectés automatiquement.',
                'items' => $components[$category],
                'collapsedByDefault' => in_array($category, ['Utilities', 'Domain'], true),
                'sectionLabel' => in_array($category, ['Utilities', 'Domain'], true) ? 'Secondaire' : 'Core',
            ];
        }

        return $sections;
    }

    private function sortComponents(array &$components): void
    {
        foreach ($components as &$items) {
            usort($items, static function (array $a, array $b): int {
                $scoreCompare = ($b['importanceScore'] ?? 0) <=> ($a['importanceScore'] ?? 0);
                if (0 !== $scoreCompare) {
                    return $scoreCompare;
                }

                $usageCompare = ($b['usageCount'] ?? 0) <=> ($a['usageCount'] ?? 0);
                if (0 !== $usageCompare) {
                    return $usageCompare;
                }

                return strcasecmp((string) $a['name'], (string) $b['name']);
            });
        }
        unset($items);
    }

    private function buildFeaturedComponents(array $components, int $limit = 8): array
    {
        $flat = [];
        foreach ($components as $category => $items) {
            foreach ($items as $item) {
                $item['category'] = $category;
                $flat[] = $item;
            }
        }

        usort($flat, static function (array $a, array $b): int {
            $scoreCompare = ($b['importanceScore'] ?? 0) <=> ($a['importanceScore'] ?? 0);
            if (0 !== $scoreCompare) {
                return $scoreCompare;
            }

            $usageCompare = ($b['usageCount'] ?? 0) <=> ($a['usageCount'] ?? 0);
            if (0 !== $usageCompare) {
                return $usageCompare;
            }

            return strcasecmp((string) $a['name'], (string) $b['name']);
        });

        return array_slice($flat, 0, $limit);
    }

    private function resolveImportanceGroup(int $usageCount, string $category): string
    {
        if ($usageCount >= 10) {
            return 'core';
        }

        if ($usageCount >= 3) {
            return 'frequent';
        }

        return match ($category) {
            'UI', 'Layout' => 'frequent',
            default => 'specific',
        };
    }

    private function countUsage(array $projectFiles, string $templateAbsPath, ?string $classPath, string $componentName, string $category): array
    {
        $templateNeedle = strtolower('components/' . str_replace('templates/components/', '', str_replace('\\', '/', $templateAbsPath)));
        $nameNeedle = strtolower($componentName);
        $patterns = [
            "component('{$nameNeedle}'",
            'component("' . $nameNeedle . '"',
            '<twig:' . $nameNeedle,
            'include(' . "'{$templateNeedle}'",
            'include(' . '"' . $templateNeedle . '"',
            $templateNeedle,
        ];

        $usageCount = 0;
        foreach ($projectFiles as $file) {
            if ($classPath !== null && $file['path'] === $classPath) {
                continue;
            }

            if ($file['path'] === $templateAbsPath) {
                continue;
            }

            foreach ($patterns as $pattern) {
                if ($pattern === '') {
                    continue;
                }
                $usageCount += substr_count($file['lower'], $pattern);
            }
        }

        $categoryWeight = match ($category) {
            'UI' => 300,
            'Layout' => 200,
            'Utilities' => 100,
            default => 0,
        };

        return [$usageCount, ($usageCount * 10) + $categoryWeight];
    }

    private function buildPreview(string $componentName, string $templatePath, string $templateContent, ?UserInterface $user): array
    {
        $props = [];
        $html = null;
        $note = null;

        $templateKey = strtolower(basename($templatePath));

        switch ($templateKey) {
            case 'button.html.twig':
                $props = [
                    'label' => 'Ajouter',
                    'variant' => 'success',
                    'icon' => 'icon:add',
                    'href' => '#',
                ];
                break;
            case 'badge.html.twig':
                $props = [
                    'label' => 'Publié',
                    'variant' => 'success',
                    'icon' => 'icon:check',
                ];
                break;
            case 'alerte.html.twig':
                $props = [
                    'type' => 'warning',
                    'message' => 'Attention : exemple de message.',
                ];
                break;
            case 'remplissage_progress.html.twig':
                $score = new Remplissage();
                $score->setScore(7);
                $score->setTotal(10);
                $props = [
                    'value' => $score,
                ];
                break;
            case 'delete_button.html.twig':
                $props = [
                    'url' => '#',
                    'label' => 'Supprimer',
                    'tooltip' => 'Supprimer un élément',
                    'title' => 'Confirmer la suppression',
                    'message' => 'Cette action est irréversible.',
                ];
                break;
            case 'iconbox.html.twig':
                $props = [
                    'icon' => 'icon:check',
                    'variant' => 'success',
                ];
                break;
            case 'dropdown_actions.html.twig':
                $props = [
                    'items' => [
                        ['label' => 'Voir', 'url' => '#', 'icon' => 'icon:eye', 'method' => 'GET'],
                        ['label' => 'Supprimer', 'url' => '#', 'icon' => 'icon:delete', 'method' => 'POST', 'danger' => true],
                    ],
                    'label' => 'Actions',
                    'help' => 'Menu d\'actions',
                    'labelSrOnly' => false,
                    'id' => 'dropdown-actions-demo',
                    'icon' => 'icon:ellipsis',
                ];
                break;
            case 'actualites.html.twig':
                $props = [
                    'demoActualites' => [
                        [
                            'datePublication' => new DateTimeImmutable('-2 days'),
                            'titre' => 'Nouvelle version du catalogue',
                            'texte' => 'Le catalogue UI affiche désormais des aperçus automatiques et une palette de couleurs.',
                        ],
                        [
                            'datePublication' => new DateTimeImmutable('-5 days'),
                            'titre' => 'Migration des icônes',
                            'texte' => 'Les alias d’icônes ont été harmonisés avec la table de migration du projet.',
                        ],
                    ],
                ];
                break;
            case 'notifications.html.twig':
                $props = [
                    'user' => $user,
                    'formation' => null,
                    'parcours' => null,
                ];
                break;
            case 'stats_block.html.twig':
                $props = [
                    'charts' => [],
                    'titles' => [],
                    'collapsible' => false,
                ];
                break;
            case 'historique_date.html.twig':
                $props = [
                    'date' => new DateTimeImmutable('-3 days'),
                    'type' => 'demo',
                ];
                break;
        }

        if (str_contains($templatePath, 'filter_panel.html.twig')) {
            $html = <<<HTML
<div class="rounded-xl border p-4" style="background-color: var(--color-bg); border-color: var(--color-border); color: var(--color-text);">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="w-full sm:max-w-sm">
            <label class="mb-2 block text-xs font-semibold uppercase tracking-wider" style="color: var(--color-text-muted);">Recherche</label>
            <input type="text" class="w-full rounded-lg border px-3 py-2 text-sm" style="background-color: var(--color-surface); border-color: var(--color-border); color: var(--color-text);" placeholder="Rechercher...">
        </div>
        <div class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold" style="background-color: var(--color-border); color: var(--color-text);">
            <span>Filtres actifs</span>
            <span class="inline-flex min-w-5 items-center justify-center rounded-full px-1.5 py-0.5 text-[10px] font-bold text-white" style="background-color: var(--color-primary);">2</span>
        </div>
    </div>
</div>
HTML;
            $note = 'Aperçu HTML statique du panneau de filtres.';
        } elseif (str_contains($templatePath, 'SidebarParcours.html.twig')) {
            $html = <<<HTML
<div class="rounded-xl border p-4" style="background-color: var(--color-text); border-color: var(--color-border); color: var(--color-surface);">
    <div class="space-y-4">
        <div class="flex items-center justify-between"><span class="text-sm font-bold">Année 1</span><span class="h-3 w-3 rounded-full bg-emerald-500"></span></div>
        <div class="flex items-center justify-between"><span class="text-sm font-bold">Année 2</span><span class="h-3 w-3 rounded-full bg-emerald-500"></span></div>
        <div class="flex items-center justify-between"><span class="text-sm font-bold">Année 3</span><span class="h-3 w-3 rounded-full bg-red-500"></span></div>
    </div>
</div>
HTML;
            $note = 'Aperçu HTML statique de la sidebar parcours.';
        } elseif (in_array($templateKey, ['badge_ects.html.twig', 'badge_bcc.html.twig', 'badge_mccc.html.twig', 'badge_ects_semestre.html.twig'], true)) {
            $html = match ($templateKey) {
                'badge_ects.html.twig' => '<span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800">6 ECTS</span>',
                'badge_bcc.html.twig' => '<span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800">BCC complet</span>',
                'badge_mccc.html.twig' => '<span class="inline-flex items-center gap-1 rounded-full bg-indigo-100 px-2 py-1 text-xs font-semibold text-indigo-800">MCCC à saisir</span>',
                default => '<span class="inline-flex items-center gap-1 rounded-full bg-cyan-100 px-2 py-1 text-xs font-semibold text-cyan-800">18 ECTS / semestre</span>',
            };
            $note = 'Aperçu statique du badge métier.';
        }

        if ($html === null && $props === []) {
            $html = sprintf(
                '<div class="rounded-xl border border-dashed p-4 text-sm" style="background-color: var(--color-bg); border-color: var(--color-border); color: var(--color-text-muted);">Aperçu automatique non disponible pour <strong style="color: var(--color-text);">%s</strong>.</div>',
                htmlspecialchars($componentName, ENT_QUOTES)
            );
            $note = 'Aperçu automatique générique.';
        }

        return [
            'props' => $props,
            'html' => $html,
            'note' => $note,
            'snippet' => $this->buildSnippet($componentName, $props, $html !== null),
        ];
    }

    private function buildSnippet(string $componentName, array $props, bool $staticPreview): string
    {
        if ($staticPreview) {
            return sprintf('<!-- Aperçu visuel automatique pour %s -->', $componentName);
        }

        if ($props === []) {
            return sprintf("{{ component('%s') }}", $componentName);
        }

        $pairs = [];
        foreach ($props as $key => $value) {
            $pairs[] = sprintf('%s: %s', $key, $this->formatTwigValue($value));
        }

        return sprintf("{{ component('%s', { %s }) }}", $componentName, implode(', ', $pairs));
    }

    private function formatTwigValue(mixed $value): string
    {
        return match (true) {
            is_string($value) => sprintf("'%s'", str_replace("'", "\\'", $value)),
            is_int($value), is_float($value) => (string) $value,
            is_bool($value) => $value ? 'true' : 'false',
            null === $value => 'null',
            $value instanceof UserInterface => 'app.user',
            $value instanceof DateTimeImmutable, $value instanceof \DateTimeInterface => "date('Y-m-d')",
            $value instanceof Remplissage => 'remplissage',
            is_array($value) => '[...]',
            default => '...'
        };
    }
}


