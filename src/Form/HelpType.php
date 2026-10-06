<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file //wsl.localhost/Ubuntu/home/louca/oreof-stack/oreofv2/src/Form/HelpType.php
 * @author louca
 * @project oreofv2
 * @lastUpdate 29/04/2026 15:14
 */


namespace App\Form;

use App\Entity\Help;
use App\Enums\CentreGestionEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;

class HelpType extends AbstractType
{
    public function __construct(private RouterInterface $router)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $routes = $this->router->getRouteCollection()->all();
        $routeChoices = [];
        $centreChoices = [];

        foreach ($routes as $name => $route) {
            if (!str_starts_with($name, '_') && !str_starts_with($name, 'api_')) {
                $routeChoices[$name] = $name;
            }
        }

        // Clé virtuelle (sans route) : aide des pages d'erreur 403/404/500, voir base-speciale.html.twig
        $routeChoices['erreur'] = 'erreur';
        ksort($routeChoices);

        foreach (CentreGestionEnum::cases() as $centre) {
            if ($centre === CentreGestionEnum::CENTRE_GESTION_NULL) {
                continue;
            }

            $centreChoices[$centre->getLibelle()] = $centre->value;
        }

        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre de l\'aide',
                'attr' => ['class' => 'form-control mb-3']
            ])
            ->add('routeSlug', ChoiceType::class, [
                'choices' => $routeChoices,
                'label' => 'Page cible (Route)',
                'attr' => ['class' => 'form-select select2 mb-3'],
                'autocomplete' => true,
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Contenu explicatif',
                'attr' => ['class' => 'form-control', 'rows' => 12]
            ])
            ->add('centresShow', ChoiceType::class, [
                'choices' => $centreChoices,
                'label' => 'Centres autorisés',
                'help' => 'Sélectionnez les centres qui auront la visibilité "SHOW" pour cette aide. Laisser vide = pas de restriction par centre.',
                'required' => false,
                'multiple' => true,
                'expanded' => true,
                'empty_data' => [],
                'attr' => ['class' => 'mb-3']
            ])
            
            ->add('isActive', CheckboxType::class, [
                'label' => ' Activer cette aide',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Help::class]);
    }
}