<?php

// src/Form/Type/InlineCreateEntitySelectType.php
namespace App\Form\Type;

use App\Classes\AddUser;
use App\Entity\User;
use App\Validator\Constraints\LdapEmailExists;
use App\Validator\Constraints\LdapEmailExistsValidator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\EmailValidator;

final class InlineCreateEntitySelectType extends AbstractType
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ?AddUser $addUser = null,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $entityOptions = [
            'class' => $options['class'],
            'choice_label' => $options['choice_label'],
            'placeholder' => $options['placeholder'],
            'required' => $options['required'],
            'query_builder' => $options['query_builder'], // callable|null (Doctrine attend callable(repo): QB)
        ];

        if ($options['autocomplete']) {
            $entityOptions['autocomplete'] = $options['autocomplete'];
        }

        $builder
            ->add('entity', EntityType::class, $entityOptions);

        if ($options['ldap_check']) {
            $builder->add('new', TextType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => $options['new_placeholder'],
                    'data-inline-create-target' => 'ldapCheckInput',
                ],
                'constraints' => [new Email(), new LdapEmailExists()]
            ]);
        } else {
            $builder->add('new', TextType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => $options['new_placeholder'],
                    'data-inline-create-target' => 'newInput',
                ],
            ]);
        }
        $builder->addModelTransformer(new CallbackTransformer(
            function ($modelValue) {
                return ['entity' => $modelValue];
            },
            function ($submittedValue) {
                if (!is_array($submittedValue)) {
                    return null;
                }
                return $submittedValue['entity'] ?? null;
            }
        ));

        // Création/selection au submit

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($options) {
            $data = $event->getData();        // tableau brut soumis : ['entity' => '...', 'new' => '...']
            $form = $event->getForm();

            $newLabel = trim((string)($data['new'] ?? ''));
            if ($newLabel === '') {
                return;
            }

            $scope = $options['scope'] ?? null;

            // 1) éviter doublons (optionnel)
            if (is_callable($options['find_existing'])) {
                $existing = ($options['find_existing'])($newLabel, $scope, $this->em);
                if ($existing) {
                    // on force la valeur soumise "entity" (id)
                    $data['entity'] = (string)$existing->getId();
                    $data['new'] = '';
                    $event->setData($data);
                    return;
                }
            }

            // 2) créer
            if (!is_callable($options['create'])) {
                throw new \LogicException('Option "create" must be a callable that returns the created entity.');
            }

            $entity = ($options['create'])($newLabel, $scope, $this->em);
            $this->em->persist($entity);
            $this->em->flush();

            // 3) injecter l'id dans la soumission => Symfony sélectionnera cet élément
            $data['entity'] = (string)$entity->getId();
            $data['new'] = ''; // important
            $event->setData($data);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['class']);
        $resolver->setDefaults([
            'choice_label' => 'id',
            'placeholder' => 'Choisir…',
            'required' => false,
            'ldap_check' => false,
            'create' => null,
            // texte de l’input
            'new_placeholder' => 'Nom du nouveau…',
            'scope' => null,
            'query_builder' => null,
            'find_existing' => null,
            'label' => false,
            'autocomplete' => true,
        ]);

        $resolver->setAllowedTypes('class', 'string');
        $resolver->setAllowedTypes('ldap_check', ['boolean']);
        $resolver->setAllowedTypes('choice_label', ['string', 'callable']);
        $resolver->setAllowedTypes('query_builder', ['null', 'callable']);
        $resolver->setAllowedTypes('create', ['null', 'callable']);
        $resolver->setAllowedTypes('find_existing', ['null', 'callable']);
        $resolver->setAllowedTypes('autocomplete', ['bool', 'string']);

        $resolver->setNormalizer('choice_label', static function (Options $options, mixed $value): string|callable {
            if ($value === 'id' && is_a($options['class'], User::class, true)) {
                return 'display';
            }
            return $value;
        });

        $resolver->setNormalizer('placeholder', static function (Options $options, mixed $value): string {
            if ($value === 'Choisir…' && is_a($options['class'], User::class, true)) {
                return 'Choisir dans la liste ou choisir "+ Créer nouveau" pour ajouter un utilisateur';
            }
            return $value;
        });

        $resolver->setNormalizer('ldap_check', static function (Options $options, mixed $value): bool {
            if ($value === false && is_a($options['class'], User::class, true)) {
                return true;
            }
            return $value;
        });

        $resolver->setNormalizer('new_placeholder', static function (Options $options, mixed $value): string {
            if ($value === 'Nom du nouveau…' && is_a($options['class'], User::class, true)) {
                return 'Email de l\'utilisateur…';
            }
            return $value;
        });

        $resolver->setNormalizer('query_builder', static function (Options $options, mixed $value): ?callable {
            if ($value === null && is_a($options['class'], User::class, true)) {
                return static fn (EntityRepository $er) => $er->createQueryBuilder('u')
                    ->orderBy('u.nom', 'ASC')
                    ->addOrderBy('u.prenom', 'ASC');
            }
            return $value;
        });

        $resolver->setNormalizer('find_existing', static function (Options $options, mixed $value): ?callable {
            if ($value === null && is_a($options['class'], User::class, true)) {
                return static fn (string $label, $scope, EntityManagerInterface $em) => $em->getRepository(User::class)->createQueryBuilder('t')
                    ->andWhere('LOWER(t.email) = LOWER(:l)')
                    ->setParameter('l', $label)
                    ->getQuery()
                    ->getOneOrNullResult();
            }
            return $value;
        });

        $resolver->setNormalizer('create', function (Options $options, mixed $value): ?callable {
            if ($value === null && is_a($options['class'], User::class, true)) {
                return function (string $label, $scope = null, ?EntityManagerInterface $em = null): User {
                    if ($this->addUser !== null) {
                        $user = $this->addUser->addUser($label);
                        if ($user !== null) {
                            return $user;
                        }
                    }
                    $e = new User();
                    $e->setEmail($label);
                    return $e;
                };
            }
            return $value;
        });
    }

    public function getBlockPrefix(): string
    {
        return 'inline_create_entity_select';
    }
}
