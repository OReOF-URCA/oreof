<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Parcours;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class PilotageParcoursDescriptifType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('descriptifHautPageAutomatique', TextareaType::class, [
                'label' => 'Descriptif haut de page généré automatiquement',
                'required' => false,
                'help' => 'Texte synthétique généré automatiquement selon l\'état de fermeture / non-ouverture du parcours.',
                'attr' => [
                    'rows' => 5,
                    'maxlength' => 2500,
                    'placeholder' => 'Ex : Parcours non ouvert pour l\'année...',
                    'class' => 'w-full rounded-xl border-secondary-300 dark:border-secondary-700 dark:bg-secondary-800 text-sm focus:border-primary focus:ring-primary',
                ],
            ])
            ->add('descriptifHautPage', TextareaType::class, [
                'label' => 'Descriptif haut de page (manuel / spécifique)',
                'required' => false,
                'help' => 'Texte spécifique affiché en tête de présentation du parcours sur le site institutionnel.',
                'attr' => [
                    'rows' => 5,
                    'maxlength' => 2500,
                    'placeholder' => 'Ex : Informations spécifiques d\'accueil, de candidature ou de rentrée...',
                    'class' => 'w-full rounded-xl border-secondary-300 dark:border-secondary-700 dark:bg-secondary-800 text-sm focus:border-primary focus:ring-primary',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Parcours::class,
        ]);
    }
}
