<?php

namespace App\Form\Type;

use App\Form\DataTransformer\JsonToKeyValueTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Type de formulaire pour gérer des champs de configuration JSON
 * de manière accessible aux non-développeurs.
 *
 * Transforme un tableau JSON en textarea éditable avec validation.
 */
class JsonConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new JsonToKeyValueTransformer());
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['add_button_text'] = $options['add_button_text'];
        $view->vars['help_text'] = $options['help_text'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'add_button_text' => 'Ajouter une configuration',
            'help_text' => null,
            'required' => false,
        ]);

        $resolver->setAllowedTypes('add_button_text', 'string');
        $resolver->setAllowedTypes('help_text', ['null', 'string']);
    }

    public function getParent(): string
    {
        return TextareaType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'json_config';
    }
}
