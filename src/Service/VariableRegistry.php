<?php
declare(strict_types=1);

namespace App\Service;

use App\Email\Vars\VariableProviderInterface;

/**
 * Agrège les providers et construit un contexte "safe" pour Twig.
 * Gère:
 *  - mode runtime (valeurs réelles)
 *  - mode preview (complète avec defaults + overrides)
 * Le résultat est un array associatif par "namespace".
 */
final class VariableRegistry
{
    /**
     * @param iterable<VariableProviderInterface> $providers
     */
    public function __construct(
        private readonly iterable $providers
    )
    {
    }

    /**
     * @param 'runtime'|'preview' $mode
     * @param array<string,mixed> $rawContext
     * @param array<string,array<string,mixed>> $previewOverrides
     * @return array<string,mixed> ex: ['user'=>['fullName'=>'...'], 'formation'=>['name'=>'...']]
     */
    public function resolveForKey(
        string $key,
        array  $rawContext,
        string $mode = 'runtime',
        array  $previewOverrides = []
    ): array
    {
        $out = [];

        foreach ($this->providers as $p) {
            $ns = $p->getNamespace();

            // 1) variables depuis le contexte
            $vars = $p->provide($rawContext);

            // 2) en preview, compléter les valeurs manquantes avec des defaults
            if ($mode === 'preview') {
                $vars = $this->fillMissing($vars, $p->previewDefaults());
            }

            // 3) overrides de preview envoyés par l'UI
            if ($mode === 'preview' && isset($previewOverrides[$ns]) && is_array($previewOverrides[$ns])) {
                $vars = array_replace($vars, $previewOverrides[$ns]);
            }

            $out[$ns] = $vars;
        }

        return $out;
    }

    /**
     * Complète $base avec $defaults uniquement si valeur absente, null ou ''.
     * @param array<string,mixed> $base
     * @param array<string,mixed> $defaults
     * @return array<string,mixed>
     */
    private function fillMissing(array $base, array $defaults): array
    {
        foreach ($defaults as $k => $v) {
            if (!array_key_exists($k, $base) || $base[$k] === null || $base[$k] === '') {
                $base[$k] = $v;
            }
        }
        return $base;
    }

    /**
     * Liste globale des variables (clé plate => description) pour l'UI.
     * @return array<string,string>
     */
    public function describeAll(): array
    {
        $desc = [];
        foreach ($this->providers as $p) {
            foreach ($p->describe() as $k => $v) {
                $desc[$k] = $v;
            }
        }
        ksort($desc);
        return $desc;
    }
}
