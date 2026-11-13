<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Classes/Ldap.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Classes;

use App\DTO\LdapLookupResult;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class Ldap
{
    private ?\Symfony\Component\Ldap\Ldap $ds = null;

    public function __construct(private readonly ParameterBagInterface $parameterBag)
    {
    }

    public function connect(): void
    {
        if ($this->ds !== null) {
            return;
        }

        $host = (string)$this->parameterBag->get('LDAP_HOST');
        $login = (string)$this->parameterBag->get('LDAP_LOGIN');
        $password = (string)$this->parameterBag->get('LDAP_PASSWORD');

        $this->ds = \Symfony\Component\Ldap\Ldap::create(
            'ext_ldap',
            ['connection_string' => $host, 'version' => 3]
        );
        $this->ds->bind($login, $password);
    }

    public function lookupUser(?string $email): LdapLookupResult
    {
        $email = trim((string)$email);
        if ($email === '' || !filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return LdapLookupResult::invalidEmail($email);
        }

        $host = (string)$this->parameterBag->get('LDAP_HOST');

        try {
            $this->connect();
        } catch (\Throwable $e) {
            return LdapLookupResult::connectionError($host, $e->getMessage(), $e);
        }

        if ($this->ds === null) {
            return LdapLookupResult::connectionError($host, 'Connexion LDAP non initialisée.');
        }

        try {
            $baseDn = (string)$this->parameterBag->get('LDAP_BASE_DN');
            $escapedEmail = function_exists('ldap_escape')
                ? ldap_escape($email, '', \LDAP_ESCAPE_FILTER)
                : str_replace(['\\', '*', '(', ')', "\x00"], ['\\5c', '\\2a', '\\28', '\\29', '\\00'], $email);

            $query = $this->ds->query(
                $baseDn,
                '(mail=' . $escapedEmail . ')',
                ['filter' => ['uid', 'sn', 'givenName']]
            );
            $results = $query->execute()->toArray();
        } catch (\Throwable $e) {
            return LdapLookupResult::queryError($e->getMessage(), $e);
        }

        $count = count($results);
        if ($count === 0) {
            return LdapLookupResult::notFound($email);
        }

        if ($count > 1) {
            return LdapLookupResult::multiple($email, $count);
        }

        $entry = $results[0];
        $uid = $entry->hasAttribute('uid') ? ($entry->getAttribute('uid')[0] ?? null) : null;
        if (empty($uid)) {
            return LdapLookupResult::incompleteData($email, 'uid');
        }

        $nom = $entry->hasAttribute('sn') ? ((string)($entry->getAttribute('sn')[0] ?? '')) : '';
        $prenom = $entry->hasAttribute('givenName') ? ((string)($entry->getAttribute('givenName')[0] ?? '')) : '';

        return LdapLookupResult::ok([
            'username' => (string)$uid,
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
        ]);
    }

    public function getUsername(?string $email): ?string
    {
        $result = $this->lookupUser($email);
        return $result->isSuccess() ? ($result->getData()['username'] ?? null) : null;
    }

    /**
     * @return array{username: string, nom: string, prenom: string, email: string}|null
     */
    public function getDatas(?string $email): ?array
    {
        $result = $this->lookupUser($email);
        return $result->isSuccess() ? $result->getData() : null;
    }

    public function emailExists(string $email): bool
    {
        return $this->lookupUser($email)->isSuccess();
    }
}
