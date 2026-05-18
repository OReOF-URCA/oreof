<?php

namespace App\Form;

use App\Entity\EtablissementInformation;
use App\Form\Type\TextareaAutoSaveType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EtablissementInformationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('calendrier_universitaire', TextareaAutoSaveType::class, [
                'label' => 'Calendrier universitaire',
                'required' => true,
                'attr' => ['maxlength' => 2500]
            ])
            ->add('calendrier_inscription', TextareaAutoSaveType::class, [
                'label' => "Calendrier d'inscription",
                'required' => true,
                'attr' => ['maxlength' => 2500]
            ])
            ->add('tarif_inscription', TextareaAutoSaveType::class, [
                'label' => "Tarif d'inscription",
                'required' => true,
                'attr' => ['maxlength' => 2500]
            ])
            ->add('informations_pratiques', TextareaAutoSaveType::class, [
                'label' => 'Informations pratiques',
                'required' => true,
                'attr' => ['maxlength' => 2500]
            ])
            ->add('restauration', TextareaAutoSaveType::class, [
                'label' => 'Restauration',
                'required' => true,
                'attr' => ['maxlength' => 2500]
            ])
            ->add('hebergement', TextareaAutoSaveType::class, [
                'label' => 'Hébergement',
                'required' => true,
                'attr' => ['maxlength' => 2500]
            ])
            ->add('transport', TextareaAutoSaveType::class, [
                'label' => 'Transport',
                'required' => true,
                'attr' => ['maxlength' => 2500]
            ])
            ->add('descriptifHautPage', TextareaAutoSaveType::class, [
                'label' => 'Texte affiché en haut des pages parcours',
                'help' => 'Ce texte sera affiché par défaut en haut des pages parcours. Il peut être remplacé par un texte spécifique à chaque parcours.',
                'required' => false,
                'attr' => ['maxlength' => 2500]
            ])
            ->add('descriptifBasPage', TextareaAutoSaveType::class, [
                'label' => 'Texte affiché en bas des pages parcours',
                'help' => 'Ce texte sera affiché par défaut en bas des pages parcours. Il peut être remplacé par un texte spécifique à chaque parcours.',
                'required' => false,
                'attr' => ['maxlength' => 2500]
            ])
            ->add('textLas1', TextareaAutoSaveType::class, [
                'label' => 'Après la 1ère année de licence "Sciences pour la Santé" - Accès santé',
                'required' => true,
                'attr' => ['maxlength' => 2500]
            ])
            ->add('textLas2', TextareaAutoSaveType::class, [
                'label' => 'Licence - Accès santé 2ème année (L.As 2)',

                'required' => true,
                'attr' => ['maxlength' => 2500]
            ])
            ->add('textLas3', TextareaAutoSaveType::class, [
                'label' => 'Licence - Accès santé 3ème année (L.As 3)',
                'required' => true,
                'attr' => ['maxlength' => 2500]
            ])
            ->add('secondeChance', TextareaAutoSaveType::class, [
                'label' => 'Droit à la 2nde chance',
                'required' => true,
                'attr' => ['maxlength' => 2500]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EtablissementInformation::class,
        ]);
    }
}
