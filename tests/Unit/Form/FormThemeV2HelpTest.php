<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Twig\Environment;

class FormThemeV2HelpTest extends KernelTestCase
{
    private Environment $twig;
    private FormFactoryInterface $formFactory;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->twig = $container->get('twig');
        $this->formFactory = $container->get('form.factory');
    }

    public function testHelpRenderingWithTranslationsAndFallbacks(): void
    {
        $form = $this->formFactory->createNamedBuilder('change_rf_formation', FormType::class)
            // Field with InlineCreateEntitySelectType and existing translation in help.fr.yaml
            ->add('user', \App\Form\Type\InlineCreateEntitySelectType::class, [
                'class' => \App\Entity\User::class,
                'choice_label' => 'email',
            ])
            // Field with no translation anywhere
            ->add('typeRf', ChoiceType::class, [
                'choices' => ['A' => 'a'],
                'expanded' => true,
            ])
            // Field with explicit text help
            ->add('explicitField', TextType::class, [
                'help' => 'Texte direct d\'aide',
            ])
            // Field with explicitly empty help
            ->add('disabledField', TextType::class, [
                'help' => '',
            ])
            ->getForm();

        $view = $form->createView();

        $template = $this->twig->createTemplate('
            {% form_theme form "communs/form_theme_v2.html.twig" %}
            {{ form_row(form.user) }}
            {{ form_row(form.typeRf) }}
            {{ form_row(form.explicitField) }}
            {{ form_row(form.disabledField) }}
        ');

        $html = $template->render(['form' => $view]);

        // 1. user field should display translated message from help.fr.yaml, NOT raw key
        $this->assertStringContainsString('id="change_rf_formation_user_help"', $html);
        $this->assertStringContainsString('Sélectionnez le nouveau responsable de la formation.', $html);
        $this->assertStringNotContainsString('change_rf_formation.helps.user.help', $html);
        $this->assertStringNotContainsString('change_rf_formation.helps.user</span>', $html);

        // 2. typeRf has no help in help.fr.yaml: NO help element should be rendered
        $this->assertStringNotContainsString('id="change_rf_formation_typeRf_help"', $html);
        $this->assertStringNotContainsString('change_rf_formation.helps.typeRf', $html);

        // 3. explicit help field should display the text
        $this->assertStringContainsString('id="change_rf_formation_explicitField_help"', $html);
        $this->assertStringContainsString('Texte direct d\'aide', $html);

        // 4. disabled field should not render help
        $this->assertStringNotContainsString('id="change_rf_formation_disabledField_help"', $html);
    }

    public function testInlineCreateEntitySelectTypeWithAutocomplete(): void
    {
        $form = $this->formFactory->createNamedBuilder('test_autocomplete_form', FormType::class)
            ->add('user', \App\Form\Type\InlineCreateEntitySelectType::class, [
                'class' => \App\Entity\User::class,
                'choice_label' => 'email',
                'autocomplete' => true,
            ])
            ->getForm();

        $view = $form->createView();

        $template = $this->twig->createTemplate('
            {% form_theme form "communs/form_theme_v2.html.twig" %}
            {{ form_row(form.user) }}
        ');

        $html = $template->render(['form' => $view]);

        $this->assertStringContainsString('data-controller="inline-create"', $html);
        $this->assertStringContainsString('symfony--ux-autocomplete--autocomplete', $html);
        $this->assertStringContainsString('data-inline-create-target="select"', $html);
        $this->assertStringContainsString('data-inline-create-target="newInput"', $html);
    }
}
