<?php

namespace App\Form;

use App\Entity\Composante;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ExportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['fixed_composante'] instanceof Composante) {
            $builder->add('composante', EntityType::class, [
                'class' => Composante::class,
                'disabled' => true,
                'data' => $options['fixed_composante'],
                'choices' => [$options['fixed_composante']],
                'choice_label' => 'libelle',
                'label' => 'Composante',
                'attr' => [
                    'data-export-target' => 'composante',
                ],
            ]);
        } else {
            $builder->add('composante', EntityType::class, [
                'class' => Composante::class,
                'choices' => $options['composantes'],
                'choice_label' => 'libelle',
                'placeholder' => 'Choisir une composante',
                'label' => 'Composante',
                'required' => true,
                'autocomplete' => true,
                'attr' => [
                    'data-export-target' => 'composante',
                    'data-action' => 'change->export#refreshFrame',
                ],
            ]);
        }

        $builder->add('type_document', ChoiceType::class, [
            'choices' => array_flip($options['types_document']),
            'placeholder' => 'Choisir le format',
            'label' => 'Type d\'export',
            'required' => false,
            'attr' => [
                'data-export-target' => 'typeDocument',
                'data-action' => 'change->export#onTypeDocumentChange',
            ],
        ]);

        if ($options['show_global_documents'] && !empty($options['types_document_global'])) {
            $builder->add('type_document_global', ChoiceType::class, [
                'choices' => array_flip($options['types_document_global']),
                'placeholder' => 'Choisir le format',
                'label' => 'Exports spécifiques (sans choix de composante)',
                'required' => false,
                'attr' => [
                    'data-export-target' => 'typeDocumentGlobal',
                    'data-action' => 'change->export#onTypeGlobalChange',
                ],
            ]);
        }

        if ($options['show_date']) {
            $builder->add('date', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Préciser, si besoin, la date du conseil ou de la commission',
                'required' => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'composantes' => [],
            'types_document' => [],
            'types_document_global' => [],
            'show_global_documents' => true,
            'show_date' => true,
            'fixed_composante' => null,
            'csrf_protection' => false,
        ]);
    }
}
