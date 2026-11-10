<?php

namespace App\Tests\Unit\Controller;

use App\Controller\FormationController;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Forms;

final class FormationFormErrorTest extends TestCase
{
    private FormationController $controller;
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $this->controller = new FormationController($em);
        $this->reflection = new ReflectionClass(FormationController::class);
    }

    public function testGetFormErrorsMessageDetectsMissingAndInvalidFields(): void
    {
        $factory = Forms::createFormFactory();
        $form = $factory->createBuilder(FormType::class)
            ->add('domaine', ChoiceType::class, [
                'label' => 'Domaine d\'études',
                'choices' => ['Sciences' => 'sciences'],
            ])
            ->add('composantePorteuse', TextType::class)
            ->add('codeRNCP', TextType::class, [
                'label' => 'Code RNCP',
            ])
            ->getForm();

        // Simulate missing required field errors
        $form->get('domaine')->addError(new FormError('Cette valeur ne doit pas être nulle.'));
        $form->get('composantePorteuse')->addError(new FormError('Cette valeur ne doit pas être vide.'));
        // Simulate custom invalid value error
        $form->get('codeRNCP')->addError(new FormError('Le format du code RNCP est invalide (RNCPxxxxx attendu).'));

        $method = $this->reflection->getMethod('getFormErrorsMessage');
        /** @var string $message */
        $message = $method->invoke($this->controller, $form);

        self::assertStringContainsString('Champs obligatoires manquants', $message);
        self::assertStringContainsString('Domaine d\'études', $message);
        self::assertStringContainsString('Composante', $message);
        self::assertStringContainsString('Autre erreur', $message);
        self::assertStringContainsString('Code RNCP : Le format du code RNCP est invalide (RNCPxxxxx attendu).', $message);
    }

    public function testGetFormErrorsListReturnsFormattedList(): void
    {
        $factory = Forms::createFormFactory();
        $form = $factory->createBuilder(FormType::class)
            ->add('mention', TextType::class)
            ->getForm();

        $form->get('mention')->addError(new FormError('Cette valeur ne doit pas être vide.'));

        $method = $this->reflection->getMethod('getFormErrorsList');
        /** @var list<string> $list */
        $list = $method->invoke($this->controller, $form);

        self::assertCount(1, $list);
        self::assertSame('Mention : Ce champ est obligatoire.', $list[0]);
    }
}
