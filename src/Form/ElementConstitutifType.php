<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Form/FicheMatiereType.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 21:42
 */

namespace App\Form;

use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\NatureUeEc;
use App\Entity\TypeEc;
use App\Entity\User;
use App\Form\Type\CardsChoiceType;
use App\Form\Type\InlineCreateEntitySelectType;
use App\Repository\FicheMatiereRepository;
use App\Repository\NatureUeEcRepository;
use App\Repository\TypeEcRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ElementConstitutifType extends AbstractType
{
    public function __construct(
        private FicheMatiereRepository $ficheMatiereRepository,
        private NatureUeEcRepository $natureUeEcRepository
    )
    {
    }
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $typeDiplome = $options['typeDiplome'];
        $parcours = $options['parcours'];
        $formation = $parcours->getFormation();
        $campagneCollecte = $options['campagneCollecte'];
        $isAdmin = $options['isAdmin'];

        $matieres = [];
        $matieres[] = $this->ficheMatiereRepository->findByParcours($parcours, $campagneCollecte);
        $matieres[] = $this->ficheMatiereRepository->findAllByHd($campagneCollecte);

        // fusionner et dédupliquer par id
        $mergedById = [];
        foreach (array_merge(...$matieres) as $m) {
            $mergedById[$m->getId()] = $m;
        }
        $choices = array_reverse(array_values($mergedById));

        if ($isAdmin) {
            $builder
                ->add('ordre', IntegerType::class)
                ->add('code', TextType::class, [
                'required' => false]);
        }

        //        $builder
        //            ->add('typeEc', EntityType::class, [
        //                'class' => TypeEc::class,
        //                'autocomplete' => true,
        //                'choice_label' => 'libelle',
        //                'query_builder' => fn (
        //                    TypeEcRepository $typeEcRepository
        //                ) => $typeEcRepository->findByTypeDiplomeAndFormationBuilder($typeDiplome, $formation),
        //                'required' => false,
        //            ])
        //            ->add('typeEcTexte', TextType::class, [
        //                'attr' => [
        //                    'maxlength' => 100,
        //                ],
        //                'required' => false,
        //                'mapped' => false,
        //            ])

        $natures = $this->natureUeEcRepository->findBy(['type' => NatureUeEc::Nature_EC], ['id' => 'ASC']);

        $builder->add('typeEc', InlineCreateEntitySelectType::class, [

            'class' => TypeEc::class,
            'choice_label' => 'libelle',
            'placeholder' => 'Choisir un type d’EC',
            'new_placeholder' => 'Nom du nouveau type…',
            'label' => "Type d'EC",
            'required' => true,

            // ton contexte (typediplome)
            'scope' => $typeDiplome,

            // filtrage de la liste (optionnel)
            'query_builder' => function (TypeEcRepository $repo) use ($typeDiplome, $formation) {
                return $repo->findByTypeDiplomeAndFormationBuilder($typeDiplome, $formation);
            },

            // évite doublons (optionnel)
            'find_existing' => function (string $label, $scope, EntityManagerInterface $em) {
                return $em->getRepository(TypeEc::class)->createQueryBuilder('t')
                    ->join('t.typeDiplomes', 'td')
                    ->andWhere('LOWER(t.libelle) = LOWER(:l)')
                    ->andWhere('td = :td')
                    ->setParameter('l', $label)
                    ->setParameter('td', $scope)
                    ->getQuery()
                    ->getOneOrNullResult();
            },

            // création (obligatoire)
            'create' => function (string $label, $typeDiplome, EntityManagerInterface $em) {
                $e = new TypeEc();
                $e->setLibelle($label);
                $e->addTypeDiplome($typeDiplome);
                return $e; // persist/flush gérés par le type (ou tu peux le faire ici)
            },
        ])
            ->add('natureUeEc', CardsChoiceType::class, [
                'label' => "Nature de l'élément",
                'choices' => $natures, // entités
                'choice_label' => fn($e) => $e->getLibelle(),
                'choice_value' => fn($e) => (string)$e?->getId(),
                'columns' => 3,
                'subtitle_property' => 'descriptionCourte', // ex: getDescriptionCourte()
                'icon_property' => 'icone',                  // ex: CI / TD / TP / ...
                'on_change_action' => 'change->ec--manage#updateNature',
            ])
            ->add('libelleLibre', TextType::class, ['mapped' => false, 'attr' => ['maxlength' => 250], 'data' => $builder->getData()?->getLibelle()])
            ->add('libelleChoix', TextType::class, ['mapped' => false, 'attr' => ['maxlength' => 250], 'data' => $builder->getData()?->getLibelle()])
            ->add('texteEcLibre', TextareaType::class, ['attr' => ['maxlength' => 250]])
            ->add('ficheMatiere', InlineCreateEntitySelectType::class, [
                'help' => '',
                'class' => FicheMatiere::class,
                'choice_label' => 'libelle',
                'query_builder' => fn(FicheMatiereRepository $repo) => $repo->qbForParcoursOrHd($parcours, $campagneCollecte),
                'placeholder' => 'Choisir dans la liste ou choisir "+ Créer Nouveau" pour ajouter une fiche matière',
                'new_placeholder' => 'Libelle de la fiche matière',
                'required' => true,
                'label' => 'Fiche matière obligatoire',

//                // évite doublons (optionnel)
//                'find_existing' => function (string $label, $scope, EntityManagerInterface $em) {
//                    return $em->getRepository(FicheMatiere::class)->createQueryBuilder('t')
//                        ->andWhere('LOWER(t.email) = LOWER(:l)')
//                        ->setParameter('l', $label)
//                        ->getQuery()
//                        ->getOneOrNullResult();
//                },

                // création (obligatoire)
                'create' => function (string $label, EntityManagerInterface $em) {
                    $e = new FicheMatiere();
                    $e->setLibelle($label);
                    return $e; // persist/flush gérés par le type (ou tu peux le faire ici)
                },

            ])

//        ->add('natureUeEc', EntityType::class, [
//            'class' => NatureUeEc::class,
//            'choice_label' => 'libelle',
//            'query_builder' => fn (
//                NatureUeEcRepository $natureUeEcRepository
//            ) => $natureUeEcRepository->findByBuilder(NatureUeEc::Nature_EC),
//            'required' => false,
//            'placeholder' => 'Choisissez une nature...',
//            'choice_attr' => function ($choice) {
//                return ['data-choix' => $choice->isChoix() ? 'true' : 'false'];
//            },
//            'attr' => ['data-action' => 'change->ec--manage#changeNatureEc'],
//            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ElementConstitutif::class,
            'translation_domain' => 'form',
            'typeDiplome' => null,
            'campagneCollecte' => null,
            'parcours' => null,
            'isAdmin' => false,
        ]);
    }
}
