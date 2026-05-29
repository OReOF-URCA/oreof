<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Form/ProfilType.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 26/05/2025 16:32
 */

namespace App\Form;

use App\Entity\Profil;
use App\Enums\CentreGestionEnum;
use App\Form\Type\YesNoType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProfilType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelle', TextType::class, [
                'attr' => [
                    'placeholder' => 'Libellé du profil'
                ]
            ])
            ->add('code', TextType::class, [
                'attr' => [
                    'placeholder' => 'Code du profil'
                ]
            ])
            ->add('centre', ChoiceType::class, [
                'choices' => [
                    'Parcours' => CentreGestionEnum::CENTRE_GESTION_PARCOURS,
                    'Formation' => CentreGestionEnum::CENTRE_GESTION_FORMATION,
                    'Composante' => CentreGestionEnum::CENTRE_GESTION_COMPOSANTE,
                    'Etablissement' => CentreGestionEnum::CENTRE_GESTION_ETABLISSEMENT,
                ],
                'placeholder' => 'Indiquez un centre de gestion',
                'required' => true,
                'expanded' => true
            ])
            ->add('onlyAdmin', YesNoType::class, [
                'label' => 'Ce profil est réservé aux administrateurs',
                'required' => true,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ])
            ->add('isExclusif', YesNoType::class, [
                'label' => 'Ce profil est exclusif',
                'required' => true,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ])
            ->add('isMailing', YesNoType::class, [
                'label' => 'Peut être destinataire de mails',
                'required' => true,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Profil::class,
            'translation_domain' => 'form',
        ]);
    }
}
