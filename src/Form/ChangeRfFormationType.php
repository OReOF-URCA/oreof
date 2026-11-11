<?php

namespace App\Form;

use App\Entity\User;
use App\Enums\TypeRfEnum;
use App\Form\Type\InlineCreateEntitySelectType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use UnitEnum;

class ChangeRfFormationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('typeRf', EnumType::class, [
                'class' => TypeRfEnum::class,
                'expanded' => true,
                'translation_domain' => 'form',
                'choice_label' => static function (TypeRfEnum $choice): string {
                    return $choice->getLibelle();
                },
            ])
            ->add('user', InlineCreateEntitySelectType::class, [
                'class' => User::class,
                'label' => 'Nouveau (co-)responsable de formation',
                'new_placeholder' => 'Email du nouveau (co-)responsable de formation',
                'placeholder' => 'Choisir dans la liste ou choisir "+" pour ajouter un utilisateur',
                'required' => true,
            ])
            ->add('datePriseFonction', DateType::class, [
                'label' => 'Date de prise de fonction',
                'widget' => 'single_text',
                'required' => true,
                'help' => 'Attention : cette date pourra impacter l\'année en cours et/ou l\'année précédente selon les dates de la campagne.',
            ])
            ->add('commentaire', TextareaType::class, [
                'label' => 'Commentaire sur le changement de (co-)responsable de formation',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => \App\DTO\ChangeRf::class,
        ]);
    }
}

