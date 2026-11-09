<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Form/MentionType.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 10/02/2023 22:12
 */

namespace App\Form;

use App\Entity\Accreditation;
use App\Entity\Domaine;
use App\Entity\Mention;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MentionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('accreditation', EntityType::class, [
                'class' => Accreditation::class,
                'query_builder' => static function ($er) {
                    return $er->createQueryBuilder('a')
                        ->orderBy('a.anneeDebut', 'DESC');
                },
                'autocomplete' => true,
                'choice_label' => 'libelle',
                'label' => 'Période d\'accréditation',
                'required' => true,
            ])
            ->add('libelle', TextType::class, [
                'label' => 'Libellé',
                'required' => true,
            ])
            ->add('sigle', TextType::class, [
                'label' => 'Sigle',
                'help' => 'Le sigle est la dénomination courte de la mention, s\'il existe.',
                'required' => false,
            ])
            ->add('domaines', EntityType::class, [
                'query_builder' => static function ($er) {
                    return $er->createQueryBuilder('d')
                        ->orderBy('d.libelle', 'ASC');
                },
                'autocomplete' => true,
                'class' => Domaine::class,
                'choice_label' => 'libelle',
                'multiple' => true,
                'required' => true,
                'label' => 'Domaine(s)',
            ])
            ->add('codeApogee', TextType::class, [
                'label' => 'Code Apogée',
                'attr' => ['maxlength' => 1],
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Mention::class,
            'translation_domain' => 'form'
        ]);
    }
}
