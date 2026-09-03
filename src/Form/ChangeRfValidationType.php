<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ChangeRfValidationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $meta = $options['meta'] ?? [];
        $transition = $options['transition'] ?? '';
        $process = $options['process'] ?? null;
        $processData = $options['processData'] ?? null;
        $laisserPasserValue = $options['laisserPasser'] ?? null;

        if (!empty($meta['hasDate'])) {
            $builder->add('date', DateType::class, [
                'widget' => 'single_text',
                'required' => true,
                'constraints' => [
                    new NotBlank(null, 'La date est obligatoire.'),
                ],
                'label' => 'valide.change_rf.date.' . $transition . '.label',
                'help' => 'valide.change_rf.helps.date.help',
                'translation_domain' => 'process',
                'attr' => [
                    'class' => 'form-control',
                ],
            ]);
        }

        if (!empty($meta['hasUpload'])) {
            $builder->add('file', FileType::class, [
                'required' => false,
                'label' => 'valide.change_rf.attente.fichier.label',
                'help' => 'valide.change_rf.attente.helps.fichier.help',
                'translation_domain' => 'process',
                'attr' => [
                    'accept' => 'application/pdf',
                    'class' => 'form-control',
                ],
            ]);

            $labelKey = '';
            if ($process && $processData && isset($process['label'])) {
                $labelKey = $process['label'] . '.valide.laisserPasser.' . $processData->placeTexte();
            } else {
                $labelKey = 'valide.change_rf.laisserPasser.label';
            }

            $helpKey = '';
            if ($process && isset($process['label'])) {
                $helpKey = 'valide.' . $process['label'] . '.helps.laisserPasser.help';
            } else {
                $helpKey = 'valide.change_rf.helps.laisserPasser.help';
            }

            $laisserPasserDefault = false;
            if ($laisserPasserValue) {
                if (is_array($laisserPasserValue) && isset($laisserPasserValue['etat']) && $laisserPasserValue['etat'] === 'valide') {
                    $laisserPasserDefault = true;
                } elseif (is_object($laisserPasserValue)) {
                    if (method_exists($laisserPasserValue, 'getEtat') && $laisserPasserValue->getEtat() === 'valide') {
                        $laisserPasserDefault = true;
                    } elseif (isset($laisserPasserValue->etat) && $laisserPasserValue->etat === 'valide') {
                        $laisserPasserDefault = true;
                    }
                }
            }

            $builder->add('laisserPasser', CheckboxType::class, [
                'required' => false,
                'data' => $laisserPasserDefault,
                'label' => $labelKey,
                'help' => $helpKey,
                'translation_domain' => 'process',
                'attr' => [
                    'class' => 'form-check-input',
                ],
            ]);
        }

        if (!empty($meta['hasArgumentaire'])) {
            $builder->add('argumentaire', TextareaType::class, [
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'L\'argumentaire est obligatoire.']),
                ],
                'label' => 'reserve.change_rf.argumentaire.label',
                'help' => 'reserve.change_rf.helps.argumentaire.help',
                'translation_domain' => 'process',
                'attr' => [
                    'rows' => 5,
                    'class' => 'form-control',
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'meta' => [],
            'transition' => '',
            'process' => null,
            'processData' => null,
            'laisserPasser' => null,
            'attr' => ['id' => 'modal_form'],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
