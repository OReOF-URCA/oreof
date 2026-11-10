<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Accreditation;
use App\Form\Type\YesNoType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AccreditationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelle', TextType::class, [
                'label' => 'Libellé',
                'attr' => [
                    'placeholder' => 'ex: 2024-2028',
                ],
                'required' => true,
            ])
            ->add('anneeDebut', IntegerType::class, [
                'label' => 'Année de début',
                'attr' => [
                    'placeholder' => 'ex: 2024',
                ],
                'required' => true,
            ])
            ->add('anneeFin', IntegerType::class, [
                'label' => 'Année de fin',
                'attr' => [
                    'placeholder' => 'ex: 2028',
                ],
                'required' => true,
            ])
            ->add('actif', YesNoType::class, [
                'label' => 'Active ?',
                'required' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Accreditation::class,
            'translation_domain' => 'form',
        ]);
    }
}

