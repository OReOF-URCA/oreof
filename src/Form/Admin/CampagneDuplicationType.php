<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\DTO\Campagne\CampagneDuplicationDTO;
use App\Entity\CampagneCollecte;
use App\Form\Type\YesNoType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CampagneDuplicationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $currentYear = (int) date('Y');
        $nextYear = $currentYear + 1;
        $nextNextYear = $nextYear + 1;

        $builder
            ->add('sourceCampagne', EntityType::class, [
                'class' => CampagneCollecte::class,
                'label' => 'Campagne Source (à copier)',
                'choice_label' => static fn (CampagneCollecte $c) => sprintf('%s (%s)', $c->getLibelle(), $c->getAnneeUniversitaire()?->getLibelle() ?? (string)$c->getAnnee()),
                'required' => true,
                'placeholder' => 'Sélectionnez la campagne source...',
                'attr' => [
                    'data-action' => 'change->campagne-duplication#changeSource',
                ],
            ])
            ->add('libelleAnneeUniversitaire', TextType::class, [
                'label' => "Libellé de l'Année Universitaire cible",
                'help' => 'Format attendu : YYYY-YYYY (ex: 2026-2027)',
                'attr' => [
                    'placeholder' => sprintf('%d-%d', $nextYear, $nextNextYear),
                ],
            ])
            ->add('anneeUniversitaire', IntegerType::class, [
                'label' => "Année de référence (début d'année)",
                'help' => 'Année numérique (ex: 2026)',
                'attr' => [
                    'placeholder' => (string) $nextYear,
                ],
            ])
            ->add('libelleCampagne', TextType::class, [
                'label' => 'Libellé de la Nouvelle Campagne de Collecte',
                'help' => 'Ex : Campagne 2026-2027 ou Accréditation 2026',
                'attr' => [
                    'placeholder' => sprintf('%d-%d', $nextYear, $nextNextYear),
                ],
            ])
            ->add('anneeCampagne', IntegerType::class, [
                'label' => 'Année de la campagne',
                'attr' => [
                    'placeholder' => (string) $nextYear,
                ],
            ])
            ->add('codeApogeeCampagne', TextType::class, [
                'label' => 'Code Apogée Campagne (1 caractère)',
                'required' => true,
                'attr' => [
                    'maxlength' => 1,
                    'placeholder' => '6',
                ],
            ])
            ->add('slugSuffix', TextType::class, [
                'label' => 'Suffixe de slug (pour éviter les collisions d’URLs)',
                'help' => 'Suffixe ajouté aux slugs des formations et matières',
                'attr' => [
                    'placeholder' => '-' . $nextYear,
                ],
            ])
            ->add('couleur', ChoiceType::class, [
                'label' => 'Couleur d’accentuation',
                'choices' => [
                    'Bleu (Primary)' => 'primary',
                    'Vert (Success)' => 'success',
                    'Violet (Indigo)' => 'indigo',
                    'Orange (Warning)' => 'warning',
                    'Rouge (Danger)' => 'danger',
                    'Gris (Secondary)' => 'secondary',
                ],
            ])
            ->add('setCampagneDefaut', YesNoType::class, [
                'label' => 'Définir comme campagne active par défaut',
            ])
            ->add('dateOuvertureDpe', DateType::class, [
                'label' => 'Date d’ouverture de la collecte',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('dateClotureDpe', DateType::class, [
                'label' => 'Date de clôture de la collecte',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('dateTransmissionSes', DateType::class, [
                'label' => 'Date limite de transmission aux SES',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('dateCfvu', DateType::class, [
                'label' => 'Date de passage en CFVU',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('datePublication', DateType::class, [
                'label' => 'Date de publication officielle',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('duplicateCompetences', YesNoType::class, [
                'label' => 'Dupliquer les Référentiels de Compétences (Blocs, Compétences et BUT)',
            ])
            ->add('duplicateMutualisations', YesNoType::class, [
                'label' => 'Dupliquer et reconnecter les Mutualisations (Semestres, UEs, Fiches)',
            ])
            ->add('duplicateContacts', YesNoType::class, [
                'label' => 'Dupliquer les Contacts et Adresses associées',
            ])
            ->add('duplicateMccc', YesNoType::class, [
                'label' => 'Dupliquer les Modalités de Contrôle des Connaissances (MCCC)',
            ])
            ->add('duplicateDroits', YesNoType::class, [
                'label' => 'Affecter les Droits d’accès & Profils (RF, co-RF, RP, co-RP) sur la nouvelle campagne',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CampagneDuplicationDTO::class,
        ]);
    }
}
