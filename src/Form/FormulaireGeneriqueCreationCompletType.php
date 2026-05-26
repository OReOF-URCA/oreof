<?php

namespace App\Form;

use App\Entity\Composante;
use App\Entity\Domaine;
use App\Entity\TypeDiplome;
use App\Entity\Ville;
use App\Enums\NiveauFormationEnum;
use App\Enums\TypeParcoursEnum;
use App\Enums\DureeParcoursUniteEnum;
use App\Enums\RegimeInscriptionEnum;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
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

            // --- Inline mention creation ---
            ->add('nouvelleMentionLibelle', TextType::class, [
                'label' => 'Libellé',
                'required' => false,
            ])
            ->add('nouvelleMentionSigle', TextType::class, [
                'label' => 'Sigle',
                'help' => 'La dénomination courte de la mention, si elle existe.',
                'required' => false,
            ])
            ->add('nouvelleMentionDomaine', EntityType::class, [
                'class' => Domaine::class,
                'query_builder' => fn($er) => $er->createQueryBuilder('d')->orderBy('d.libelle', 'ASC'),
                'choice_label' => 'libelle',
                'label' => 'Domaine',
                'placeholder' => 'Choisissez un domaine',
                'required' => false,
                'autocomplete' => true,
            ])
            ->add('nouvelleMentionCodeApogee', TextType::class, [
                'label' => 'Code Apogée',
                'required' => false,
                'attr' => ['maxlength' => 1],
            ])

            // --- Parcours fields ---
            ->add('libelle', TextType::class, [
                'label' => 'Libellé du parcours',
                'required' => true,
            ])
            ->add('sigle', TextType::class, [
                'label' => 'Sigle du parcours',
                'required' => false,
                'attr' => ['maxlength' => 15],
            ])
            ->add('typeParcours', EnumType::class, [
                'class' => TypeParcoursEnum::class,
                'label' => 'Type de parcours',
                'translation_domain' => 'form',
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
            ->add('sigleFormation', TextType::class, [
                'label' => 'Sigle de la formation',
                'required' => false,
                'mapped' => false,
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