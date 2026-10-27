<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Form/NatureUeEcType.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 02/02/2023 08:52
 */

namespace App\Form;

use App\Entity\NatureUeEc;
use App\Form\Type\YesNoType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class NatureUeEcType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelle', TextType::class, [
                'required' => true,
                'attr' => ['maxlength' => 255],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le libellé est obligatoire.'),
                    new Assert\Length(max: 255),
                ],
            ])
            ->add('choix', YesNoType::class)
            ->add('libre', YesNoType::class)
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'nature_ue_ec.type.ec' => NatureUeEc::Nature_EC,
                    'nature_ue_ec.type.ue' => NatureUeEc::Nature_UE,
                ],
                'expanded' => true,
                'multiple' => false,
                'label' => 'nature_ue_ec.type.label',
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le type est obligatoire.'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => NatureUeEc::class,
            'translation_domain' => 'form'
        ]);
    }
}
