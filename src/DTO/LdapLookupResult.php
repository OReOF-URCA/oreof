<?php

declare(strict_types=1);

namespace App\DTO;

final class LdapLookupResult
{
    public const STATUS_OK = 'ok';
    public const STATUS_NOT_FOUND = 'not_found';
    public const STATUS_MULTIPLE = 'multiple';
    public const STATUS_CONNECTION_ERROR = 'connection_error';
    public const STATUS_QUERY_ERROR = 'query_error';
    public const STATUS_INVALID_EMAIL = 'invalid_email';
    public const STATUS_INCOMPLETE_DATA = 'incomplete_data';

    /**
     * @param array{username: string, nom: string, prenom: string, email: string}|null $data
     */
    public function __construct(
        private readonly string      $status,
        private readonly ?string     $errorMessage = null,
        private readonly ?array      $data = null,
        private readonly ?int        $count = null,
        private readonly ?\Throwable $exception = null
    )
    {
    }

    /**
     * @param array{username: string, nom: string, prenom: string, email: string} $data
     */
    public static function ok(array $data): self
    {
        return new self(self::STATUS_OK, null, $data, 1);
    }

    public static function notFound(string $email): self
    {
        return new self(
            self::STATUS_NOT_FOUND,
            sprintf("Aucun utilisateur trouvé dans l'annuaire LDAP pour l'adresse « %s ».", $email),
            null,
            0
        );
    }

    public static function multiple(string $email, int $count): self
    {
        return new self(
            self::STATUS_MULTIPLE,
            sprintf("Plusieurs comptes (%d) correspondent à l'adresse « %s » dans l'annuaire LDAP. Impossible de déterminer le bon compte automatiquement.", $count, $email),
            null,
            $count
        );
    }

    public static function connectionError(string $host, string $detail, ?\Throwable $e = null): self
    {
        $friendlyDetail = trim($detail);
        if ($friendlyDetail === '' || stripos($friendlyDetail, "Can't contact LDAP server") !== false) {
            $friendlyDetail = 'serveur LDAP injoignable (vérifiez votre connexion réseau ou l\'accès VPN)';
        }

        return new self(
            self::STATUS_CONNECTION_ERROR,
            sprintf("Impossible de se connecter au serveur LDAP (%s) : %s.", $host !== '' ? $host : 'hôte non configuré', $friendlyDetail),
            null,
            null,
            $e
        );
    }

    public static function queryError(string $detail, ?\Throwable $e = null): self
    {
        return new self(
            self::STATUS_QUERY_ERROR,
            sprintf("Erreur lors de la recherche dans l'annuaire LDAP : %s.", $detail),
            null,
            null,
            $e
        );
    }

    public static function invalidEmail(string $email): self
    {
        return new self(
            self::STATUS_INVALID_EMAIL,
            sprintf("L'adresse email « %s » n'est pas valide.", $email),
            null,
            0
        );
    }

    public static function incompleteData(string $email, string $missing): self
    {
        return new self(
            self::STATUS_INCOMPLETE_DATA,
            sprintf("L'entrée LDAP trouvée pour « %s » est incomplète (identifiant « %s » manquant).", $email, $missing),
            null,
            1
        );
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_OK;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * @return array{username: string, nom: string, prenom: string, email: string}|null
     */
    public function getData(): ?array
    {
        return $this->data;
    }

    public function getCount(): ?int
    {
        return $this->count;
    }

    public function getException(): ?\Throwable
    {
        return $this->exception;
    }
}
