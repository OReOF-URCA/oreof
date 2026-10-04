<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Form/EcStep4Type.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Form;

use App\Entity\ElementConstitutif;
use App\Enums\ModaliteEnseignementEnum;
use App\Form\Type\FloatType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EcStep4Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isModal = $options['isModal'];

        if ($options['modalite'] === null || $options['modalite'] === ModaliteEnseignementEnum::HYBRIDE || $options['modalite'] === ModaliteEnseignementEnum::NON_DEFINI) {
            $builder->add('modaliteEnseignement', EnumType::class, [
                'class' => ModaliteEnseignementEnum::class,
                'choice_label' => fn (ModaliteEnseignementEnum $choice) => match ($choice) {
                    ModaliteEnseignementEnum::NON_DEFINI => 'Choisir une modalité',
                    ModaliteEnseignementEnum::PRESENTIELLE => 'En présentiel',
                    ModaliteEnseignementEnum::DISTANCIELLE => 'En distanciel',
                    ModaliteEnseignementEnum::HYBRIDE => 'Hybride',
                },
                'expanded' => false,
                'attr' => ['data-action' => !$isModal ? 'change->ec--structure#saveModaliteEnseignement' : 'change->ec--structureparcours#changeModalite'],
            ]);
        }

        $builder->add('volumeCmPresentiel', FloatType::class, [
            'html5' => true,
            'scale' => 1,
            'required' => false,
            'attr' => [
                'data-action' => !$isModal ? 'change->ec--structure#saveVolume' : '',
                'data-ec--structure-type-param' => 'volumeCmPresentiel',
                'min' => 0,
            ],
        ])
            ->add('volumeTdPresentiel', FloatType::class, [
                'html5' => true,
                'scale' => 1,
                'required' => false,
                'attr' => [
                    'data-action' => !$isModal ? 'change->ec--structure#saveVolume' : '',
                    'data-ec--structure-type-param' => 'volumeTdPresentiel',
                    'min' => 0,
                ],
            ])
            ->add('volumeTpPresentiel', FloatType::class, [
                'html5' => true,
                'scale' => 1,
                'required' => false,
                'attr' => [
                    'data-action' => !$isModal ? 'change->ec--structure#saveVolume' : '',
                    'data-ec--structure-type-param' => 'volumeTpPresentiel',
                    'min' => 0,
                ],
            ])
            ->add('volumeCmDistanciel', FloatType::class, [
                'html5' => true,
                'scale' => 1,
                'required' => false,
                'attr' => [
                    'data-action' => !$isModal ? 'change->ec--structure#saveVolume' : '',
                    'data-ec--structure-type-param' => 'volumeCmDistanciel',
                    'min' => 0,
                ],
            ])
            ->add('volumeTdDistanciel', FloatType::class, [
                'html5' => true,
                'scale' => 1,
                'required' => false,
                'attr' => [
                    'data-action' => !$isModal ? 'change->ec--structure#saveVolume' : '',
                    'data-ec--structure-type-param' => 'volumeTdDistanciel',
                    'min' => 0,
                ],
            ])
            ->add('volumeTpDistanciel', FloatType::class, [
                'html5' => true,
                'scale' => 1,
                'required' => false,
                'attr' => [
                    'data-action' => !$isModal ? 'change->ec--structure#saveVolume' : '',
                    'data-ec--structure-type-param' => 'volumeTpDistanciel',
                    'min' => 0,
                ],
            ])
            ->add('volumeTe', FloatType::class, [
                'html5' => true,
                'scale' => 1,
                'required' => false,
                'attr' => [
                    'data-action' => !$isModal ? 'change->ec--structure#saveVolume' : '',
                    'data-ec--structure-type-param' => 'volumeTe',
                    'min' => 0,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ElementConstitutif::class,
            'isModal' => false,
            'modalite' => null,
            'translation_domain' => 'form'
        ]);
    }
}
