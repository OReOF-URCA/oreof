<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Form/FicheMatiereStep1Type.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Form;

use App\Entity\FicheMatiere;
use App\Entity\User;
use App\Form\Type\YesNoType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class FicheMatiereStep1Type extends AbstractType
{

    public function __construct()
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $access = true;
        $builder

            ->add('responsableFicheMatiere', EntityType::class, [
                'class' => User::class,
                'autocomplete' => true,
                'query_builder' => function ($er) {
                    return $er->createQueryBuilder('u')
                        ->orderBy('u.nom', 'ASC')
                        ->addOrderBy('u.prenom', 'ASC');
                },
                'disabled' => !$access,
                'required' => false,
                'choice_label' => 'display',
            ])
            ->add('sigle', TextType::class, [
                'attr' => ['maxlength' => 250],
                'required' => false
            ])
            ->add('libelle', TextType::class, [
                'disabled' => !$access,
                'attr' => ['maxlength' => 250],
            ])
            ->add('libelleAnglais', TextType::class, [
                'attr' => ['maxlength' => 250],
            ]);

        if ($options['isScol']) {
            $builder->add('codeApogee', TextType::class, [
                'attr' => ['maxlength' => 8],
                'required' => false
            ]);
        }

        if ($options['isBut']) {
            $builder->add('typeMatiere', ChoiceType::class, [
                'choices' => [
                    'ressource' => 'ressource',
                    'sae' => 'sae'
                ],
                'expanded' => true,
                'multiple' => false,
                'required' => true,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FicheMatiere::class,
            'translation_domain' => 'form',
            'isBut' => false,
            'isScol' => false,
        ]);
    }
}
