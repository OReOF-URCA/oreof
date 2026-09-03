<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Form/ParcoursStep1Type.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Form;

use App\Entity\Parcours;
use App\Entity\RythmeFormation;
use App\Entity\User;
use App\Entity\Ville;
use App\Form\Type\InlineCreateEntitySelectType;
use App\Form\Type\TextareaAutoSaveType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ParcoursStep1Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $formation = $options['data']->getFormation();

        $builder
            ->add('respParcours', InlineCreateEntitySelectType::class, [
                'help' => '',
                'class' => User::class,
                'choice_label' => 'display',
                'query_builder' => function ($er) {
                    return $er->createQueryBuilder('u')
                        ->orderBy('u.nom', 'ASC')
                        ->addOrderBy('u.prenom', 'ASC');
                },
                'label' => 'Responsable du parcours',
                'placeholder' => 'Choisir dans la liste ou choisir "+ Créer Nouveau" pour ajouter un utilisateur',
                'new_placeholder' => 'Email du responsable du parcours',
                'required' => true,
            ])
            ->add('coResponsable', InlineCreateEntitySelectType::class, [
                'help' => '',
                'class' => User::class,
                'choice_label' => 'display',
                'query_builder' => function ($er) {
                    return $er->createQueryBuilder('u')
                        ->orderBy('u.nom', 'ASC')
                        ->addOrderBy('u.prenom', 'ASC');
                },
                'label' => 'Co-responsable du parcours',
                'placeholder' => 'Choisir dans la liste ou choisir "+ Créer Nouveau" pour ajouter un utilisateur',
                'new_placeholder' => 'Email du co-responsable du parcours'
            ])
            ->add('objectifsParcours', TextareaAutoSaveType::class, [
                'attr' => [
                    'rows' => 10,
                    'maxlength' => 3000,
                ],
            ])
            ->add('motsCles', TextareaAutoSaveType::class, [
                'attr' => [
                    'rows' => 10,
                    'maxlength' => 3000,
                ],
            ])
            ->add('resultatsAttendus', TextareaAutoSaveType::class, [
                'attr' => ['rows' => 10, 'maxlength' => 3000,],
            ])
            ->add('contenuFormation', TextareaAutoSaveType::class, [
                'attr' => ['rows' => 20, 'maxlength' => 3000,],
            ])
            ->add('rythmeFormation', EntityType::class, [
                'placeholder' => 'Choisissez un rythme de formation ou complétez le champ ci-dessous',
                'required' => true,
                'class' => RythmeFormation::class,
                'choice_label' => 'libelle',
            ])
            ->add('rythmeFormationTexte', TextareaAutoSaveType::class, [
                'required' => false,
                'attr' => ['rows' => 10, 'maxlength' => 3000],
            ])
            ->add('localisation', EntityType::class, [
                'class' => Ville::class,
                'choice_label' => 'libelle',
                'placeholder' => 'Choisissez une ville',
                'expanded' => false,
                'choices' => $formation->getLocalisationMention()->toArray(),
                'data' => $options['data']->getLocalisation(),
            ]);
            //->add('logo', FileType::class, [
            //    'help' => 'Logo du parcours',
            //    'multiple' => true,
            //    'required' => false,
            //    'mapped' => false,
            //    'attr' => ['accept' => 'image/png, image/jpeg'],
            //])
            ;
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
