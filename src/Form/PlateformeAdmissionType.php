<?php

namespace App\Form;

use App\Entity\PlateformeAdmission;
use App\Form\Type\ColorVariantType;
use App\Form\Type\JsonConfigType;
use App\Form\Type\YesNoType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlateformeAdmissionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelle')
            ->add('code')
            ->add('color', ColorVariantType::class, [
                'label' => 'Couleur',
                'show_labels' => false,
            ])
            ->add('active', YesNoType::class, [])
            ->add('configuration', JsonConfigType::class, [
                'label' => 'Configuration de la plateforme',
                'required' => false,
                'help_text' => 'Configuration technique de la plateforme au format JSON',
            ])
            ->add('definitionChamps', JsonConfigType::class, [
                'label' => 'Définition des champs',
                'required' => false,
                'help_text' => 'Définition des champs spécifiques pour cette plateforme',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PlateformeAdmission::class,
            'translation_domain' => 'form'
        ]);
    }
}
