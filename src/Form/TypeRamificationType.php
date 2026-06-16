<?php

namespace App\Form;

use App\Entity\TypeRamificationParcours;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TypeRamificationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, 
                [
                    'label' => 'Code du type de ramification du parcours',
                    'required' => true,
                    'attr' => ['maxlength' => 255]
                ]
            )
            ->add('libelle', TextType::class, 
                [
                    'label' => 'Libellé du type de ramification du parcours',
                    'required' => true,
                    'attr' => ['maxlength' => 1500]
                ]
            )
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TypeRamificationParcours::class,
        ]);
    }
}
