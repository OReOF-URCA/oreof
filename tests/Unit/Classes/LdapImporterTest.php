<?php

declare(strict_types=1);

namespace App\Tests\Unit\Classes;

use App\Classes\Ldap;
use App\Classes\LdapImporter;
use App\Entity\User;
use App\Repository\UserRepository;
use PHPUnit\Framework\TestCase;

final class LdapImporterTest extends TestCase
{
    public function testAddFromLdapReturnsExistingUserByEmail(): void
    {
        $existing = new User();
        $existing->setEmail('existing@univ-reims.fr');

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects(self::once())
            ->method('findOneBy')
            ->with(['email' => 'existing@univ-reims.fr'])
            ->willReturn($existing);

        $ldap = $this->createMock(Ldap::class);
        $ldap->expects(self::never())->method('getDatas');

        $importer = new LdapImporter($ldap, $userRepo);
        $result = $importer->addFromLdap('existing@univ-reims.fr');

        self::assertSame($existing, $result);
    }

    public function testAddFromLdapReturnsExistingUserByUsernameWhenEmailDiffers(): void
    {
        $existing = new User();
        $existing->setEmail('old-email@univ-reims.fr');
        $existing->setUsername('jdupont');

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects(self::exactly(2))
            ->method('findOneBy')
            ->willReturnCallback(static function (array $criteria) use ($existing) {
                if (isset($criteria['email']) && $criteria['email'] === 'new-alias@univ-reims.fr') {
                    return null;
                }
                if (isset($criteria['username']) && $criteria['username'] === 'jdupont') {
                    return $existing;
                }
                return null;
            });

        $ldap = $this->createMock(Ldap::class);
        $ldap->expects(self::once())
            ->method('getDatas')
            ->with('new-alias@univ-reims.fr')
            ->willReturn([
                'username' => 'jdupont',
                'nom' => 'Dupont',
                'prenom' => 'Jean',
            ]);

        $userRepo->expects(self::never())->method('save');

        $importer = new LdapImporter($ldap, $userRepo);
        $result = $importer->addFromLdap('new-alias@univ-reims.fr');

        self::assertSame($existing, $result);
    }
}
