<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Form/FormationSesType.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Form;

use App\Entity\Composante;
use App\Entity\Domaine;
use App\Entity\Formation;
use App\Entity\TypeDiplome;
use App\Entity\User;
use App\Form\Type\InlineCreateEntitySelectType;
use App\Form\Type\NiveauFormationType;
use App\Form\Type\YesNoType;
use App\Repository\DomaineRepository;
use App\Repository\MentionRepository;
use App\Repository\TypeDiplomeRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;

class FormationSesType extends AbstractType
{
    public function __construct(
        private readonly MentionRepository $mentionRepository,
        private readonly DomaineRepository $domaineRepository,
        private readonly TypeDiplomeRepository $typeDiplomeRepository
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('typeDiplome', EntityType::class, [
                'class' => TypeDiplome::class,
                'query_builder' => static function ($er) {
                    return $er->createQueryBuilder('t')
                        ->orderBy('t.libelle', 'ASC');
                },
                'choice_label' => 'libelle',
                'attr' => ['data-action' => 'change->formation#changeTypeDiplome']
            ])
            ->add('domaine', EntityType::class, [
                'class' => Domaine::class,
                'query_builder' => static function ($er) {
                    return $er->createQueryBuilder('d')
                        ->orderBy('d.libelle', 'ASC');
                },
                'choice_label' => 'libelle',
                'autocomplete' => true,
                'placeholder' => 'Choisir un domaine...',
                'attr' => ['data-action' => 'change->formation#changeDomaine']
            ])
            ->add('composantePorteuse', EntityType::class, [
                'placeholder' => 'Choisir la composante porteuse du projet',
                'class' => Composante::class,
                'query_builder' => static function ($er) {
                    return $er->createQueryBuilder('c')
                        ->orderBy('c.libelle', 'ASC');
                },
                'choice_label' => 'libelle',
                'required' => true,
                'autocomplete' => true,
                'help' => 'Indiquer la composante porteuse du projet, qui aura en charge le dépôt de la demande de création de la formation'
            ])
            ->add('mention', ChoiceType::class, [
                'attr' => ['data-action' => 'change->formation#changeMention'],
                'choices' => [
                    'Choisir une mention' => '',
                    'Autre mention' => 'autre'
                ],
                'required' => false,
                'mapped' => false,
                'help' => 'Si la mention n\'existe pas, veuillez la créer dans la section "Autre mention"'
            ])
            ->add('mentionTexte', TextType::class, [
                'attr' => ['data-action' => 'change->formation#changeMentionTexte'],
                'required' => false,
                'help' => 'Si la mention existe, veuillez la sélectionner dans la liste déroulante'
            ])
            ->add('codeMentionApogee', TextType::class, [
                'attr' => ['maxlength' => 1],
                'required' => false,
                'help' => 'Code de la mention dans Apogée'
            ])
            ->add('niveauEntree', NiveauFormationType::class, [
                'getter' => static fn (Formation $entity): ?string => $entity->getNiveauEntreeValue(),
                'setter' => static function (Formation $entity, ?string $value): void { $entity->setNiveauEntreeValue($value); },
            ])
            ->add('niveauSortie', NiveauFormationType::class, [
                'getter' => static fn (Formation $entity): ?string => $entity->getNiveauSortieValue(),
                'setter' => static function (Formation $entity, ?string $value): void { $entity->setNiveauSortieValue($value); },
            ])
            ->add('inRncp', YesNoType::class, [
                'attr' => ['data-action' => 'change->formation#changeInscriptionRNCP']
            ])
            ->add('codeRNCP', TextType::class, [
                'required' => false,
                'attr' => ['maxlength' => 10],
            ])
            ->add('responsableMention', InlineCreateEntitySelectType::class, [
                'class' => User::class,
                'label' => 'Responsable de la mention',
                'new_placeholder' => 'Email du responsable de la mention',
                'required' => true,
            ])
            ->add('coResponsable', InlineCreateEntitySelectType::class, [
                'class' => User::class,
                'label' => 'Co-Responsable de la mention',
                'new_placeholder' => 'Email du co-responsable de la mention',
                'required' => false,
            ])
            ->addEventListener(
                FormEvents::PRE_SET_DATA,
                function (FormEvent $event) {
                    $formation = $event->getData();
                    if ($formation instanceof Formation && $formation->getDomaine() !== null && $formation->getTypeDiplome() !== null) {
                        $form = $event->getForm();
                        $mentions = $this->mentionRepository->findByDomaineAndTypeDiplome(
                            $formation->getDomaine(),
                            $formation->getTypeDiplome()
                        );
                        $tabMentions = [
                            'Choisir une mention' => '',
                        ];
                        foreach ($mentions as $mention) {
                            $tabMentions[$mention->getLibelle()] = (string)$mention->getId();
                        }
                        $tabMentions['Autre mention'] = 'autre';

                        $initialValue = '';
                        if ($formation->getMention() !== null) {
                            $initialValue = (string)$formation->getMention()->getId();
                        } elseif ($formation->getMentionTexte() !== null && $formation->getMentionTexte() !== '') {
                            $initialValue = 'autre';
                        }

                        $form->add('mention', ChoiceType::class, [
                            'attr' => ['data-action' => 'change->formation#changeMention'],
                            'choices' => $tabMentions,
                            'label' => 'Mention',
                            'required' => false,
                            'mapped' => false,
                            'data' => $initialValue,
                            'help' => 'Si la mention n\'existe pas, veuillez la créer dans la section "Autre mention"'
                        ]);
                    }
                }
            )
            ->addEventListener(
                FormEvents::PRE_SUBMIT,
                function (FormEvent $event) {
                    $data = $event->getData();
                    if (!is_array($data)) {
                        return;
                    }

                    $form = $event->getForm();
                    $tabMentions = [
                        'Choisir une mention' => '',
                        'Autre mention' => 'autre',
                    ];

                    $domaineId = $data['domaine'] ?? null;
                    $typeDiplomeId = $data['typeDiplome'] ?? null;

                    if ($domaineId && $typeDiplomeId) {
                        $domaine = $this->domaineRepository->find($domaineId);
                        $typeDiplome = $this->typeDiplomeRepository->find($typeDiplomeId);
                        if ($domaine && $typeDiplome) {
                            $mentions = $this->mentionRepository->findByDomaineAndTypeDiplome($domaine, $typeDiplome);
                            foreach ($mentions as $mention) {
                                $tabMentions[$mention->getLibelle()] = (string)$mention->getId();
                            }
                        }
                    }

                    $submittedMention = $data['mention'] ?? null;
                    if ($submittedMention && $submittedMention !== 'autre' && !in_array((string)$submittedMention, $tabMentions, true)) {
                        $objMention = $this->mentionRepository->find($submittedMention);
                        if ($objMention !== null) {
                            $tabMentions[$objMention->getLibelle()] = (string)$objMention->getId();
                        }
                    }

                    $form->add('mention', ChoiceType::class, [
                        'attr' => ['data-action' => 'change->formation#changeMention'],
                        'choices' => $tabMentions,
                        'label' => 'Mention',
                        'required' => false,
                        'mapped' => false,
                        'help' => 'Si la mention n\'existe pas, veuillez la créer dans la section "Autre mention"'
                    ]);
                }
            )
            ->addEventListener(
                FormEvents::POST_SUBMIT,
                function (FormEvent $event) {
                    $formation = $event->getData();
                    if (!$formation instanceof Formation) {
                        return;
                    }
                    $form = $event->getForm();
                    $mention = $form->get('mention')->getData();
                    if ($mention !== '' && $mention !== null && $mention !== 'autre') {
                        $objMention = $this->mentionRepository->find($mention);
                        $formation->setMention($objMention);
                        $formation->setMentionTexte(null);
                    } elseif ($mention === 'autre') {
                        $formation->setMention(null);
                    } else {
                        $formation->setMention(null);
                    }
                }
            );

        $formation = $builder->getData();
        if ($formation instanceof Formation && ($formation->getTypeDiplome()?->isClassique() ?? true) === false) {
            $builder->remove('composantePorteuse');
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Formation::class,
            'typesDiplomes' => [],
            'translation_domain' => 'form'
        ]);
    }
}
