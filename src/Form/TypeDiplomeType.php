<?php

namespace App\Form;

use App\Entity\TypeDiplome;
use App\Form\Type\TextareaAutoSaveType;
use App\Form\Type\YesNoType;
use App\TypeDiplome\NonClassique\NonClassiqueHandler;
use App\TypeDiplome\TypeDiplomeHandlerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

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
                'required' => true,
            ])
            ->add('codifIntermediaire', YesNoType::class, [
                'label' => 'Codification intermédiaire',
                'required' => true,
            ])
            ->add('modalites_admission', TextareaAutoSaveType::class, [
                'label' => "Modalités d'admission",
                'required' => true,
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
            ->add('classique', CheckboxType::class, [
                'label' => 'Structure classique (semestres)',
                'required' => false,
                'row_attr' => ['class' => 'mt-3 mb-3 font-weight-bold'],
                'attr' => ['data-type-diplome-target' => 'classique', 'data-action' => 'change->type-diplome#toggle'],
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
            ->add('nbUeMin')
            ->add('nbUeMax')
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
            ->add('nbEcParUe')
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
            if (!$hasEcts && (!isset($data['nbEctsMaxUe']) || $data['nbEctsMaxUe'] === '')) {
                $data['nbEctsMaxUe'] = '0';
                $changed = true;
            }

            // Structure non classique : le select « Modèle MCCC » est grisé/désactivé
            // côté client et donc absent du POST. La colonne étant NOT NULL, on force
            // le handler non classique (même valeur que celle posée par le JS).
            $classique = !empty($data['classique']);
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
            'translation_domain' => 'form'
        ]);
    }
}
