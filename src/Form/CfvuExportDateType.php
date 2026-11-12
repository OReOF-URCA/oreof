<?php

declare(strict_types=1);

namespace App\Form;

use App\Form\Type\CfvuDateType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

final class CfvuExportDateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('dateCfvu', CfvuDateType::class, [
            'label' => 'Date de la CFVU',
            'required' => true,
            'constraints' => [
                new NotNull(message: 'La date de la CFVU est obligatoire.'),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => ['id' => 'modal_form'],
            'csrf_protection' => false,
            'translation_domain' => 'form',
        ]);
    }
}

