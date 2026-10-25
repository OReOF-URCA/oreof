<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Form/ParcoursType.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 21:42
 */

namespace App\Form;

use App\Entity\Parcours;
use App\Entity\User;
use App\Enums\TypeParcoursEnum;
use App\Form\Type\InlineCreateEntitySelectType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;

class ParcoursType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelle', TextType::class, [
                'help' => '',
                'required' => true,
            ])
            ->add('respParcours', InlineCreateEntitySelectType::class, [
                'class' => User::class,
                'label' => 'Responsable du parcours',
                'new_placeholder' => 'Email du responsable du parcours',
                'required' => true,
            ])
            ->add('coResponsable', InlineCreateEntitySelectType::class, [
                'class' => User::class,
                'label' => 'Co-Responsable du parcours',
                'new_placeholder' => 'Email du co-responsable du parcours',
                'required' => false,
            ])
            ->add('sigle', TextType::class, [
                'help' => 'Optionnel, sigle/code ou appelation courte du parcours',
                'required' => false,
                'attr' => [
                    'maxlength' => '15',
                ],
            ])
            ->add('typeParcours', EnumType::class, [
                'class' => TypeParcoursEnum::class,
                'translation_domain' => 'form',
            ])
            ->add('parcoursOrigine', EntityType::class, [
                'required' => false,
                'help' => '',
                'autocomplete' => true,
                'class' => Parcours::class,
                'query_builder' => function ($er) use ($options){
                    return $er->createQueryBuilder('p')
                        ->where('p.formation = :formation')
                        ->setParameter('formation', $options['formation'])
                        ->orderBy('p.libelle', 'ASC');
                },
                'choice_label' => 'getDisplay',
            ])
            ->add('logo', FileType::class, [
                'help' => 'Logo du parcours',
                'multiple' => true,
                'required' => false,
                'mapped' => false,
                'attr' => ['accept' => 'image/png, image/jpeg'],
            ]);

        if ($options['isAdmin'] === true) {
            $builder->add('codeMentionApogee', TextType::class, [
                'help' => 'Code de la mention dans Apogée',
                'attr' => [
                    'maxlength' => '1',
                ],
                'required' => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Parcours::class,
            'translation_domain' => 'form',
            'formation' => null,
            'isAdmin' => false,
        ]);
    }
}
