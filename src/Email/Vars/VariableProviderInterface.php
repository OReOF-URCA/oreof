<?php
declare(strict_types=1);

namespace App\Email\Vars;

/**
 * Chaque provider expose un "namespace" (user, formation, etc.)
 * et sait fournir ses variables depuis un contexte brut.
 * Il expose aussi des valeurs par défaut pour la PREVIEW.
 */
interface VariableProviderInterface
{
    /** Namespace logique, ex: "user", "formation" */
    public function getNamespace(): string;

    /**
     * Construit les variables depuis le contexte (runtime).
     * Retourner uniquement des scalaires/arrays simples (pas d'objets Doctrine).
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function provide(array $context): array;

    /**
     * Documentation clé => description (pour l'UI).
     * Exemple: ['user.fullName' => 'Nom complet de l’utilisateur']
     * @return array<string,string>
     */
    public function describe(): array;

    /**
     * Valeurs par défaut pour la PREVIEW (ex. 'Alice Dupont'…).
     * @return array<string,mixed>
     */
    public function previewDefaults(): array;
}
