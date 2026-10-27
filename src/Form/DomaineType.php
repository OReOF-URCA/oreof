<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Form/DomaineType.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 27/01/2023 21:43
 */

namespace App\Form;

use App\Entity\Domaine;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class DomaineType extends AbstractType
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
            ->add('sigle', TextType::class, [
                'label' => 'Sigle',
                'required' => true,
                'attr' => ['maxlength' => 50],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le sigle est obligatoire.'),
                    new Assert\Length(max: 50),
                ],
            ])
            ->add('codeApogee', TextType::class, [
                'label' => 'Code Apogée',
                'attr' => ['maxlength' => 1],
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(message: 'Le code Apogée est obligatoire.'),
                    new Assert\Length(max: 1),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Domaine::class,
            'translation_domain' => 'form'
        ]);
    }
}
