<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Form/RythmeFormationType.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 02/02/2023 10:57
 */

namespace App\Form;

use App\Entity\RythmeFormation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class RythmeFormationType extends AbstractType
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
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RythmeFormation::class,
            'translation_domain' => 'form'
        ]);
    }
}
