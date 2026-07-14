<?php

namespace App\Form;

use App\Entity\TypeDiplome;
use App\Form\Type\TextareaAutoSaveType;
use App\Form\Type\YesNoType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\TypeDiplome\NonClassique\NonClassiqueHandler;
use App\TypeDiplome\TypeDiplomeHandlerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;

class TypeDiplomeType extends AbstractType
{
    private iterable $typeDiplomeHandlers;

    public function __construct(
        #[TaggedIterator('app.type_diplome_handler')] iterable $typeDiplomeHandlers
    )
    {
        $this->typeDiplomeHandlers = $typeDiplomeHandlers;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [];
        foreach ($this->typeDiplomeHandlers as $handler) {
            $get_class = get_class($handler);
            $choices[$get_class] = $get_class;
        }

        $builder
            ->add('libelle')
            ->add('libelle_court')
            ->add('codeApogee', TextType::class, [
                'label' => 'Code Apogée',
                'attr' => ['maxlength' => 1],
                'required' => false,
                // Colonne NOT NULL + setter non-nullable : un champ vide retombe
                // sur une chaîne vide plutôt que null.
                'empty_data' => '',
            ])
            // Champ déprécié : conservé (entité et colonne intactes) mais masqué et
            // verrouillé. Les nouveaux types retombent sur le défaut « false » de
            // l'entité ; un champ désactivé n'est de toute façon pas modifiable.
            ->add('codifIntermediaire', YesNoType::class, [
                'label' => 'Codification intermédiaire',
                'required' => false,
                'disabled' => true,
                'row_attr' => ['class' => 'd-none'],
            ])
            ->add('modalites_admission', TextareaAutoSaveType::class, [
                'label' => "Modalités d'admission",
                'required' => false,
                'attr' => [
                    'rows' => 6,
                    'maxlength' => 3000
                ]
            ])
            ->add('prerequis_obligatoires', TextareaAutoSaveType::class, [
                'label' => "Prérequis obligatoires",
                'required' => false,
                'attr' => [
                    'rows' => 6,
                    'maxlength' => 3000
                ]
            ])
            ->add('presentationFormation', TextareaAutoSaveType::class, [
                'label' => "Présentation des formations",
                'required' => false,
                'attr' => [
                    'rows' => 5,
                    'maxlength' => 3000
                ]
            ])
            ->add('insertionProfessionnelle', TextareaAutoSaveType::class, [
                'label' => 'Devenir des diplômés',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'maxlength' => 3000
                ]
            ])
            ->add('classique', ChoiceType::class, [
                'label' => 'Type de formation',
                'choices' => [
                    'Formation accréditée' => true,
                    'Formation non-accréditée' => false,
                ],
                'expanded' => true,
                'multiple' => false,
                'required' => true,
                'placeholder' => false,
                // Valeurs de soumission explicites et non vides : évite qu'un choix
                // (notamment « non-accréditée ») soit interprété comme « rien de coché »
                // sur un champ obligatoire.
                'choice_value' => fn(?bool $choice) => $choice === true ? 'accreditee' : ($choice === false ? 'non_accreditee' : ''),
                'row_attr' => ['class' => 'mt-3 mb-3 font-weight-bold'],
                'choice_attr' => fn() => [
                    'data-type-diplome-target' => 'classique',
                    'data-action' => 'change->type-diplome#toggle',
                ],
            ])
            ->add('passageCfvu', CheckboxType::class, [
                'label' => 'Passage au CFVU',
                'required' => false,
                'row_attr' => ['class' => 'mt-3 mb-3 font-weight-bold'],
                'help' => "Décoché : les formations/parcours créés via le formulaire générique sont initialisés sans passage en CFVU.",
            ])
            ->add('semestreDebut', null, [
                'row_attr' => ['class' => 'semestre-field'],
            ])
            ->add('semestreFin', null, [
                'row_attr' => ['class' => 'semestre-field'],
            ])
            ->add('nbUeMin', null, [
                'row_attr' => ['class' => 'semestre-field'],
            ])
            ->add('nbUeMax', null, [
                'row_attr' => ['class' => 'semestre-field'],
            ])
            ->add('hasEcts', YesNoType::class, [
                'label' => 'Utilise les ECTS',
                'empty_data' => true,
                'choice_attr' => fn() => [
                    'data-type-diplome-target' => 'hasEcts',
                    'data-action' => 'change->type-diplome#toggleEcts',
                ],
            ])
            ->add('nbEctsParSemestre', null, [
                'label' => 'Nombre d\'ECTS par semestre (laisser vide si pas de quota fixe)',
                'required' => false,
                'row_attr' => ['class' => 'ects-field'],
            ])
            ->add('nbEctsMaxUe', null, [
                'row_attr' => ['class' => 'ects-field'],
            ])
            ->add('nbEctsMaxUe')
            ->add('nbEcParUe')
            // « Nombre maximum d'EC par UE » : sans objet à la fois pour une formation
            // non accréditée ET pour un diplôme sans ECTS → présent dans les deux groupes.
            ->add('nbEcParUe', null, [
                'row_attr' => ['class' => 'semestre-field ects-field'],
            ])
            ->add('ModeleMcc', ChoiceType::class, [
                'choices' => $choices,
            ])
            ->add('debutSemestreFlexible', null, [
                'row_attr' => ['class' => 'semestre-field'],
            ])
            ->add('hasMemoire', YesNoType::class)
            ->add('hasStage', YesNoType::class)
            ->add('hasSituationPro', YesNoType::class)
            ->add('hasProjet', YesNoType::class)
            ->add('ectsObligatoireSurEc', YesNoType::class, ['empty_data' => true, 'row_attr' => ['class' => 'ects-field']])
            ->add('mcccObligatoireSurEc', YesNoType::class, ['empty_data' => true])
            ->add('controleAssiduite', YesNoType::class, ['empty_data' => true])
            ->add('controleAssiduite', YesNoType::class, ['empty_data' => true])
            ->add('mcccObligatoireSurEc', YesNoType::class, ['empty_data' => true, 'row_attr' => ['class' => 'semestre-field']])
            ->add('controleAssiduite', YesNoType::class, ['empty_data' => true]);

        // Le logo n'est proposé dans le formulaire qu'à la création. En modification, il
        // est déjà géré par la carte dédiée (upload/suppression en AJAX, contrôleur
        // « type-diplome-logos ») : l'inclure ici l'afficherait en double.
        $typeDiplome = $builder->getData();
        if ($typeDiplome === null || $typeDiplome->getId() === null) {
            $builder->add('logo', FileType::class, [
                'label' => 'Logo',
                'multiple' => false,
                'required' => false,
                'mapped' => false,
                'attr' => ['accept' => 'image/png, image/jpeg'],
            ]);
        }

        // Pré-remplir les plateformes déjà associées avec leurs années
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            /** @var TypeDiplome|null $typeDiplome */
            $typeDiplome = $event->getData();
            $form = $event->getForm();

            $nbAnnees = 1;
            if ($typeDiplome && $typeDiplome->getId()) {
                $nbAnnees = $typeDiplome->getNbAnnee();
            }

            // Récupérer les associations plateformes/années existantes
            $plateformesData = [];
            if ($typeDiplome && $typeDiplome->getId()) {
                foreach ($typeDiplome->getTypeDiplomePlateformeAdmissions() as $tpa) {
                    $plateformesData[] = [
                        'plateforme' => $tpa->getPlateforme(),
                        'annees' => $tpa->getAnnees() ?? [],
                    ];
                }
            }

            $form->add('plateformesAdmission', CollectionType::class, [
                'entry_type' => TypeDiplomePlateformeType::class,
                'entry_options' => [
                    'label' => false,
                    'nb_annees' => $nbAnnees,
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'mapped' => false,
                'data' => $plateformesData,
                'label' => 'Plateformes d\'admission',
                'prototype' => true,
                'attr' => [
                    'class' => 'plateformes-collection',
                ],
            ]);
        })
            ->add('controleAssiduite', YesNoType::class, ['empty_data' => true])
            ->add('hasEcts', YesNoType::class, [
                'label' => 'Utilise les ECTS',
                'empty_data' => true,
            ])
            ->add('nbEctsParSemestre', null, [
                'label' => 'Nombre d\'ECTS par semestre (laisser vide si pas de quota fixe)',
                'required' => false,
            ])
            ->add('controleAssiduite', YesNoType::class, ['empty_data' => true]);

        // Quand le diplôme n'utilise pas les ECTS, le champ « Nombre maximum d'ECTS
        // par UE » est grisé côté client et sans objet. On lui fournit une valeur
        // valide côté serveur (la colonne est NOT NULL) pour ne pas dépendre du JS
        // et éviter l'erreur « Veuillez saisir un entier » sur un champ vide.
        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
            $data = $event->getData();
            if (!is_array($data)) {
                return;
            }

            $changed = false;

            // YesNoType : « Oui » est soumis avec la valeur '1' ; tout le reste
            // (vide, '0', absent) signifie que les ECTS ne sont pas utilisés.
            $hasEcts = !empty($data['hasEcts']) && $data['hasEcts'] !== '0';
            $classique = ($data['classique'] ?? null) === 'accreditee';

            // Les champs entiers NOT NULL grisés côté client (donc absents du POST)
            // selon les conditions ci-dessous recevraient null → violation NOT NULL.
            // On leur fournit une valeur de repli (0) lorsqu'ils sont sans objet.
            $defaultZeroIfMissing = static function (string $key) use (&$data, &$changed): void {
                if (!isset($data[$key]) || $data[$key] === '') {
                    $data[$key] = '0';
                    $changed = true;
                }
            };

            if (!$hasEcts) {
                // Diplôme sans ECTS : « Nombre maximum d'ECTS par UE » sans objet.
                $defaultZeroIfMissing('nbEctsMaxUe');
                // « Les ECTS sont-ils obligatoires sur les EC ? » forcé à « Non » (0).
                $data['ectsObligatoireSurEc'] = '0';
                $changed = true;
            }
            if (!$classique) {
                // Formation non accréditée : champs de structure « semestre » sans objet.
                $defaultZeroIfMissing('nbUeMin');
                $defaultZeroIfMissing('nbUeMax');
                // « Les MCCC sont-ils obligatoires sur les EC ? » forcé à « Non » (0).
                $data['mcccObligatoireSurEc'] = '0';
                $changed = true;
            }
            if (!$classique || !$hasEcts) {
                // « Nombre maximum d'EC par UE » : sans objet si non accréditée OU sans ECTS.
                $defaultZeroIfMissing('nbEcParUe');
            }

            // Structure non classique : le select « Modèle MCCC » est grisé/désactivé
            // côté client et donc absent du POST. La colonne étant NOT NULL, on force
            // le handler non classique (même valeur que celle posée par le JS).
            $classique = ($data['classique'] ?? null) === 'accreditee';
            if (!$classique && empty($data['ModeleMcc'])) {
                $data['ModeleMcc'] = NonClassiqueHandler::class;
                $changed = true;
            }

            if ($changed) {
                $event->setData($data);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TypeDiplome::class,
            'translation_domain' => 'form',
            // Contrôleur Stimulus qui grise/désactive les champs sans objet selon le type
            // de formation (accréditée ?) et l'usage des ECTS. Sans ce data-controller sur
            // le <form>, les data-action/target des champs n'étaient jamais connectés et
            // tous les champs restaient obligatoires.
            'attr' => ['data-controller' => 'type-diplome'],
        ]);
    }
}
