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

        $villes = [];
        foreach ($formation->getLocalisationMention() as $ville) {
            $villes[$ville->getLibelle()] = $ville->getId();
        }

        $builder
            ->add('respParcours', EntityType::class, [
                'required' => true,
                'help' => '',
                'class' => User::class,
                'autocomplete' => true,
                'choice_label' => 'display',
                'query_builder' => function ($er) {
                    return $er->createQueryBuilder('u')
                        ->orderBy('u.nom', 'ASC')
                        ->addOrderBy('u.prenom', 'ASC');
                },
                'attr' => ['data-action' => 'change->parcours--step1#saveRespParcours']
            ])
            ->add('coResponsable', EntityType::class, [
                'required' => false,
                'help' => '',
                'autocomplete' => true,
                'class' => User::class,
                'choice_label' => 'display',
                'query_builder' => function ($er) {
                    return $er->createQueryBuilder('u')
                        ->orderBy('u.nom', 'ASC')
                        ->addOrderBy('u.prenom', 'ASC');
                },
                'attr' => ['data-action' => 'change->parcours--step1#saveCoRespParcours']
            ])
            ->add('objectifsParcours', TextareaAutoSaveType::class, [
                'help' => '-',
                'attr' => [
                    'rows' => 10,
                    'maxlength' => 3000,
                    'data-action' => 'change->parcours--step1#saveObjectifsParcours'
                ],
            ])
            ->add('motsCles', TextareaAutoSaveType::class, [
                'help' => '-',
                'attr' => [
                    'rows' => 10,
                    'maxlength' => 3000,
                    'data-action' => 'change->parcours--step1#saveMotsCles'
                ],
            ])
            ->add('resultatsAttendus', TextareaAutoSaveType::class, [
                'help' => '-',
                'attr' => ['rows' => 10, 'maxlength' => 3000, 'data-action' => 'change->parcours--step1#saveResultats'],
            ])
            ->add('contenuFormation', TextareaAutoSaveType::class, [
                'help' => '-',
                'attr' => ['rows' => 20, 'maxlength' => 3000, 'data-action' => 'change->parcours--step1#saveContenu'],
            ])
            ->add('rythmeFormation', EntityType::class, [
                'placeholder' => 'Choisissez un rythme de formation ou complétez le champ ci-dessous',
                'required' => false,
                'help' => '-',
                'class' => RythmeFormation::class,
                'choice_label' => 'libelle',
                'attr' => ['data-action' => 'change->parcours--step1#changeRythme'],
            ])
            ->add('rythmeFormationTexte', TextareaAutoSaveType::class, [
                'required' => false,
                'help' => '-',
                'attr' => ['rows' => 10, 'maxlength' => 3000, 'data-action' => 'change->parcours--step1#saveRythme'],
            ])
            ->add('localisation', ChoiceType::class, [
                'placeholder' => 'Choisissez une ville',
                'expanded' => false,
                'choices' => $villes,
                'data' => $options['data']->getLocalisation()?->getId() ?? 0,
                'attr' => [
                    'data-action' => 'change->parcours--step1#changeLocalisation',
                ]
            ])
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
