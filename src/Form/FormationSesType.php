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
use App\Enums\NiveauFormationEnum;
use App\Form\Type\YesNoType;
use App\Repository\MentionRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use UnitEnum;
use Symfony\Component\Form\Extension\Core\Type\FileType;

class FormationSesType extends AbstractType
{
    public function __construct(
        private readonly MentionRepository $mentionRepository
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $mentionRepository = $this->mentionRepository;

        $builder
            ->add('typeDiplome', EntityType::class, [
                'class' => TypeDiplome::class,
                'query_builder' => static function ($er) {
                    return $er->createQueryBuilder('t')
                        ->orderBy('t.libelle', 'ASC');
                },
                'autocomplete' => true,
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
                'attr' => ['data-action' => 'change->formation#changeDomaine']
            ])
            ->add('composantePorteuse', EntityType::class, [
                'attr' => ['placeholder' => 'Choisir la composante porteuse du projet'],
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
                    'Choisir une mention' => null,
                    'Autre mention' => 'autre'
                ],
                'required' => false,
                'validation_groups' => false,
                'mapped' => false,
                'autocomplete' => true,
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
            ->add('niveauEntree', EnumType::class, [
                'class' => NiveauFormationEnum::class,
                'choice_label' => static function (UnitEnum $choice): string {
                    return $choice->libelle();
                },
            ])
            ->add('niveauSortie', EnumType::class, [
                'class' => NiveauFormationEnum::class,
                'choice_label' => static function (UnitEnum $choice): string {
                    return $choice->libelle();
                },
            ])
            ->add('inRncp', YesNoType::class, [
                'attr' => ['data-action' => 'change->formation#changeInscriptionRNCP']
            ])
            ->add('codeRNCP', TextType::class, [
                'required' => false,
                'attr' => ['maxlength' => 10],
            ])
            ->add('responsableMention', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'display',
                'autocomplete' => true,
            ])
            ->add('coResponsable', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'display',
                'autocomplete' => true,
            ])

            ->addEventListener(
                FormEvents::POST_SUBMIT,
                static function (FormEvent $event) use ($mentionRepository) {
                    $formation = $event->getData();
                    $form = $event->getForm();
                    $mention = $form->get('mention')->getData();
                    if ($mention !== '' && $mention !== null) {
                        $objMention = $mentionRepository->find($mention);
                        $formation->setMention($objMention);
                    } else {
                        $formation->setMention(null);
                    }
                }
            )
            ->addEventListener(
                FormEvents::PRE_SET_DATA,
                static function (FormEvent $event) use ($mentionRepository) {
                    $formation = $event->getData();
                    if ($formation->getDomaine() !== null && $formation->getTypeDiplome() !== null) {
                        $form = $event->getForm();
                        $mentions = $mentionRepository->findByDomaineAndTypeDiplome(
                            $formation->getDomaine(),
                            $formation->getTypeDiplome()
                        );
                        foreach ($mentions as $mention) {
                            $tabMentions[$mention->getLibelle()] = $mention->getId();
                        }
                        $tabMentions['Autre'] = 'autre';
                        $tabMentions[''] = null;
                        $form->add('mention', ChoiceType::class, [
                            'attr' => ['data-action' => 'change->formation#changeMention'],
                            'choices' => $tabMentions,
                            'label' => 'Mention',
                            'required' => false,
                            'mapped' => false,
                            'help' => 'Si la mention n\'existe pas, veuillez la créer dans la section "Autre mention"'
                        ]);
                    }
                }
            );

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
