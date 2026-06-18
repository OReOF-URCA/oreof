<?php

namespace App\Form;

use App\Entity\PlateformeAdmission;
use App\Entity\TypeDiplome;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TypeDiplomePlateformeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $nbAnnees = $options['nb_annees'];
        
        $builder
            ->add('plateforme', EntityType::class, [
                'class' => PlateformeAdmission::class,
                'choice_label' => 'libelle',
                'label' => 'Plateforme',
                'required' => true,
                'placeholder' => 'Sélectionnez une plateforme',
            ]);
        
        // Générer dynamiquement les cases à cocher pour les années
        if ($nbAnnees > 0) {
            $anneesChoices = [];
            for ($i = 1; $i <= $nbAnnees; $i++) {
                $anneesChoices["Année $i"] = $i;
            }
            
            $builder->add('annees', ChoiceType::class, [
                'choices' => $anneesChoices,
                'multiple' => true,
                'expanded' => true,
                'label' => 'Années concernées',
                'required' => true,
                'help' => 'Sélectionnez les années pour lesquelles cette plateforme est utilisée',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'nb_annees' => 1,
        ]);
        
        $resolver->setAllowedTypes('nb_annees', 'int');
    }
}
