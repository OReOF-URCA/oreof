<?php

namespace App\Form;

use App\Entity\CampagneCollecte;
use App\Entity\TimelineDate;
use App\Form\Type\YesNoType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use function Sodium\add;

class TimelineDateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelle', TextType::class, [
                'label' => 'timeline.libelle',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'timeline.description',
                'required' => false,
            ])
            ->add('icone', TextType::class, [
                'label' => 'timeline.icone',
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
