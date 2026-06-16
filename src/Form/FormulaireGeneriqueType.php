<?php

namespace App\Form;

use App\Entity\Composante;
use App\Entity\RythmeFormation;
use App\Entity\TypeDiplome;
use App\Entity\User;
use App\Entity\Ville;
use App\Enums\NiveauFormationEnum;
use App\Enums\DureeParcoursUniteEnum;
use App\Enums\RegimeInscriptionEnum;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use UnitEnum;

class FormulaireGeneriqueType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $currentUser = $options['current_user'];
        $canChoose = $options['can_choose_responsable'];

        // Champs « responsable » (formation et parcours) : libre choix pour un admin,
        // verrouillé sur l'utilisateur courant sinon (cf. responsableFieldConfig).
        [$respMentionType, $respMentionOptions] = $this->responsableFieldConfig(
            $canChoose,
            $currentUser,
            'Responsable de la formation',
            'Vous serez le responsable de cette formation. Seul un administrateur peut désigner une autre personne.'
        );
        [$respParcoursType, $respParcoursOptions] = $this->responsableFieldConfig(
            $canChoose,
            $currentUser,
            'Responsable du parcours',
            'Vous serez le responsable de ce parcours. Seul un administrateur peut désigner une autre personne.'
        );

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
                'autocomplete' => true,
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
            ->add('responsableMention', $respMentionType, $respMentionOptions)

            // --- Parcours fields ---
            // Case proposée uniquement pour une formation neuve (gérée en JS). Décochée =
            // mono (la formation EST son parcours) ; cochée = la formation aura des parcours.
            ->add('plusieursParcours', CheckboxType::class, [
                'label' => 'Cette formation aura plusieurs parcours',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'data-formulaire-generique--creation-target' => 'multiParcoursCheck',
                    'data-action' => 'change->formulaire-generique--creation#toggleMultiParcours',
                ],
            ])
            ->add('libelle', TextType::class, [
                'label' => 'Libellé du parcours (le cas échéant)',
                'required' => false,
                'help' => "Laissez vide pour reprendre l'intitulé de la formation.",
            ])
            ->add('respParcours', $respParcoursType, $respParcoursOptions)
            ->add('dureeParcours', NumberType::class, [
                // Libellé par défaut « de formation » (mode mono) ; basculé en « du parcours » en JS.
                'label' => 'Durée de formation',
                'required' => true,
                'html5' => true,
                'scale' => 1,
                'attr' => [
                    'placeholder' => 'Ex. : 2',
                    'data-formulaire-generique--creation-target' => 'dureeInput',
                ],
            ])
            ->add('dureeParcoursUnite', EnumType::class, [
                'class' => DureeParcoursUniteEnum::class,
                'label' => 'Unité de durée',
                'required' => true,
                'placeholder' => 'Choisissez une unité',
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
                'label' => 'Rythme de formation',
                'placeholder' => 'Choisissez un rythme de formation',
                'required' => true,
                'attr' => [
                    'data-formulaire-generique--creation-target' => 'rythmeSelect',
                    'data-action' => 'change->formulaire-generique--creation#toggleRythmeTexte',
                ],
            ])
            ->add('rythmeFormationTexte', TextareaType::class, [
                'label' => 'Ou précisez ici le rythme en texte libre',
                'required' => false,
                'attr' => ['rows' => 5, 'maxlength' => 3000],
            ])
        ;
    }

    /**
     * Construit la configuration d'un champ « responsable » selon les droits.
     *
     * @return array{0: class-string, 1: array<string, mixed>} le type de champ et ses options
     */
    private function responsableFieldConfig(bool $canChoose, ?User $currentUser, string $label, string $help): array
    {
        if ($canChoose) {
            // Utilisateur privilégié (admin) : libre choix du responsable.
            // Champ facultatif : laissé vide, le créateur est désigné par défaut.
            return [EntityType::class, [
                'class' => User::class,
                'query_builder' => fn($er) => $er->createQueryBuilder('u')
                    ->orderBy('u.nom', 'ASC')
                    ->addOrderBy('u.prenom', 'ASC'),
                'choice_label' => fn(User $u) => $u->getDisplay(),
                'label' => $label,
                'placeholder' => 'Choisissez un responsable',
                'required' => false,
                'autocomplete' => true,
                'help' => 'Laissez vide pour vous désigner comme responsable.',
            ]];
        }

        // Utilisateur standard : il ne peut être responsable que de lui-même.
        // On affiche son nom dans un champ verrouillé (purement informatif) ;
        // la valeur réelle est forcée côté serveur dans le contrôleur.
        return [TextType::class, [
            'mapped' => false,
            'data' => $currentUser?->getDisplay(),
            'label' => $label,
            'required' => false,
            'disabled' => true,
            'help' => $help,
        ]];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'form',
            'can_choose_responsable' => false,
            'current_user' => null,
        ]);
        $resolver->setAllowedTypes('can_choose_responsable', 'bool');
        $resolver->setAllowedTypes('current_user', [User::class, 'null']);
    }
}