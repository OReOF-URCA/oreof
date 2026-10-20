<?php
namespace App\Form\Referentiel;

use App\Entity\ReferentielValeur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ReferentielValeurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['allow_code']) { $builder->add('code', TextType::class); }
        $builder->add('libelle', TextType::class)
            ->add('ordre', IntegerType::class)
            ->add('actif', CheckboxType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ReferentielValeur::class, 'allow_code' => false]);
    }
}
