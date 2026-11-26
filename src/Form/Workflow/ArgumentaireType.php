<?php

declare(strict_types=1);

namespace App\Form\Workflow;

use App\DTO\Workflow\ArgumentaireData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class ArgumentaireType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isRequired = (bool)$options['argumentaire_required'];
        $minLength = (int)$options['min_length'];

        $constraints = [];
        if ($isRequired) {
            $constraints[] = new NotBlank(message: 'Le commentaire est obligatoire.');
        }

        if ($minLength > 0) {
            $constraints[] = new Callback(
                static function (?string $value, ExecutionContextInterface $context) use ($minLength, $isRequired): void {
                    $cleaned = preg_replace('/\s+/', '', (string)$value);
                    if ($cleaned === '' || $cleaned === null) {
                        if ($isRequired) {
                            $context->buildViolation('Le commentaire est obligatoire.')
                                ->addViolation();
                        }
                        return;
                    }

                    if (mb_strlen($cleaned) < $minLength) {
                        $context->buildViolation(sprintf('Le commentaire doit comporter au moins %d caractères (hors espaces).', $minLength))
                            ->addViolation();
                    }
                }
            );
        }

        $attr = [
            'rows' => 6,
            'placeholder' => $minLength > 0
                ? sprintf('Saisissez votre commentaire (au moins %d caractères hors espaces)...', $minLength)
                : 'Saisissez votre commentaire...',
        ];

        if ($isRequired) {
            $attr['required'] = 'required';
        }

        if ($minLength > 0) {
            $attr['minlength'] = $minLength;
            $attr['oninput'] = "const clean = this.value.replace(/\\s+/g, ''); if(clean.length < {$minLength}) { this.setCustomValidity('Veuillez saisir au moins {$minLength} caractères (hors espaces).'); } else { this.setCustomValidity(''); }";
        }

        $builder->add('argumentaire', TextareaType::class, [
            'required' => $isRequired,
            'label' => $options['label'] ?? 'Commentaire',
            'help' => $isRequired && $minLength > 0
                ? sprintf('Au minimum %d caractères requis (hors espaces).', $minLength)
                : null,
            'attr' => $attr,
            'constraints' => $constraints,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ArgumentaireData::class,
            'translation_domain' => 'process',
            'attr' => ['id' => 'modal_form'],
            'argumentaire_required' => true,
            'min_length' => 5,
        ]);
        $resolver->setAllowedTypes('argumentaire_required', 'bool');
        $resolver->setAllowedTypes('min_length', 'int');
    }
}
