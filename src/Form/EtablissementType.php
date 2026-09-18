<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Form/EtablissementType.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 26/01/2023 20:40
 */

namespace App\Form;

use App\Entity\Etablissement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EtablissementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelle')
            ->add('adresse', AdresseType::class, [
                'label' => 'Adresse du siège de l\'établissement',
            ])
            ->add('numero_SIRET', TextType::class, [
                'label' => "Numéro SIRET",
                'required' => true,
                'attr' => ['maxlength' => 14, 'minlength' => 14]
            ])
            ->add('numero_activite', TextType::class, [
                'label' => "Numéro d'activité",
                'required' => true,
                'attr' => ['maxlength' => 11, 'minlength' => 11]
            ])
            ->add('emailCentral', EmailType::class, [
                'label' => "Email central/CFVU",
                'required' => false,
            ])
            ->add('emailOreof', EmailType::class, [
                'label' => "Email de contact ORéOF",
                'required' => false,
            ])
            ->add('etablissement_information', EtablissementInformationType::class, [
                'label' => "Informations diverses"
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Etablissement::class,
            'translation_domain' => 'form'
        ]);
    }
}
