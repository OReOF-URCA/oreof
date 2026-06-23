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
use App\Enums\DureeParcoursUniteEnum;
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
                'help' => '-',
                'attr' => ['data-action' => 'change->parcours--step2#changeModaliteEnseignement'],
                'choice_label' => fn ($choice) => match ($choice) {
                    modaliteEnseignementEnum::NON_DEFINI => 'Choisissez une modalité d\'enseignement',
                    modaliteEnseignementEnum::PRESENTIELLE => 'En présentiel',
                    modaliteEnseignementEnum::DISTANCIELLE => 'En distanciel',
                    modaliteEnseignementEnum::HYBRIDE => 'Hybride',
                },
                'expanded' => false,
            ]);

        // Durée du parcours : saisie à la création (formulaire générique) mais sans écran
        // de modification. On l'expose ici pour les types de diplôme non classiques, seuls
        // concernés par cette notion de durée libre (les classiques sont rythmés en semestres).
        if ($typeDiplome !== null && !$typeDiplome->isClassique()) {
            $builder
                ->add('dureeParcours', NumberType::class, [
                    'required' => false,
                    'html5' => true,
                    'scale' => 1,
                    'help' => '-',
                    'attr' => [
                        'placeholder' => 'Ex. : 2',
                        'data-action' => 'change->parcours--step2#changeDureeParcours',
                    ],
                    'row_attr' => ['class' => 'col-sm-3'],
                ])
                ->add('dureeParcoursUnite', EnumType::class, [
                    'class' => DureeParcoursUniteEnum::class,
                    'required' => false,
                    'placeholder' => 'Choisissez une unité',
                    'help' => '-',
                    'choice_label' => fn (DureeParcoursUniteEnum $c) => $c->libelle(),
                    'attr' => ['data-action' => 'change->parcours--step2#changeDureeParcoursUnite'],
                    'row_attr' => ['class' => 'col-sm-3'],
                ]);
        }

       // if ($typeDiplome->isHasStage() === true) {
            $builder->add('hasStage', YesNoType::class, [
                'help' => '-',
                'attr' => ['data-action' => 'change->parcours--step2#changeStage'],
            ])
                ->add('stageText', TextareaAutoSaveType::class, [
                    'attr' => [
                        'rows' => 15,
                        'maxlength' => 3000,
                        'data-action' => 'change->parcours--step2#saveStageText'
                    ],
                    'help' => '-',
                ])
                ->add('nbHeuresStages', NumberType::class, [
                    'html5' => true,
                    'scale' => 1,
                    'input_suffix_text' => 'heure(s)',
                    'attr' => [
                        'data-action' => 'change->parcours--step2#changeNbHeuresStages',
                    ],
                    'row_attr' => [
                        'class' => 'col-sm-3',
                    ],
                ]);
       // }

        //alors zone de saisi
        //si L ou M, nombre d'heures
        //if ($typeDiplome->isHasProjet() === true) {
            $builder->
            add('hasProjet', YesNoType::class, [
                'attr' => ['data-action' => 'change->parcours--step2#changeProjet'],
            ])
                ->add('projetText', TextareaAutoSaveType::class, [
                    'help' => '-',
                    'attr' => [
                        'rows' => 15,
                        'maxlength' => 3000,
                        'data-action' => 'change->parcours--step2#saveProjetText'
                    ],
                ])
                ->add('nbHeuresProjet', NumberType::class, [
                    'html5' => true,
                    'scale' => 1,
                    'input_suffix_text' => 'heure(s)',
                    'attr' => ['data-action' => 'change->parcours--step2#changeNbHeuresProjet'],
                    'row_attr' => [
                        'class' => 'col-sm-3',
                    ],
                ]);
       // }

       // if ($typeDiplome->isHasMemoire() === true) {
            $builder->add('hasMemoire', YesNoType::class, [
                'attr' => ['data-action' => 'change->parcours--step2#changeMemoire'],
            ])
                ->add('memoireText', TextareaAutoSaveType::class, [
                    'help' => '-',
                    'attr' => [
                        'rows' => 15,
                        'maxlength' => 3000,
                        'data-action' => 'change->parcours--step2#saveMemoireText'
                    ],
                ]);
       // }


        if ($typeDiplome !== null && $typeDiplome->isHasSituationPro()) {
            $builder
                ->add('hasSituationPro', YesNoType::class, [
                    'attr' => ['data-action' => 'change->parcours--step2#changeSituationPro'],
                    'data' => (bool)$typeDiplome?->isHasSituationPro(),
                ])
                ->add('nbHeuresSituationPro', NumberType::class, [
                    'html5' => true,
                    'scale' => 1,
                    'input_suffix_text' => 'heure(s)',
                    'attr' => ['data-action' => 'change->parcours--step2#changeNbHeuresSituationPro'],
                    'row_attr' => [
                        'class' => 'col-sm-3',
                    ],
                ])
                ->add('situationProText', TextareaAutoSaveType::class, [
                    'help' => '-',
                    'attr' => [
                        'rows' => 15,
                        'maxlength' => 3000,
                        'data-action' => 'change->parcours--step2#saveSituationProText'
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
