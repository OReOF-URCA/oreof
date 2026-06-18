<?php

namespace App\Form;

use App\Entity\PlateformeAdmissionParametre;
use App\Form\Type\DynamicFieldsType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire pour PlateformeAdmissionParametre avec champs dynamiques.
 *
 * Les champs spécifiques sont générés automatiquement à partir de
 * la définition stockée dans PlateformeAdmission->definitionChamps.
 */
class PlateformeAdmissionParametreType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('capaciteGlobale', IntegerType::class, [
                'label' => 'Capacité globale',
                'required' => false,
            ])
            ->add('capaciteFi', IntegerType::class, [
                'label' => 'Capacité formation initiale',
                'required' => false,
            ])
            ->add('capaciteAlternance', IntegerType::class, [
                'label' => 'Capacité alternance',
                'required' => false,
            ])
            ->add('capaciteSpecifique', IntegerType::class, [
                'label' => 'Capacité spécifique',
                'required' => false,
            ]);

        // Ajouter les champs dynamiques selon la plateforme
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();

            if (!$data instanceof PlateformeAdmissionParametre) {
                return;
            }

            $plateforme = $data->getPlateforme();
            if (!$plateforme) {
                return;
            }

            // Récupérer la définition des champs spécifiques
            $fieldDefinitions = $plateforme->getDefinitionChamps() ?? [];

            if (!empty($fieldDefinitions)) {
                // Ajouter le formulaire dynamique pour les données spécifiques
                $form->add('donneesSpecifiques', DynamicFieldsType::class, [
                    'label' => 'Données spécifiques à ' . $plateforme->getLibelle(),
                    'field_definitions' => $fieldDefinitions,
                    'required' => false,
                ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PlateformeAdmissionParametre::class,
            'translation_domain' => 'form',
        ]);
    }
}
