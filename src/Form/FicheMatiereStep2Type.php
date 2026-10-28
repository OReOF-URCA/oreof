<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Form/FicheMatiereStep2Type.php
 * @author davidannebicque
 * @project oreof
 */

namespace App\Form;

use App\Entity\FicheMatiere;
use App\Form\Type\TextareaAutoSaveType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\LanguageType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FicheMatiereStep2Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('description', TextareaAutoSaveType::class, [
                'attr' => ['maxlength' => 3000, 'rows' => 20],
            ])
            ->add('objectifs', TextareaAutoSaveType::class, [
                'attr' => ['maxlength' => 3000, 'rows' => 20],
            ])
            ->add('langueDispense', LanguageType::class, [
                'label' => 'Langue(s) des cours',
                'autocomplete' => true,
                'multiple' => true,
                'choice_translation_locale' => 'fr',
                'required' => true,
                'placeholder' => 'Rechercher une langue...',
            ])
            ->add('langueSupport', LanguageType::class, [
                'label' => 'Langue(s) des supports',
                'autocomplete' => true,
                'multiple' => true,
                'choice_translation_locale' => 'fr',
                'required' => true,
                'placeholder' => 'Rechercher une langue...',
            ])
            ->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event): void {
                $data = $event->getData();
                if (!$data instanceof FicheMatiere) {
                    return;
                }

                if (empty($data->getLangueDispense())) {
                    $data->setLangueDispense(['fr']);
                }

                if (empty($data->getLangueSupport())) {
                    $data->setLangueSupport(['fr']);
                }
            })
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FicheMatiere::class,
            'translation_domain' => 'form',
        ]);
    }
}
