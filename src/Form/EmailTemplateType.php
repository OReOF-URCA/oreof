<?php

namespace App\Form;

use App\Entity\EmailTemplate;
use App\Form\Type\SubjectsJsonType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EmailTemplateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string,string> $workflows */
        $workflows = $options['template_workflows']; // ex: fournis depuis le contrôleur
        $builder
            ->add('workflow', ChoiceType::class, [
                'choices' => $workflows,
                'placeholder' => '— choisir une clé —',
                'required' => true,
                'label' => 'Clé fonctionnelle',
            ])
            ->add('subject', TextType::class, [
                'label' => 'Sujet',
                'attr' => ['data-email-template-target' => 'subject']
            ])
            ->add('subjects', SubjectsJsonType::class, [
                'required' => false,
                'label' => 'Sujets par variante (JSON)',
                'help' => 'Ex: {"parcours_par_defaut":"Sujet {{ user.fullName }}","parcours":"Sujet {{ formation.code }}"}',
                'attr' => [
                    'rows' => 6,
                    'data-email-template-target' => 'subjectsJson'
                ],
            ])
            ->add('bodyHtml', TextareaType::class, [
                'label' => 'Corps HTML (Twig autorisé)',
                'attr' => [
                    'rows' => 18,
                    'data-email-template-target' => 'bodyHtml'
                ],
            ])
            ->add('bodyText', TextareaType::class, [
                'required' => false,
                'label' => 'Texte brut (optionnel)',
                'attr' => [
                    'rows' => 8,
                    'data-email-template-target' => 'bodyText'
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EmailTemplate::class,
            'template_workflows' => [], // contrôle fourni par le contrôleur
        ]);
        $resolver->setAllowedTypes('template_workflows', 'array');
    }
}
