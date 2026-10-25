<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Form/ParcoursStep2Type.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Form;

use App\Entity\Parcours;
use App\Entity\TypeDiplome;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ParcoursStep3Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var TypeDiplome $typeDiplome */
        $typeDiplome = $options['typeDiplome'];

        $tabSemestre = [];
        for ($i = $typeDiplome->getSemestreDebut(); $i <= $typeDiplome->getSemestreFin(); $i++) {
            $tabSemestre['Semestre ' . $i] = $i;
        }

        $builder
            ->add('semestreDebut', ChoiceType::class, [
                'choices' => $tabSemestre,
            ])
            ->add('semestreFin', ChoiceType::class, [
                'choices' => $tabSemestre,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Parcours::class,
            'typeDiplome' => null,
            'translation_domain' => 'form'
        ]);
    }
}
