<?php

declare(strict_types=1);

namespace App\Tests\Unit\Classes;

use App\Classes\Ldap;
use App\DTO\LdapLookupResult;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class LdapTest extends TestCase
{
    public function testLookupUserRejectsInvalidEmailFormat(): void
    {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->expects(self::never())->method('get');

        $ldap = new Ldap($parameterBag);
        $result = $ldap->lookupUser('invalid-email');

        self::assertFalse($result->isSuccess());
        self::assertSame(LdapLookupResult::STATUS_INVALID_EMAIL, $result->getStatus());
        self::assertStringContainsString('invalid-email', (string)$result->getErrorMessage());
        self::assertNull($ldap->getDatas('invalid-email'));
        self::assertNull($ldap->getUsername('invalid-email'));
        self::assertFalse($ldap->emailExists('invalid-email'));
    }

    public function testLookupUserRejectsEmptyEmail(): void
    {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->expects(self::never())->method('get');

        $ldap = new Ldap($parameterBag);
        $result = $ldap->lookupUser('   ');

        self::assertFalse($result->isSuccess());
        self::assertSame(LdapLookupResult::STATUS_INVALID_EMAIL, $result->getStatus());
    }

    public function testLookupUserHandlesConnectionErrorCleanly(): void
    {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturnCallback(static function (string $key): string {
            return match ($key) {
                'LDAP_HOST' => 'ldap://invalid-host-that-does-not-exist.test',
                'LDAP_LOGIN' => 'uid=admin,dc=test',
                'LDAP_PASSWORD' => 'secret',
                'LDAP_BASE_DN' => 'dc=test',
                default => '',
            };
        });

        $ldap = new Ldap($parameterBag);
        $result = $ldap->lookupUser('test.user@univ-reims.fr');

        self::assertFalse($result->isSuccess());
        self::assertSame(LdapLookupResult::STATUS_CONNECTION_ERROR, $result->getStatus());
        self::assertStringContainsString('Impossible de se connecter au serveur LDAP (ldap://invalid-host-that-does-not-exist.test)', (string)$result->getErrorMessage());
        self::assertNull($ldap->getDatas('test.user@univ-reims.fr'));
        self::assertNull($ldap->getUsername('test.user@univ-reims.fr'));
        self::assertFalse($ldap->emailExists('test.user@univ-reims.fr'));
    }
}
