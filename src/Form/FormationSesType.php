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
use App\Form\Type\InlineCreateEntitySelectType;
use App\Form\Type\YesNoType;
use App\Repository\MentionRepository;
use Doctrine\ORM\EntityManagerInterface;
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
            ->add('responsableMention', InlineCreateEntitySelectType::class, [
                'help' => '',
                'class' => User::class,
                'choice_label' => 'display',
                'query_builder' => function ($er) {
                    return $er->createQueryBuilder('u')
                        ->orderBy('u.nom', 'ASC')
                        ->addOrderBy('u.prenom', 'ASC');
                },
                'placeholder' => 'Choisir dans la liste ou choisir "+ Créer nouveau" pour ajouter un utilisateur',
                'new_placeholder' => 'Email du responsable de la mention',
                'required' => true,
                'label' => 'Responsable de la mention',
                'ldap_check' => true,

                // évite doublons (optionnel)
                'find_existing' => function (string $label, $scope, EntityManagerInterface $em) {
                    return $em->getRepository(User::class)->createQueryBuilder('t')
                        ->andWhere('LOWER(t.email) = LOWER(:l)')
                        ->setParameter('l', $label)
                        ->getQuery()
                        ->getOneOrNullResult();
                },

                // création (obligatoire)
                'create' => function (string $label, EntityManagerInterface $em) {
                    $e = new User();
                    $e->setEmail($label);
                    return $e; // persist/flush gérés par le type (ou tu peux le faire ici)
                },

            ])
            ->add('coResponsable', InlineCreateEntitySelectType::class, [
                'help' => '',
                'class' => User::class,
                'choice_label' => 'display',
                'query_builder' => function ($er) {
                    return $er->createQueryBuilder('u')
                        ->orderBy('u.nom', 'ASC')
                        ->addOrderBy('u.prenom', 'ASC');
                },
                'placeholder' => 'Choisir dans la liste ou choisir "+ Créer nouveau" pour ajouter un utilisateur',
                'new_placeholder' => 'Email du co-responsable de la mention',
                'required' => false,
                'label' => 'Co-Responsable de la mention',
                'ldap_check' => true,

                // évite doublons (optionnel)
                'find_existing' => function (string $label, $scope, EntityManagerInterface $em) {
                    return $em->getRepository(User::class)->createQueryBuilder('t')
                        ->andWhere('LOWER(t.email) = LOWER(:l)')
                        ->setParameter('l', $label)
                        ->getQuery()
                        ->getOneOrNullResult();
                },

                // création (obligatoire)
                'create' => function (string $label, EntityManagerInterface $em) {
                    $e = new User();
                    $e->setEmail($label);
                    return $e; // persist/flush gérés par le type (ou tu peux le faire ici)
                },

            ])
            ->add('logo', FileType::class, [
                'multiple' => true,
                'required' => false,
                'mapped' => false,
                // Restriction du type de fichier pour afficher un feedback à l'utilisateur
                'attr' => ['accept' => 'image/png, image/jpeg'],
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
            )
;
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
