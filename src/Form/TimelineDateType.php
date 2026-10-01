<?php

namespace App\Form;

use App\Entity\CampagneCollecte;
use App\Entity\TimelineDate;
use App\Enums\TimelineDateFlagEnum;
use App\Form\Type\YesNoType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TimelineDateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('ordre', HiddenType::class, [
                'empty_data' => '0',
            ])
            ->add('libelle', TextType::class, [
                'label' => 'timeline.libelle',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'timeline.description',
                'required' => false,
            ])
            ->add('icone', ChoiceType::class, [
                'label' => 'timeline.icone',
                'choices' => [
                    'Horloge / Temps' => 'icon:clock',
                    'Calendrier' => 'icon:calendar',
                    'Drapeau (Étape clé)' => 'icon:flag',
                    'Validation / Succès' => 'icon:check',
                    'Diplôme / Formation' => 'icon:graduation',
                    'Utilisateurs / Équipe' => 'icon:users',
                    'Édition / Saisie' => 'icon:edit',
                    'Livre / Enseignements' => 'icon:book-open',
                    'Document' => 'icon:document',
                    'Verrou / Clôture' => 'icon:lock',
                    'Couches / Maquette' => 'icon:layers',
                    'Envoi / Transmission' => 'icon:paper-plane',
                    'Cloche / Notification' => 'icon:bell',
                    'Information' => 'icon:info',
                    'Avertissement' => 'icon:warning',
                    'Outil / Configuration' => 'icon:wrench',
                    'Étoile' => 'icon:star',
                    'Œil / Consultation' => 'icon:eye',
                ],
                'required' => true,
                'placeholder' => 'Choisir une icône...',
            ])
            ->add('dateDebut', DateType::class, [
                'widget' => 'single_text',
                'label' => 'timeline.dateDebut',
                'help' => 'timeline.dateDebut.help',
                'required' => false,
            ])
            ->add('date', DateType::class, [
                'widget' => 'single_text',
                'label' => 'timeline.date',
                'help' => 'timeline.date.help',
                'required' => true,
            ])
            ->add('heure', TimeType::class, [
                'widget' => 'single_text',
                'required' => false,
                'label' => 'timeline.heure'
            ])
            ->add('inTimeline', YesNoType::class, [
                'label' => 'timeline.inTimeline',
            ])
            ->add('flag', EnumType::class, [
                'class' => TimelineDateFlagEnum::class,
                'label' => 'Type de date (Flag)',
                'choice_label' => fn (TimelineDateFlagEnum $choice) => match ($choice) {
                    TimelineDateFlagEnum::NONE => 'Aucun',
                    TimelineDateFlagEnum::OUVERTURE_COLLECTE => 'Ouverture de la collecte',
                    TimelineDateFlagEnum::CLOTURE_COLLECTE => 'Clôture de la collecte',
                    TimelineDateFlagEnum::CFVU => 'Date de la CFVU',
                    TimelineDateFlagEnum::TRANSMISSION_SES => 'Transmission SES',
                    TimelineDateFlagEnum::PUBLICATION => 'Publication',
                },
                'required' => true,
            ])
            ->add('modulesActifs', \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, [
                'label' => 'timeline.modulesActifs.label',
                'help' => 'timeline.modulesActifs.help',
                'choices' => \App\Enums\CampagneModuleEnum::getChoices(),
                'expanded' => true,
                'multiple' => true,
                'required' => false,
                'attr' => [
                    'columns' => 2,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TimelineDate::class,
            'translation_domain' => 'form'
        ]);
    }
}
