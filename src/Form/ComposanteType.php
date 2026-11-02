<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Form/ComposanteType.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 22/02/2023 10:54
 */

namespace App\Form;

use App\Entity\Composante;
use App\Entity\User;
use App\Form\Type\InlineCreateEntitySelectType;
use App\Form\Type\YesNoType;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ComposanteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $composante = $options['data'] ?? null;

        $builder
            ->add('libelle', TextType::class, [
                'label' => 'Libellé',
                'required' => true,
            ])
            ->add('sigle', TextType::class, [
                'label' => 'Sigle',
                'help' => 'Le sigle est la dénomination courte de la composante, s\'il existe.',
                'required' => false,
            ])
            ->add('inscriptionUniquement', YesNoType::class, [
                'label' => 'Composante d\'inscription et où se déroulent les cours',
            ])
            ->add('composanteParent', EntityType::class, [
                'class' => Composante::class,
                'query_builder' => static function (EntityRepository $er) use ($composante) {
                    $qb = $er->createQueryBuilder('t')
                        ->where('t.inscriptionUniquement = :inscription OR t.inscriptionUniquement IS NULL')
                        ->setParameter('inscription', false)
                        ->orderBy('t.libelle', 'ASC');

                    if ($composante instanceof Composante && $composante->getId() !== null) {
                        $qb->andWhere('t.id != :currentId')
                            ->setParameter('currentId', $composante->getId());
                    }

                    return $qb;
                },
                'choice_label' => 'libelle',
                'autocomplete' => true,
                'required' => false,
            ])
            ->add('codeComposante', TextType::class, [
                'label' => 'Code Composante',
                'attr' => ['maxlength' => 3],
                'required' => true,
            ])
            ->add('codeApogee', TextType::class, [
                'label' => 'Code CIP (Apogée)',
                'attr' => ['maxlength' => 2],
                'required' => true,
            ])
            ->add('directeur', InlineCreateEntitySelectType::class, [
                'class' => User::class,
                'label' => 'Directeur de composante',
                'new_placeholder' => 'Email du directeur de la composante',
                'required' => false,
            ])
            ->add('responsableDpe', InlineCreateEntitySelectType::class, [
                'class' => User::class,
                'label' => 'Responsable "DPE" de la composante',
                'help' => 'Personne responsable de la validation des DPE des formations de la composante',
                'new_placeholder' => 'Email du responsable DPE',
                'required' => false,
            ])
            ->add('adresse', AdresseType::class, [
                'label' => 'Adresse du site principal de la composante',
            ])
            ->add('telStandard', TelType::class, [
                'label' => 'Téléphone standard de la composante',
                'required' => false,
                'attr' => [
                    'maxlength' => 14,
                    'placeholder' => 'Ex : 03 26 91 30 00',
                ],
                'help' => 'Numéro français à 10 chiffres (espaces autorisés).',
            ])
            ->add('telComplementaire', TelType::class, [
                'label' => 'Autre téléphone de la composante',
                'required' => false,
                'attr' => [
                    'maxlength' => 14,
                    'placeholder' => 'Ex : 03 26 91 30 00',
                ],
                'help' => 'Numéro français à 10 chiffres (espaces autorisés).',
            ])
            ->add('mailContact', EmailType::class, [
                'label' => 'Adresse mail de contact de la composante',
                'required' => false,
                'attr' => [
                    'maxlength' => 255,
                    'placeholder' => 'contact@univ-reims.fr',
                ],
            ])
            ->add('urlSite', UrlType::class, [
                'label' => 'URL du site de la composante',
                'required' => false,
                'default_protocol' => 'https',
                'attr' => [
                    'maxlength' => 255,
                    'placeholder' => 'https://...',
                ],
            ])
        ;

        $frenchPhoneTransformer = new CallbackTransformer(
            static function (?string $raw): string {
                if ($raw === null || $raw === '') {
                    return '';
                }
                $clean = preg_replace('/\D/', '', $raw) ?? '';
                if (strlen($clean) === 10) {
                    return trim(chunk_split($clean, 2, ' '));
                }
                return $raw;
            },
            static function (?string $formatted): ?string {
                if ($formatted === null || $formatted === '') {
                    return null;
                }
                $clean = preg_replace('/\D/', '', $formatted) ?? '';
                return $clean !== '' ? substr($clean, 0, 10) : null;
            }
        );

        $builder->get('telStandard')->addModelTransformer($frenchPhoneTransformer);
        $builder->get('telComplementaire')->addModelTransformer($frenchPhoneTransformer);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Composante::class,
            'translation_domain' => 'form',
        ]);
    }
}
