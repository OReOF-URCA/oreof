<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Form/VilleType.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 27/01/2023 19:20
 */

namespace App\Form;

use App\Entity\Etablissement;
use App\Entity\Ville;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VilleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelle', TextType::class, [
                'label' => 'Libellé de la ville',
            ])
            ->add('etablissement', EntityType::class, [
                'class' => Etablissement::class,
                'choice_label' => 'libelle',
                'label' => 'Etablissement',
                'autocomplete' => true,
            ])
            ->add('codeApogee', TextType::class, [
                'label' => 'Code Apogée',
                'attr' => ['maxlength' => 1],
                'required' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Ville::class,
            'translation_domain' => 'form'
        ]);
    }
}
