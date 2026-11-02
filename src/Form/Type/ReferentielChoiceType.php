<?php
namespace App\Form\Type;

use App\Entity\ReferentielValeur;
use App\Referentiel\ReferentielManager;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ReferentielChoiceType extends AbstractType
{
    public function __construct(private readonly ReferentielManager $manager) {}

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('referentiel');
        $resolver->setAllowedTypes('referentiel', 'string');
        $resolver->setDefaults([
            'class' => ReferentielValeur::class,
            'choice_label' => 'libelle',
            'placeholder' => 'Choisir une valeur',
        ]);
        $resolver->setNormalizer('choices', function (OptionsResolver $options, mixed $value): array {
            return $this->manager->getValeurs($options['referentiel']);
        });
    }

    public function getParent(): string { return EntityType::class; }
}
