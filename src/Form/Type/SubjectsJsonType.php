<?php
declare(strict_types=1);

namespace App\Form\Type;

use App\Form\Transformer\SubjectsJsonTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SubjectsJsonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new SubjectsJsonTransformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // rien de spécial
    }

    public function getParent(): string
    {
        return TextareaType::class;
    }
}
