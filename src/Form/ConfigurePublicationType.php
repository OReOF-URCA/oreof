<?php

namespace App\Form;

use App\Entity\CampagneCollecte;
use App\Enums\CampagnePublicationTagEnum;
use App\Enums\ConfigurationPublicationEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfigurePublicationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('campagneTag', ChoiceType::class, [
                'mapped' => false,
                'required' => true,
                'label' => 'Statut de la campagne de collecte',
                'choices' => [
                    'Sélectionner une option...' => 'none',
                    'Année archivée' => CampagnePublicationTagEnum::ANNEE_ARCHIVEE->value,
                    'Année courante (N)' => CampagnePublicationTagEnum::ANNEE_COURANTE->value,
                    'Année suivante (N+1)' => CampagnePublicationTagEnum::ANNEE_SUIVANTE->value
                ],
                'choice_attr' => [
                    'Sélectionner une option...' => $options['publicationTag'] === 'none' ? ['selected' => ''] : [],
                    'Année archivée' => $options['publicationTag'] === CampagnePublicationTagEnum::ANNEE_ARCHIVEE->value ? ['selected' => ''] : [],
                    'Année courante (N)' => $options['publicationTag'] === CampagnePublicationTagEnum::ANNEE_COURANTE->value ? ['selected' => ''] : [],
                    'Année suivante (N+1)' => $options['publicationTag'] === CampagnePublicationTagEnum::ANNEE_SUIVANTE->value ? ['selected' => ''] : []
                ]
            ])
            ->add('enablePublication', CheckboxType::class, [
                'label' => 'Activer la publication ?',
                'required' => true
            ])
            ->add(ConfigurationPublicationEnum::MAQUETTE->value, ChoiceType::class, [
                'mapped' => false,
                'required' => true,
                'label' => 'Publier la Maquette',
                'choices' => [
                    'Sélectionner une option...' => null,
                    'Oui' => true,
                    'Non' => false
                ],
                'choice_attr' => [
                    'Sélectionner une option...' => ($options['hasPublishedMaquette'] === 'none') ? ['selected' => ''] : [],
                    'Oui' => ($options['hasPublishedMaquette'] === true) ? ['selected' => ''] : [],
                    'Non' => ($options['hasPublishedMaquette'] === false) ? ['selected' => ''] : []
                ]
            ])
            ->add(ConfigurationPublicationEnum::MCCC->value, ChoiceType::class, [
                'mapped' => false,
                'required' => true,
                'label' => 'Publier les MCCC (PDF)',
                'choices' => [
                    'Sélectionner une option...' => null,
                    'Oui' => true,
                    'Non' => false
                ],
                'choice_attr' => [
                    'Sélectionner une option... ' => ($options['hasPublishedMccc'] === 'none') ? ['selected' => ''] : [],
                    'Oui' => ($options['hasPublishedMccc'] === true) ? ['selected' => ''] : [],
                    'Non' => ($options['hasPublishedMccc'] === false) ? ['selected' => ''] : []
                ]
            ])
            ->add('isFinished', ChoiceType::class, [
                'required' => true,
                'label' => 'Campagne de consolidation de la collecte terminée ?',
                'choices' => [
                    'Sélectionner une option...' => null,
                    'Oui' => true,
                    'Non' => false
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CampagneCollecte::class,
            'hasPublishedMaquette' => 'none',
            'hasPublishedMccc' => 'none',
            'publicationTag' => 'none'
        ]);
    }
}
