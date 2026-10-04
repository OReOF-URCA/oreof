<?php
/*
 * Copyright (c) 2021. | David Annebicque | IUT de Troyes  - All Rights Reserved
 * @file /Users/davidannebicque/htdocs/intranetV3/src/Form/Extension/FormTypeExtension.php
 * @author davidannebicque
 * @project intranetV3
 * @lastUpdate 29/08/2021 21:50
 */

namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FormTypeExtension extends AbstractTypeExtension
{

    /**
     * {@inheritdoc}
     */
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['input_prefix'] = $options['input_prefix'];
        $view->vars['input_suffix'] = $options['input_suffix'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefault('input_prefix', null)
            ->setAllowedTypes('input_prefix', ['null', 'string'])
            ->setNormalizer('input_prefix', function (Options $options, $value) {
                if ($options['input_prefix_text']) {
                    return sprintf('<span class="input-group-text">%s</span>', $options['input_prefix_text']);
                }

                return $value;
            })
            ->setDefault('input_suffix', null)
            ->setAllowedTypes('input_suffix', ['null', 'string'])
            ->setNormalizer('input_suffix', function (Options $options, $value) {
                if ($options['input_suffix_text']) {
                    return sprintf('<span class="input-group-text">%s</span>', $options['input_suffix_text']);
                }

                return $value;
            })
            ->setDefault('input_prefix_text', null)
            ->setAllowedTypes('input_prefix_text', ['null', 'string'])
            ->setDefault('input_suffix_text', null)
            ->setAllowedTypes('input_suffix_text', ['null', 'string']);
    }
}
