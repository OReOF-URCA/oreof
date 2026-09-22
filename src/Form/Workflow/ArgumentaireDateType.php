<?php

declare(strict_types=1);

namespace App\Form\Workflow;

use App\DTO\Workflow\ArgumentaireDateData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

final class ArgumentaireDateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('argumentaire', TextareaType::class, [
                'label' => 'Argumentaire',
                'attr' => ['rows' => 6],
                'constraints' => [new NotBlank(message: 'L\'argumentaire est obligatoire.')],
            ])
            ->add($options['date_property'], DateType::class, [
                'widget' => 'single_text',
                'label' => $options['date_label'],
                'constraints' => [new NotNull(message: 'La date est obligatoire.')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ArgumentaireDateData::class,
            'translation_domain' => 'process',
            'attr' => ['id' => 'modal_form'],
            'date_label' => 'Date',
        ]);
        $resolver->setRequired('date_property');
        $resolver->setAllowedValues('date_property', ['dateConseil', 'dateCfvu']);
        $resolver->setAllowedTypes('date_label', 'string');
    }
}
