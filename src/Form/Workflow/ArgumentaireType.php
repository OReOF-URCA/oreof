<?php

declare(strict_types=1);

namespace App\Form\Workflow;

use App\DTO\Workflow\ArgumentaireData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ArgumentaireType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('argumentaire', TextareaType::class, [
            'required' => $options['argumentaire_required'],
            'label' => 'Argumentaire',
            'attr' => ['rows' => 6],
            'constraints' => $options['argumentaire_required']
                ? [new NotBlank(message: 'L\'argumentaire est obligatoire.')]
                : [],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ArgumentaireData::class,
            'translation_domain' => 'process',
            'attr' => ['id' => 'modal_form'],
            'argumentaire_required' => true,
        ]);
        $resolver->setAllowedTypes('argumentaire_required', 'bool');
    }
}
