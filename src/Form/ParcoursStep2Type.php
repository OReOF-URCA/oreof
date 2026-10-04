<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Form/ParcoursStep2Type.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Form;

use App\Entity\Parcours;
use App\Entity\TypeDiplome;
use App\Enums\ModaliteEnseignementEnum;
use App\Form\Type\TextareaAutoSaveType;
use App\Form\Type\YesNoType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ParcoursStep2Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var TypeDiplome $typeDiplome */
        $typeDiplome = $options['typeDiplome'];

        $builder
            ->add('modalitesEnseignement', EnumType::class, [
                //http://lheo.gouv.fr/2.3/lheo/dict-modalites-enseignement.html#dict-modalites-enseignement
                'class' => ModaliteEnseignementEnum::class,
                'choice_label' => fn (ModaliteEnseignementEnum $choice) => match ($choice) {
                    ModaliteEnseignementEnum::NON_DEFINI => 'Choisissez une modalité d\'enseignement',
                    ModaliteEnseignementEnum::PRESENTIELLE => 'En présentiel',
                    ModaliteEnseignementEnum::DISTANCIELLE => 'En distanciel',
                    ModaliteEnseignementEnum::HYBRIDE => 'Hybride',
                },
                'expanded' => false,
            ]);

        if ($typeDiplome->isHasStage() === true) {
            $builder->add('hasStage', YesNoType::class, [
                'choice_attr' => function ($choice) {
                    return [
                        'data-conditional-field-target' => 'trigger',
                        'data-action' => 'change->conditional-field#toggle'
                    ];
                },
            ])
                ->add('stageText', TextareaAutoSaveType::class, [
                    'attr' => [
                        'rows' => 15,
                        'maxlength' => 3000,
                    ],
                ])
                ->add('nbHeuresStages', NumberType::class, [
                    'html5' => true,
                    'scale' => 1,
                    'input_suffix_text' => 'heure(s)',
                    'row_attr' => [
                        'class' => 'col-sm-3',
                    ],
                ]);
        }

        //alors zone de saisi
        //si L ou M, nombre d'heures
        if ($typeDiplome->isHasProjet() === true) {
            $builder->
            add('hasProjet', YesNoType::class, [
                'choice_attr' => function ($choice) {
                    return [
                        'data-conditional-field-target' => 'trigger',
                        'data-action' => 'change->conditional-field#toggle'
                    ];
                },
            ])
                ->add('projetText', TextareaAutoSaveType::class, [
                    'attr' => [
                        'rows' => 15,
                        'maxlength' => 3000,
                    ],
                ])
                ->add('nbHeuresProjet', NumberType::class, [
                    'html5' => true,
                    'scale' => 1,
                    'input_suffix_text' => 'heure(s)',
                    'row_attr' => [
                        'class' => 'col-sm-3',
                    ],
                ]);
        }

        if ($typeDiplome->isHasMemoire() === true) {
            $builder->add('hasMemoire', YesNoType::class, [
                'choice_attr' => function ($choice) {
                    // On marque chaque checkbox comme une cible 'trigger'
                    return [
                        'data-conditional-field-target' => 'trigger',
                        'data-action' => 'change->conditional-field#toggle'
                    ];
                },
            ])
                ->add('memoireText', TextareaAutoSaveType::class, [
                    'attr' => [
                        'rows' => 15,
                        'maxlength' => 3000,
                    ],
                ]);
        }


        if ($typeDiplome->isHasSituationPro()) {
            $builder
                ->add('hasSituationPro', YesNoType::class, [
                    'choice_attr' => function ($choice) {
                        // On marque chaque checkbox comme une cible 'trigger'
                        return [
                            'data-conditional-field-target' => 'trigger',
                            'data-action' => 'change->conditional-field#toggle'
                        ];
                    },
                    'data' => (bool)$typeDiplome?->isHasSituationPro(),
                ])
                ->add('nbHeuresSituationPro', NumberType::class, [
                    'html5' => true,
                    'scale' => 1,
                    'input_suffix_text' => 'heure(s)',
                    'row_attr' => [
                        'class' => 'col-sm-3',
                    ],
                ])
                ->add('situationProText', TextareaAutoSaveType::class, [
                    'attr' => [
                        'rows' => 15,
                        'maxlength' => 3000,
                    ],
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Parcours::class,
            'typeDiplome' => null,
            'translation_domain' => 'form'
        ]);
    }
}
