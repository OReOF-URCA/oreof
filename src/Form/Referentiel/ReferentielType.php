<?php
namespace App\Form\Referentiel;

use App\Entity\Referentiel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ReferentielType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('libelle', TextType::class)
            ->add('administrable', CheckboxType::class, ['required' => false])
            ->add('extensible', CheckboxType::class, ['required' => false]);
        if ($options['allow_code']) { $builder->add('code', TextType::class); }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Referentiel::class, 'allow_code' => false]);
    }
}
