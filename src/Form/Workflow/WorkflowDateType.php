<?php

declare(strict_types=1);

namespace App\Form\Workflow;

use App\DTO\Workflow\WorkflowDateData;
use App\Form\Type\CfvuDateType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

final class WorkflowDateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isCfvu = $options['use_cfvu_dates']
            ?? ('dateCfvu' === $options['date_property'] || str_contains(mb_strtolower((string) $options['date_label']), 'cfvu'));

        $builder->add($options['date_property'], $isCfvu ? CfvuDateType::class : DateType::class, [
            'widget' => 'single_text',
            'label' => $options['date_label'],
            'constraints' => [new NotNull(message: 'La date est obligatoire.')],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WorkflowDateData::class,
            'translation_domain' => 'process',
            'attr' => ['id' => 'modal_form'],
            'date_label' => 'Date',
            'use_cfvu_dates' => null,
        ]);
        $resolver->setRequired('date_property');
        $resolver->setAllowedValues('date_property', ['dateConseil', 'dateCfvu', 'datePublication']);
        $resolver->setAllowedTypes('date_label', 'string');
        $resolver->setAllowedTypes('use_cfvu_dates', ['null', 'bool']);
    }
}
