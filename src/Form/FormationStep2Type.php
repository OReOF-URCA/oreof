<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Form/FormationStep2Type.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 14/03/2023 12:09
 */

namespace App\Form;

use App\Entity\Formation;
use App\Entity\RythmeFormation;
use App\Form\Type\TextareaAutoSaveType;
use App\Form\Type\YesNoType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FormationStep2Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('objectifsFormation', TextareaAutoSaveType::class, [
                'help' => '-',
                'attr' => ['rows' => 10, 'maxlength' => 3000],
            ])
            ->add('resultatsAttendus', TextareaAutoSaveType::class, [
                'help' => '-',
                'attr' => ['rows' => 10, 'maxlength' => 3000],
            ])
            ->add('contenuFormation', TextareaAutoSaveType::class, [
                'help' => '-',
                'attr' => ['rows' => 20, 'maxlength' => 3000],
            ])
            ->add('rythmeFormation', EntityType::class, [
                'placeholder' => 'Choisissez un rythme de formation ou complétez le champ ci-dessous',
                'required' => false,
                'help' => '-',
                'class' => RythmeFormation::class,
                'choice_label' => 'libelle',
            ])


            ->add('rythmeFormationTexte', TextareaAutoSaveType::class, [
                'required' => false,
                'help' => '-',
                'attr' => ['rows' => 10, 'maxlength' => 3000],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Formation::class,
            'translation_domain' => 'form'
        ]);
    }
}
