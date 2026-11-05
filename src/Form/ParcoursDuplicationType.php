<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\ParcoursDuplicationConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ParcoursDuplicationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nouveauLibelle', TextType::class, [
                'required' => true,
                'attr' => [
                    'placeholder' => 'ex: Informatique Graphique (copie)',
                ],
            ])
            ->add('nouveauSigle', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => 'ex: IG-2',
                ],
            ])
            ->add('dupliquerFichesMatieres', CheckboxType::class, [
                // 'label' => 'Dupliquer les fiches matières en fiches autonomes',
                // 'help' => 'Recommandé. Crée une nouvelle fiche matière pour chaque matière (avec réinitialisation de statut), permettant de modifier le nouveau parcours sans impacter le parcours d\'origine.',
                'required' => false,
            ])
            ->add('dupliquerContacts', CheckboxType::class, [
                // 'label' => 'Recopier les personnes contacts et leurs adresses',
                // 'help' => 'Clone les fiches de contact associées à ce parcours.',
                'required' => false,
            ])
            ->add('conserverResponsables', CheckboxType::class, [
                // 'label' => 'Conserver le responsable et le co-responsable de parcours',
                // 'help' => 'Si décoché, les responsables seront laissés vides dans le nouveau parcours.',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ParcoursDuplicationConfig::class,
        ]);
    }
}
