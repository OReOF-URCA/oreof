<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\LdapLookupResult;
use PHPUnit\Framework\TestCase;

final class LdapLookupResultTest extends TestCase
{
    public function testOkResult(): void
    {
        $data = [
            'username' => 'jdupont',
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'email' => 'jean.dupont@univ-reims.fr',
        ];

        $result = LdapLookupResult::ok($data);

        self::assertTrue($result->isSuccess());
        self::assertSame(LdapLookupResult::STATUS_OK, $result->getStatus());
        self::assertNull($result->getErrorMessage());
        self::assertSame($data, $result->getData());
        self::assertSame(1, $result->getCount());
    }

    public function testNotFoundResult(): void
    {
        $result = LdapLookupResult::notFound('inconnu@univ-reims.fr');

        self::assertFalse($result->isSuccess());
        self::assertSame(LdapLookupResult::STATUS_NOT_FOUND, $result->getStatus());
        self::assertStringContainsString('inconnu@univ-reims.fr', (string)$result->getErrorMessage());
        self::assertNull($result->getData());
        self::assertSame(0, $result->getCount());
    }

    public function testMultipleResult(): void
    {
        $result = LdapLookupResult::multiple('doublon@univ-reims.fr', 2);

        self::assertFalse($result->isSuccess());
        self::assertSame(LdapLookupResult::STATUS_MULTIPLE, $result->getStatus());
        self::assertStringContainsString('Plusieurs comptes (2)', (string)$result->getErrorMessage());
        self::assertSame(2, $result->getCount());
    }

    public function testConnectionErrorWithFriendlyFallback(): void
    {
        $exception = new \RuntimeException("Can't contact LDAP server");
        $result = LdapLookupResult::connectionError('ldap://ldap.univ-reims.fr', "Can't contact LDAP server", $exception);

        self::assertFalse($result->isSuccess());
        self::assertSame(LdapLookupResult::STATUS_CONNECTION_ERROR, $result->getStatus());
        self::assertStringContainsString('Impossible de se connecter au serveur LDAP (ldap://ldap.univ-reims.fr)', (string)$result->getErrorMessage());
        self::assertStringContainsString('serveur LDAP injoignable', (string)$result->getErrorMessage());
        self::assertSame($exception, $result->getException());
    }

    public function testInvalidEmailResult(): void
    {
        $result = LdapLookupResult::invalidEmail('not-an-email');

        self::assertFalse($result->isSuccess());
        self::assertSame(LdapLookupResult::STATUS_INVALID_EMAIL, $result->getStatus());
        self::assertStringContainsString('not-an-email', (string)$result->getErrorMessage());
    }

    public function testIncompleteDataResult(): void
    {
        $result = LdapLookupResult::incompleteData('jean@univ-reims.fr', 'uid');

        self::assertFalse($result->isSuccess());
        self::assertSame(LdapLookupResult::STATUS_INCOMPLETE_DATA, $result->getStatus());
        self::assertStringContainsString('uid', (string)$result->getErrorMessage());
    }

    public function testQueryErrorResult(): void
    {
        $result = LdapLookupResult::queryError('Syntax error in filter');

        self::assertFalse($result->isSuccess());
        self::assertSame(LdapLookupResult::STATUS_QUERY_ERROR, $result->getStatus());
        self::assertStringContainsString('Syntax error in filter', (string)$result->getErrorMessage());
    }
}
