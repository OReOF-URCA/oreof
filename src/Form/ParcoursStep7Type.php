<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Form/ParcoursStep7Type.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 05/03/2023 17:57
 */

namespace App\Form;

use App\Entity\Parcours;
use App\Form\Type\TextareaAutoSaveType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ParcoursStep7Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('descriptifHautPageAutomatique', TextareaAutoSaveType::class, [
//                'label' => 'Texte affiché en haut des pages parcours (généré automatiquement)',
//                'help' => 'Ce texte est généré par la configuration du parcours et des semestres. Ce texte sera affiché par défaut en haut des pages parcours. Il peut être remplacé par un texte spécifique à chaque parcours.',
                'required' => false,
                'attr' => ['rows' => 20, 'maxlength' => 2500],
            ])
            ->add('descriptifHautPage', TextareaAutoSaveType::class, [
//                'label' => 'Texte affiché en haut des pages parcours',
//                'help' => 'Ce texte sera affiché par défaut en haut des pages parcours. Il peut être remplacé par un texte spécifique à chaque parcours.',
                'required' => false,
                'attr' => ['rows' => 20, 'maxlength' => 2500],
            ])
            ->add('descriptifBasPage', TextareaAutoSaveType::class, [
//                'label' => 'Texte affiché en bas des pages parcours',
//                'help' => 'Ce texte sera affiché par défaut en bas des pages parcours. Il peut être remplacé par un texte spécifique à chaque parcours.',
                'required' => false,
                'attr' => ['rows' => 20, 'maxlength' => 2500],

            ])
            ->add('codeRNCP', TextType::class, [
                'required' => false,
                'attr' => ['maxlength' => 10],
            ])
            ->add('lienCourt', UrlType::class, [
                'required' => false,
                'default_protocol' => 'https',
                'attr' => [
                    'placeholder' => 'https://...',
                    'maxlength' => 255,
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Parcours::class,
            'translation_domain' => 'form'
        ]);
    }
}
