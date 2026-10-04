<?php

namespace App\Form;

use App\Entity\User;
use App\Enums\TypeRfEnum;
use App\Form\Type\EntityWithAddType;
use App\Form\Type\InlineCreateEntitySelectType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use UnitEnum;

class ChangeRfFormationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('typeRf', EnumType::class, [
                'class' => TypeRfEnum::class,
                'expanded' => true,
                'translation_domain' => 'form',
                'choice_label' => static function (TypeRfEnum $choice): string {
                    return $choice->getLibelle();
                },
            ])
            ->add('user', InlineCreateEntitySelectType::class, [
                'help' => '',
                'class' => User::class,
                'choice_label' => 'display',
                'query_builder' => function ($er) {
                    return $er->createQueryBuilder('u')
                        ->orderBy('u.nom', 'ASC')
                        ->addOrderBy('u.prenom', 'ASC');
                },
                'new_placeholder' => 'Email du responsable de la mention',
                'required' => true,
                'ldap_check' => true,
                'placeholder' => 'Choisir dans la liste ou choisir "+" pour ajouter un utilisateur',
                'label' => 'Nouveau (co-)responsable de formation',
                // évite doublons (optionnel)
                'find_existing' => function (string $label, $scope, EntityManagerInterface $em) {
                    return $em->getRepository(User::class)->createQueryBuilder('t')
                        ->andWhere('LOWER(t.email) = LOWER(:l)')
                        ->setParameter('l', $label)
                        ->getQuery()
                        ->getOneOrNullResult();
                },

                // création (obligatoire)
                'create' => function (string $label, EntityManagerInterface $em) {
                    $e = new User();
                    $e->setEmail($label);
                    return $e; // persist/flush gérés par le type (ou tu peux le faire ici)
                },

            ])
            ->add('datePriseFonction', DateType::class, [
                'label' => 'Date de prise de fonction',
                'widget' => 'single_text',
                'required' => true,
                'help' => 'Attention : cette date pourra impacter l\'année en cours et/ou l\'année précédente selon les dates de la campagne.',
            ])
            ->add('commentaire', TextareaType::class, [
                'label' => 'Commentaire sur le changement de (co-)responsable de formation',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
