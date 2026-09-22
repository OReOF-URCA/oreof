<?php

declare(strict_types=1);

namespace App\Form\Workflow;

use App\DTO\Workflow\ValiderConseilData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ValiderConseilType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateConseil', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date du conseil',
            ])
            ->add('uploadPv', FileType::class, [
                'required' => false,
                'label' => 'Procès-verbal du conseil',
                'help' => 'PDF obligatoire, sauf si un laissez-passer est demandé.',
                'attr' => ['accept' => 'application/pdf'],
            ])
            ->add('uploadArgumentaire', FileType::class, [
                'required' => false,
                'label' => 'Note explicative',
                'help' => 'Document PDF facultatif.',
                'attr' => ['accept' => 'application/pdf'],
            ])
            ->add('laissezPasser', CheckboxType::class, [
                'required' => false,
                'label' => 'Demander un laissez-passer en l’absence de PV',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ValiderConseilData::class,
            'translation_domain' => 'process',
            'attr' => ['id' => 'modal_form'],
        ]);
    }
}
