<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Form/Type/ColorVariantType.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 10/06/2026 14:47
 */

// src/Form/Type/ColorVariantType.php
namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ColorVariantType extends AbstractType
{
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['show_labels'] = $options['show_labels'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => [
                'Primaire' => 'primary',
                'Secondaire' => 'secondary',
                'Succès' => 'success',
                'Information' => 'info',
                'Attention' => 'warning',
                'Danger' => 'danger',
            ],
            'expanded' => true,
            'multiple' => false,
            'show_labels' => true,
        ]);

        $resolver->setAllowedTypes('show_labels', 'bool');

    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }
}
