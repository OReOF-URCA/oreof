<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Form/MentionDtoType.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 12/05/2025 22:39
 */

namespace App\Form;

use App\DTO\MentionDto;
use App\Entity\Domaine;
use App\Entity\TypeDiplome;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire pour la création et la mise à jour des mentions via DTO.
 */
class MentionDtoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('typeDiplomeId', EntityType::class, [
                'class' => TypeDiplome::class,
                'query_builder' => static function ($er) {
                    return $er->createQueryBuilder('t')
                        ->orderBy('t.libelle', 'ASC');
                },
                'autocomplete' => true,
                'choice_label' => 'libelle',
                'choice_value' => 'id',
                'label' => 'Type de diplôme',
                'attr' => ['data-action' => 'change->formation#changeTypeDiplome'],
                'placeholder' => 'Sélectionnez un type de diplôme',
                'required' => true,
                'property_path' => 'typeDiplomeId',
            ])
            ->add('libelle', TextType::class, [
                'label' => 'Libellé',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Entrez le libellé de la mention',
                    'maxlength' => 255
                ],
            ])
            ->add('sigle', TextType::class, [
                'label' => 'Sigle',
                'help' => 'Le sigle est la dénomination courte de la mention, s\'il existe.',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Entrez le sigle (optionnel)',
                    'maxlength' => 20
                ],
            ])
            ->add('domaines', EntityType::class, [
                'class' => Domaine::class,
                'query_builder' => static function ($er) {
                    return $er->createQueryBuilder('d')
                        ->orderBy('d.libelle', 'ASC');
                },
                'autocomplete' => true,
                'choice_label' => 'libelle',
                'choice_value' => 'id',
                'label' => 'Domaine(s)',
                'placeholder' => 'Sélectionnez un ou plusieurs domaine(s)',
                'required' => true,
                'multiple' => true,
                'property_path' => 'domaines',
            ])
            ->add('codeApogee', TextType::class, [
                'label' => 'Code Apogée',
                'attr' => [
                    'maxlength' => 1,
                    'placeholder' => 'A-Z, 0-9'
                ],
                'required' => true,
                'help' => 'Un seul caractère (lettre ou chiffre)',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MentionDto::class,
            'translation_domain' => 'form',
            'attr' => [
                'novalidate' => 'novalidate', // Désactive la validation HTML5 pour utiliser la validation côté serveur
            ],
        ]);
    }
}
