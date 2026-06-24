<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Form/FormationStep1Type.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Form;

use App\Entity\Composante;
use App\Entity\Formation;
use App\Entity\User;
use App\Entity\Ville;
use App\Enums\RegimeInscriptionEnum;
use App\Form\Type\TextareaAutoSaveType;
use App\Repository\ComposanteRepository;
use App\Repository\VilleRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FormationStep1Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Responsable / co-responsable modifiables directement sur la page d'édition
        // pour les formations non classiques uniquement ; verrouillés (grisés) pour les
        // classiques, où le changement passe par un circuit dédié.
        $formation = $builder->getData();
        $verrouille = $formation?->getTypeDiplome()?->isClassique() ?? true;

        $builder
            ->add('responsableMention', EntityType::class, [
                'required' => false,
                'help' => '',
                'disabled' => $verrouille,
                'class' => User::class,
                'choice_label' => 'display',
                'query_builder' => fn($er) => $er->createQueryBuilder('u')
                    ->orderBy('u.nom', 'ASC')
                    ->addOrderBy('u.prenom', 'ASC'),
                'attr' => ['data-action' => 'change->formation--step1#saveRespFormation'],
            ])
            ->add('coResponsable', EntityType::class, [
                'required' => false,
                'disabled' => $verrouille,
                'help' => '',
                'class' => User::class,
                'choice_label' => 'display',
                'query_builder' => fn($er) => $er->createQueryBuilder('u')
                    ->orderBy('u.nom', 'ASC')
                    ->addOrderBy('u.prenom', 'ASC'),
                'attr' => ['data-action' => 'change->formation--step1#saveCoRespFormation'],
            ])
            ->add('sigle', TextType::class, [
                'required' => false,
                'attr' => ['data-action' => 'change->formation--step1#changeSigle', 'maxlength' => 250],
            ])
            ->add('localisationMention', EntityType::class, [
                'class' => Ville::class,
                'query_builder' => function (VilleRepository $villeRepository) {
                    return $villeRepository->createQueryBuilder('v')
                        ->orderBy('v.libelle', 'ASC');
                },
                'choice_label' => 'libelle',
                'multiple' => true,
                'expanded' => true,
                'required' => true,
                'help' => 'Plusieurs choix possibles',
                'choice_attr' => function () {
                    return ['data-action' => 'change->formation--step1#changeVille'];
                },
            ])
            ->add('composantesInscription', EntityType::class, [
                'class' => Composante::class,
                'choice_label' => 'libelle',
                'help' => 'Plusieurs choix possibles',
                'multiple' => true,
                'expanded' => true,
                'query_builder' => function (ComposanteRepository $composanteRepository) {
                    return $composanteRepository->createQueryBuilder('comp')
                        ->orderBy('comp.libelle', 'ASC');
                },
                'choice_attr' => function () {
                    return ['data-action' => 'change->formation--step1#changeComposanteInscription'];
                },
                'attr' => ['data-action' => 'change->formation--step6#changeComposanteInscription']
            ])
            ->add('regimeInscription', EnumType::class, [
                'help' => 'Régime d\'inscription',
                'class' => RegimeInscriptionEnum::class,
                'translation_domain' => 'form',
                'multiple' => true,
                'expanded' => true,
                'attr' => ['data-action' => 'change->formation--step1#changeRegimeInscription']
            ])
            ->add('modalitesAlternance', TextareaAutoSaveType::class, [
                'help' => 'Indiquez en 3000 caractères maximum les périodes et leurs durées en centre ou en entreprise.',
                'attr' => [
                    'rows' => 10,
                    'maxlength' => 3000,
                    'data-action' => 'change->formation--step1#saveModalitesAlternance'
                ],
            ]);

        if (!$verrouille) {
            $builder->add('composantePorteuse', EntityType::class, [
                'class' => Composante::class,
                'choice_label' => 'libelle',
                'label' => 'Composante porteuse',
                'required' => false,
                'placeholder' => 'Choisissez une composante',
                'query_builder' => fn($er) => $er->createQueryBuilder('c')->orderBy('c.libelle', 'ASC'),
                'attr' => ['data-action' => 'change->formation--step1#changeComposantePorteuse'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Formation::class,
            'translation_domain' => 'form'
        ]);
    }
}
