<?php
// src/Service/TranslationFileManager.php
namespace App\Service;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Finder\Finder;

final class TranslationFileManager
{
    private string $path;

    public function __construct(
        protected string $projectDir,
        protected string $cacheDir
    )
    {
        $this->path = $projectDir . '/translations';
    }

    /** @return string[] */
    public function listFiles(): array
    {
        $finder = (new Finder())->files()->in($this->path)->name('*.yaml');
        return iterator_to_array($finder->getIterator());
    }

    public function read(string $filename): array
    {
        $full = $this->path . '/' . $filename;
        if (!is_file($full)) {
            throw new \RuntimeException(sprintf('File %s not found', $filename));
        }

        $data = Yaml::parseFile($full);
        return $this->flatten($data ?? []);
    }

    private function flatten(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix === '' ? $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $result += $this->flatten($value, $newKey);
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    public function write(string $filename, array $translations): void
    {
        $full = $this->path . '/' . $filename;
        copy($full, $full . '.bak_' . date('Ymd_His'));
        ksort($translations);
        file_put_contents($full, Yaml::dump($translations, 2, 4));
        $this->invalidateTranslationCache();
    }

    private function invalidateTranslationCache(): void
    {
        $cacheDir = $this->cacheDir;
        $translationCache = $cacheDir . '/translations';
        if (is_dir($translationCache)) {
            $files = glob($translationCache . '/*');
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }

}
