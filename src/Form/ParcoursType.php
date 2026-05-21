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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

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
                'help' => '',
                'class' => User::class,
                'choice_label' => 'display',
                'query_builder' => function ($er) {
                    return $er->createQueryBuilder('u')
                        ->orderBy('u.nom', 'ASC')
                        ->addOrderBy('u.prenom', 'ASC');
                },
                'placeholder' => 'Choisir dans la liste ou choisir "+ Créer nouveau" pour ajouter un utilisateur',
                'new_placeholder' => 'Email du responsable du parcours',
                'required' => true,
                'label' => 'Responsable du parcours',
                'ldap_check' => true,

                // évite doublons (optionnel)
                'find_existing' => function (string $label, $scope, EntityManagerInterface $em) {
                    return $em->getRepository(User::class)->createQueryBuilder('t')
                        ->andWhere('LOWER(t.email) = LOWER(:l)')
                        ->setParameter('l', $label)
                        ->getQuery()
                        ->getOneOrNullResult();
                },

                // création (obligatoire)
                'create' => function (string $label, EntityManagerInterface $em) {
                    $e = new User();
                    $e->setEmail($label);
                    return $e; // persist/flush gérés par le type (ou tu peux le faire ici)
                },

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
                'placeholder' => 'Choisir dans la liste ou choisir "+ Créer nouveau" pour ajouter un utilisateur',
                'new_placeholder' => 'Email du co-responsable du parcours',
                'required' => false,
                'label' => 'Co-Responsable du parcours',
                'ldap_check' => true,

                // évite doublons (optionnel)
                'find_existing' => function (string $label, $scope, EntityManagerInterface $em) {
                    return $em->getRepository(User::class)->createQueryBuilder('t')
                        ->andWhere('LOWER(t.email) = LOWER(:l)')
                        ->setParameter('l', $label)
                        ->getQuery()
                        ->getOneOrNullResult();
                },

                // création (obligatoire)
                'create' => function (string $label, EntityManagerInterface $em) {
                    $e = new User();
                    $e->setEmail($label);
                    return $e; // persist/flush gérés par le type (ou tu peux le faire ici)
                },

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
