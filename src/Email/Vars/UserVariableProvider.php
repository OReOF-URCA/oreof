<?php
declare(strict_types=1);

namespace App\Email\Vars;

final class UserVariableProvider implements VariableProviderInterface
{
    public function getNamespace(): string
    {
        return 'user';
    }

    public function provide(array $context): array
    {
        $u = $context['user'] ?? null;

        // Tenter d'accéder via méthodes usuelles, sinon arrays
        $fullName = null;
        $email = null;

        if (is_object($u)) {
            $fullName = method_exists($u, 'getDisplay') ? $u->getDisplay() : null;
            if ($fullName === null && method_exists($u, 'getFirstname') && method_exists($u, 'getLastname')) {
                $fullName = trim((string)$u->getFirstname() . ' ' . $u->getLastname());
            }
            $email = method_exists($u, 'getEmail') ? $u->getEmail() : null;
        } elseif (is_array($u)) {
            $fullName = $u['fullName'] ?? ($u['firstname'] ?? '') . ' ' . ($u['lastname'] ?? '');
            $email = $u['email'] ?? null;
        }

        return [
            'fullName' => $fullName ?: null,
            'email' => $email ?: null,
        ];
    }

    public function describe(): array
    {
        return [
            'user.fullName' => 'Nom complet de l’utilisateur',
            'user.email' => 'Adresse e-mail de l’utilisateur',
        ];
    }

    public function previewDefaults(): array
    {
        return [
            'fullName' => 'Alice Dupont',
            'email' => 'alice@example.org',
        ];
    }
}
