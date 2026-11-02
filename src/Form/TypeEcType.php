<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Form/TypeUeType.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 11/02/2023 07:54
 */

namespace App\Form;

use App\Entity\TypeDiplome;
use App\Entity\TypeEc;
use App\Enums\TypeUeEcEnum;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class TypeEcType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelle', TextType::class, [
                'label' => 'Libellé',
                'required' => true,
                'attr' => ['maxlength' => 255],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le libellé est obligatoire.'),
                    new Assert\Length(max: 255),
                ],
            ])
            ->add('type', EnumType::class, [
                'help' => 'Type EC',
                'class' => TypeUeEcEnum::class,
                'translation_domain' => 'form',
                'multiple' => false,
                'expanded' => true,
                'required' => true,
                'constraints' => [
                    new Assert\NotNull(message: 'Le type est obligatoire.'),
                ],
            ])
            ->add('typeDiplomes', EntityType::class, [
                'class' => TypeDiplome::class,
                'choice_label' => 'libelle',
                'label' => 'Type(s) de diplôme proposant ce type d\'EC',
                'multiple' => true,
                'expanded' => true,
            ])

        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TypeEc::class,
            'typesDiplomes' => [],
            'translation_domain' => 'form'
        ]);
    }
}
