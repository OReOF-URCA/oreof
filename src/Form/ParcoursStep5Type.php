<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Form/ParcoursStep5Type.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 05/03/2023 17:44
 */

namespace App\Form;

use App\Entity\Composante;
use App\Entity\Parcours;
use App\Enums\NiveauLangueEnum;
use App\Enums\RegimeInscriptionEnum;
use App\Form\Type\TextareaAutoSaveType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use UnitEnum;

class ParcoursStep5Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('niveauFrancais', EnumType::class, [
                'class' => NiveauLangueEnum::class,
                'choice_label' => static function (NiveauLangueEnum $choice): string {
                    return $choice->libelle();
                },
            ])
            ->add('prerequis', TextareaAutoSaveType::class, [
                'attr' => ['rows' => 15, 'maxlength' => 3000],
                'help' => '-',
            ])
            ->add('composanteInscription', EntityType::class, [
                'placeholder' => 'Choisissez une composante d\'inscription',
                'class' => Composante::class,
                'choice_label' => 'libelle',
                'multiple' => false,
                'expanded' => false,
                'query_builder' => static function ($er) {
                    return $er->createQueryBuilder('c')
                        ->orderBy('c.libelle', 'ASC');
                },
                'autocomplete' => true,
            ])
            ->add('regimeInscription', EnumType::class, [
                'class' => RegimeInscriptionEnum::class,
                'translation_domain' => 'enum',
                'multiple' => true,
                'expanded' => true,
                'choice_attr' => function ($choice) {
                    return [
                        'data-conditional-field-target' => 'trigger',
                        'data-action' => 'change->conditional-field#toggle'
                    ];
                },
            ])
            ->add('modalitesAlternance', TextareaAutoSaveType::class, [
                'attr' => ['rows' => 20, 'maxlength' => 3000],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Parcours::class,
            'translation_domain' => 'form'
        ]);
    }
}
