<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form;

use App\Entity\Domaine;
use App\Entity\User;
use App\Form\DomaineType;
use App\Form\UserAddType;
use App\Form\UserHorsUrcaType;
use App\Form\UserLdapType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

class UserFormValidationTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        $validator = Validation::createValidator();

        return [
            new ValidatorExtension($validator),
        ];
    }

    public function testUserHorsUrcaTypeValid(): void
    {
        $user = new User();
        $form = $this->factory->create(UserHorsUrcaType::class, $user);
        $form->submit([
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'email' => 'jean.dupont@ext.fr',
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        $this->assertSame('DUPONT', $user->getNom());
        $this->assertSame('Jean', $user->getPrenom());
        $this->assertSame('jean.dupont@ext.fr', $user->getEmail());
    }

    public function testUserHorsUrcaTypeInvalidWhenBlankOrBadEmail(): void
    {
        $user = new User();
        $form = $this->factory->create(UserHorsUrcaType::class, $user);
        $form->submit([
            'nom' => '',
            'prenom' => '',
            'email' => 'invalid-email',
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid());
        $this->assertGreaterThanOrEqual(1, count($form->get('nom')->getErrors()));
        $this->assertGreaterThanOrEqual(1, count($form->get('prenom')->getErrors()));
        $this->assertGreaterThanOrEqual(1, count($form->get('email')->getErrors()));
    }

    public function testUserLdapTypeValid(): void
    {
        $user = new User();
        $form = $this->factory->create(UserLdapType::class, $user);
        $form->submit([
            'email' => 'jean.dupont@univ-reims.fr',
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        $this->assertSame('jean.dupont@univ-reims.fr', $user->getEmail());
    }

    public function testUserLdapTypeInvalidEmail(): void
    {
        $user = new User();
        $form = $this->factory->create(UserLdapType::class, $user);
        $form->submit([
            'email' => '',
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid());
        $this->assertGreaterThanOrEqual(1, count($form->get('email')->getErrors()));
    }

    public function testUserAddTypeValid(): void
    {
        $user = new User();
        $form = $this->factory->create(UserAddType::class, $user);
        $form->submit([
            'email' => 'contact@test.fr',
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
    }

    public function testUserAddTypeInvalid(): void
    {
        $user = new User();
        $form = $this->factory->create(UserAddType::class, $user);
        $form->submit([
            'email' => 'not-an-email',
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid());
    }

    public function testDomaineTypeValid(): void
    {
        $domaine = new Domaine();
        $form = $this->factory->create(DomaineType::class, $domaine);
        $form->submit([
            'libelle' => 'Sciences et Technologies',
            'sigle' => 'ST',
            'codeApogee' => 'S',
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        $this->assertSame('Sciences et Technologies', $domaine->getLibelle());
        $this->assertSame('ST', $domaine->getSigle());
        $this->assertSame('S', $domaine->getCodeApogee());
    }

    public function testDomaineTypeInvalidLength(): void
    {
        $domaine = new Domaine();
        $form = $this->factory->create(DomaineType::class, $domaine);
        $form->submit([
            'libelle' => '',
            'sigle' => '',
            'codeApogee' => 'TOOLONG',
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid());
        $this->assertGreaterThanOrEqual(1, count($form->get('libelle')->getErrors()));
        $this->assertGreaterThanOrEqual(1, count($form->get('sigle')->getErrors()));
        $this->assertGreaterThanOrEqual(1, count($form->get('codeApogee')->getErrors()));
    }
}
