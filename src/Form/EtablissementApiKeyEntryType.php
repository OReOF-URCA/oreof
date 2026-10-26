<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;

class EtablissementApiKeyEntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plateforme', TextType::class, [
                'label' => 'Plateforme tierce',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ex : Apogée, Pégase, ORéBUT, Apprentissage (RNCP), ROME...',
                    'autocomplete' => 'off',
                ],
                'constraints' => [
                    new NotBlank(message: 'Veuillez renseigner le nom de la plateforme.'),
                    new Length(max: 100, maxMessage: 'Le nom de la plateforme ne doit pas dépasser {{ limit }} caractères.'),
                ],
            ])
            ->add('url', UrlType::class, [
                'label' => 'URL de l\'API',
                'required' => true,
                'default_protocol' => 'https',
                'attr' => [
                    'placeholder' => 'https://api.mon-etablissement.fr/v1',
                    'autocomplete' => 'off',
                ],
                'constraints' => [
                    new NotBlank(message: 'Veuillez renseigner l\'URL de l\'API.'),
                    new Url(message: 'Veuillez saisir une URL valide.'),
                ],
            ])
            ->add('login', TextType::class, [
                'label' => 'Identifiant / Login (optionnel)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Login ou client_id si requis par le service',
                    'autocomplete' => 'off',
                ],
                'constraints' => [
                    new Length(max: 255, maxMessage: 'L\'identifiant ne doit pas dépasser {{ limit }} caractères.'),
                ],
            ])
            ->add('apiKey', TextType::class, [
                'label' => 'Clé API / Token secret',
                'required' => true,
                'attr' => [
                    'type' => 'password',
                    'placeholder' => 'Clé d\'authentification ou jeton secret',
                    'autocomplete' => 'new-password',
                    'data-password-toggle-target' => 'input',
                ],
                'constraints' => [
                    new NotBlank(message: 'Veuillez renseigner la clé API ou le token.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'form',
        ]);
    }
}
