<?php

namespace App\Form;

use App\Entity\PlateformeAdmission;
use App\Entity\TypeDiplome;
use App\Form\Type\TextareaAutoSaveType;
use App\Form\Type\YesNoType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
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
                'attr' => ['data-type-diplome-target' => 'classique', 'data-action' => 'change->type-diplome#toggle'],
            ])
            ->add('semestreDebut', null, [
                'row_attr' => ['class' => 'semestre-field'],
            ])
            ->add('semestreFin', null, [
                'row_attr' => ['class' => 'semestre-field'],
            ])
            ->add('nbUeMin')
            ->add('nbUeMax')
            ->add('nbEctsMaxUe')
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
            ->add('ectsObligatoireSurEc', YesNoType::class, ['empty_data' => true])
            ->add('mcccObligatoireSurEc', YesNoType::class, ['empty_data' => true])
            ->add('controleAssiduite', YesNoType::class, ['empty_data' => true])
            ->add('controleAssiduite', YesNoType::class, ['empty_data' => true])
            ->add('logo', FileType::class, [
                'label' => 'Logo',
                'multiple' => false,
                'required' => false,
                'mapped' => false,
                'attr' => ['accept' => 'image/png, image/jpeg'],
            ]);

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
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TypeDiplome::class,
            'translation_domain' => 'form'
        ]);
    }
}
