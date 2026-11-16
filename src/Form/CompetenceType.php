<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Form/CompetenceType.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 07/03/2023 20:42
 */

namespace App\Form;

use App\Entity\Competence;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CompetenceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'required' => false,
                'attr' => ['maxlength' => 10],
                'help' => 'Laisser vide pour générer automatiquement (ex: 1A)',
            ])
            ->add('libelle', TextType::class, [
                'attr' => ['maxlength' => 400],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Competence::class,
            'translation_domain' => 'form'
        ]);
    }
}
