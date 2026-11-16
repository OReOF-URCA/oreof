<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Parcours;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class PilotageParcoursLienCourtType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('lienCourt', UrlType::class, [
                'label' => 'Lien court (permalien)',
                'required' => false,
                'default_protocol' => 'https',
                'help' => 'URL permanente vers les plateformes externes (ex : https://monmaster.gouv.fr/..., https://parcoursup.fr/...). Ce lien est reconduit d\'année en année à l\'identique.',
                'attr' => [
                    'placeholder' => 'https://...',
                    'maxlength' => 255,
                    'class' => 'w-full rounded-xl border-secondary-300 dark:border-secondary-700 dark:bg-secondary-800 text-sm focus:border-primary focus:ring-primary',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Parcours::class,
        ]);
    }
}
