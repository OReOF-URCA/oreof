<?php

namespace App\Form;

use App\Entity\Composante;
use App\Entity\RythmeFormation;
use App\Entity\TypeDiplome;
use App\Entity\Ville;
use App\Enums\NiveauFormationEnum;
use App\Enums\DureeParcoursUniteEnum;
use App\Enums\RegimeInscriptionEnum;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use UnitEnum;

class FormulaireGeneriqueCreationCompletType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // --- Formation fields ---
            ->add('typeDiplome', EntityType::class, [
                'class' => TypeDiplome::class,
                'query_builder' => fn($er) => $er->createQueryBuilder('t')
                    ->where('t.classique = false')
                    ->orderBy('t.libelle', 'ASC'),
                'choice_label' => 'libelle',
                'label' => 'Type de diplôme',
                'placeholder' => 'Choisissez un type de diplôme',
                'required' => true,
                'attr' => [
                    'data-action' => 'change->formulaire-generique--creation#changeTypeDiplome',
                ],
            ])
            ->add('mentionExistante', HiddenType::class, [
                'required' => true,
                'mapped' => false,
            ])
            ->add('composantePorteuse', EntityType::class, [
                'class' => Composante::class,
                'query_builder' => fn($er) => $er->createQueryBuilder('c')->orderBy('c.libelle', 'ASC'),
                'choice_label' => 'libelle',
                'label' => 'Composante porteuse',
                'placeholder' => 'Choisissez une composante',
                'required' => false,
                'autocomplete' => true,
            ])
            ->add('niveauEntree', EnumType::class, [
                'class' => NiveauFormationEnum::class,
                'label' => 'Niveau d\'entrée',
                'choice_label' => fn(UnitEnum $c) => $c->libelle(),
            ])
            ->add('niveauSortie', EnumType::class, [
                'class' => NiveauFormationEnum::class,
                'label' => 'Niveau de sortie',
                'choice_label' => fn(UnitEnum $c) => $c->libelle(),
            ])

            // --- Parcours fields ---
            ->add('libelle', TextType::class, [
                'label' => 'Libellé du parcours',
                'required' => true,
            ])
            ->add('dureeParcours', NumberType::class, [
                'label' => 'Durée du parcours',
                'required' => true,
                'html5' => true,
                'scale' => 1,
            ])
            ->add('dureeParcoursUnite', EnumType::class, [
                'class' => DureeParcoursUniteEnum::class,
                'label' => 'Unité de durée',
                'required' => true,
                'choice_label' => fn(DureeParcoursUniteEnum $c) => $c->libelle(),
            ])

            ->add('formationExistante', HiddenType::class, [
                'required' => false,
                'mapped' => false,
            ])
            ->add('localisationMention', EntityType::class, [
                'class' => Ville::class,
                'query_builder' => fn($er) => $er->createQueryBuilder('v')->orderBy('v.libelle', 'ASC'),
                'choice_label' => 'libelle',
                'label' => 'Localisation(s)',
                'multiple' => true,
                'required' => false,
                'autocomplete' => true,
            ])
            ->add('composantesInscription', EntityType::class, [
                'class' => Composante::class,
                'query_builder' => fn($er) => $er->createQueryBuilder('c')->orderBy('c.libelle', 'ASC'),
                'choice_label' => 'libelle',
                'label' => 'Composante(s) d\'inscription',
                'multiple' => true,
                'required' => false,
                'autocomplete' => true,
            ])
            ->add('regimeInscription', EnumType::class, [
                'class' => RegimeInscriptionEnum::class,
                'label' => 'Régime(s) d\'inscription',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ])
            ->add('rythmeFormation', EntityType::class, [
                'class' => RythmeFormation::class,
                'choice_label' => 'libelle',
                'label' => 'Rythme de formation du parcours',
                'placeholder' => 'Choisissez un rythme de formation ou complétez le champ ci-dessous',
                'required' => false,
            ])
            ->add('rythmeFormationTexte', TextareaType::class, [
                'label' => 'Ou précisez ici le rythme en texte libre',
                'required' => false,
                'attr' => ['rows' => 5, 'maxlength' => 3000],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'form',
        ]);
    }
}