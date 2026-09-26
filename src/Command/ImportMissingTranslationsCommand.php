<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Command/ImportMissingTranslationsCommand.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 15/02/2026 13:59
 */

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'app:translations:import-missing',
    description: 'Importe les traductions manquantes depuis un fichier de log et/ou reformate les fichiers translations/*.{locale}.yaml',
    aliases: ['app:translations:format']
)]
final class ImportMissingTranslationsCommand extends Command
{
    private const int YAML_INLINE_LEVEL = 10;
    private const int YAML_INDENT_SPACES = 4;

    protected function configure(): void
    {
        $defaultDate = date('Y-m-d');
        $this
            ->addArgument('logfile', InputArgument::OPTIONAL, 'Chemin du fichier de log à parser', '%kernel.project_dir%/var/log/dev.translations-' . $defaultDate . '.log')
            ->addOption('translations-dir', null, InputOption::VALUE_REQUIRED, 'Répertoire des fichiers de traduction', '%kernel.project_dir%/translations')
            ->addOption('simulate', null, InputOption::VALUE_NONE, 'Ne pas écrire les fichiers, afficher les changements')
            ->addOption('overwrite', null, InputOption::VALUE_NONE, 'Écraser les clés existantes lors de l\'import (attention)')
            ->addOption('format', 'f', InputOption::VALUE_NONE, 'Reformater et linter tous les fichiers de traduction YAML existants (sans importer de log)')
            ->addOption('no-backup', null, InputOption::VALUE_NONE, 'Ne pas créer de fichier de backup (.bak) avant modification');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $projectDir = (string) (getcwd() ?: '.');
        $translationsDir = str_replace('%kernel.project_dir%', $projectDir, (string) $input->getOption('translations-dir'));
        $simulate = (bool) $input->getOption('simulate');
        $overwrite = (bool) $input->getOption('overwrite');
        $formatOnly = (bool) $input->getOption('format') || $input->getFirstArgument() === 'app:translations:format';
        $noBackup = (bool) $input->getOption('no-backup');

        if (!is_dir($translationsDir)) {
            $io->warning('Répertoire de traductions introuvable, création: ' . $translationsDir);
            if (!$simulate) {
                if (!mkdir($translationsDir, 0775, true) && !is_dir($translationsDir)) {
                    $io->error('Impossible de créer le répertoire de traductions: ' . $translationsDir);
                    return Command::FAILURE;
                }
            }
        }

        if ($formatOnly) {
            return $this->formatTranslationsFiles($translationsDir, $simulate, $noBackup, $io);
        }

        $rawLogfile = (string) $input->getArgument('logfile');
        $logfile = str_replace('%kernel.project_dir%', $projectDir, $rawLogfile);

        if (!file_exists($logfile)) {
            $io->error('Fichier de log introuvable: ' . $logfile);
            $io->note('Pour seulement formater/linter les fichiers de traduction existants sans fichier de log, utilisez l\'option --format :');
            $io->text('  php bin/console app:translations:import-missing --format');
            return Command::FAILURE;
        }

        return $this->importFromLogfile($logfile, $translationsDir, $simulate, $overwrite, $noBackup, $io);
    }

    private function formatTranslationsFiles(string $translationsDir, bool $simulate, bool $noBackup, SymfonyStyle $io): int
    {
        $io->title('Reformatage et vérification des fichiers de traduction YAML');

        $finder = new Finder();
        $finder->files()->in($translationsDir)->name(['*.yaml', '*.yml']);

        if (!$finder->hasResults()) {
            $io->warning('Aucun fichier YAML trouvé dans ' . $translationsDir);
            return Command::SUCCESS;
        }

        $reformattedCount = 0;
        $unchangedCount = 0;
        $errorCount = 0;

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            $relativePath = $file->getRelativePathname();

            try {
                $existingContent = (string) file_get_contents($filePath);
                $parsed = Yaml::parse($existingContent);

                if (!is_array($parsed)) {
                    $parsed = [];
                }

                $this->sortRecursively($parsed);
                $newContent = $this->dumpYaml($parsed);

                if ($existingContent === $newContent) {
                    $unchangedCount++;
                    if ($io->isVerbose()) {
                        $io->text(sprintf('  [OK] Déjà conforme : %s', $relativePath));
                    }
                    continue;
                }

                $reformattedCount++;
                if ($simulate) {
                    $io->note(sprintf('[SIMULATION] Serait reformaté : %s', $relativePath));
                } else {
                    if (!$noBackup) {
                        $this->createBackup($filePath, $io);
                    }
                    file_put_contents($filePath, $newContent);
                    $io->text(sprintf('  [✓] Reformaté : %s', $relativePath));
                }
            } catch (ParseException $e) {
                $errorCount++;
                $io->error(sprintf('Erreur de syntaxe YAML dans %s : %s', $relativePath, $e->getMessage()));
            }
        }

        $io->newLine();
        if ($errorCount > 0) {
            $io->warning(sprintf('%d fichier(s) reformaté(s), %d fichier(s) inchangé(s), %d erreur(s) détectée(s).', $reformattedCount, $unchangedCount, $errorCount));
            return Command::FAILURE;
        }

        $io->success(sprintf('Reformatage terminé : %d fichier(s) reformaté(s), %d déjà conforme(s).', $reformattedCount, $unchangedCount));
        return Command::SUCCESS;
    }

    private function importFromLogfile(string $logfile, string $translationsDir, bool $simulate, bool $overwrite, bool $noBackup, SymfonyStyle $io): int
    {
        $content = (string) file_get_contents($logfile);
        $lines = preg_split('/\r?\n/', $content) ?: [];

        $missing = []; // keyed by domain.locale => set of keys

        foreach ($lines as $line) {
            if (!trim($line)) {
                continue;
            }
            // cherche le JSON-like context {"id":"...","domain":"...","locale":"fr"}
            if (preg_match('/\{"id":"(?P<id>[^\"]+)","domain":"(?P<domain>[^\"]*)","locale":"(?P<locale>[^\"]+)"}/', $line, $m)) {
                $id = $m['id'];
                $domain = $m['domain'] ?: 'messages';
                $locale = $m['locale'] ?: 'fr';
                $key = $domain . '.' . $locale;
                $missing[$key][$id] = $id; // valeur = id for now
            }
        }

        if (count($missing) === 0) {
            $io->warning('Aucune clé manquante trouvée dans le fichier de log.');
            return Command::SUCCESS;
        }

        foreach ($missing as $dk => $keys) {
            [$domain, $locale] = explode('.', $dk, 2);
            $fileCandidates = [
                $translationsDir . DIRECTORY_SEPARATOR . $domain . '.' . $locale . '.yaml',
                $translationsDir . DIRECTORY_SEPARATOR . $domain . '.' . $locale . '.yml',
            ];

            $file = null;
            foreach ($fileCandidates as $c) {
                if (file_exists($c)) {
                    $file = $c;
                    break;
                }
            }

            if ($file === null) {
                // use first candidate
                $file = $fileCandidates[0];
                $existing = [];
            } else {
                $existing = Yaml::parseFile($file) ?: [];
            }

            $toAdd = [];
            foreach ($keys as $id => $val) {
                if (array_key_exists($id, $existing) && !$overwrite) {
                    continue;
                }
                $toAdd[$id] = $val;
            }

            if (count($toAdd) === 0) {
                $io->text(sprintf('Aucune nouvelle clé à ajouter pour %s (%s)', $domain, $locale));
                continue;
            }

            $io->section(sprintf('%s (%s) -> %s : %d clés à ajouter', $domain, $locale, $file, count($toAdd)));

            // merge and recursive sort
            $merged = $existing + $toAdd;
            $this->sortRecursively($merged);

            if ($simulate) {
                $io->listing(array_keys($toAdd));
            } else {
                if (file_exists($file) && !$noBackup) {
                    $this->createBackup($file, $io);
                }

                $yaml = $this->dumpYaml($merged);
                file_put_contents($file, $yaml);
                $io->success(sprintf('Fichier mis à jour : %s', $file));
            }
        }

        $io->success('Import terminé.');
        return Command::SUCCESS;
    }

    private function sortRecursively(array &$array): void
    {
        ksort($array);
        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->sortRecursively($value);
            }
        }
    }

    private function dumpYaml(array $data): string
    {
        return Yaml::dump($data, self::YAML_INLINE_LEVEL, self::YAML_INDENT_SPACES, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK) . "\n";
    }

    private function createBackup(string $file, SymfonyStyle $io): void
    {
        $backupFile = $file . '.bak.' . date('Ymd_His');
        if (!copy($file, $backupFile)) {
            $io->warning(sprintf('Impossible de créer la sauvegarde du fichier %s', $file));
        } elseif ($io->isVerbose()) {
            $io->text(sprintf('Backup créé : %s', $backupFile));
        }
    }
}


