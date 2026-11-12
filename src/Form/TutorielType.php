<?php

namespace App\Form;

use App\Entity\Tutoriel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\String\Slugger\AsciiSlugger;

class TutorielType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre',
                'attr' => ['placeholder' => 'Ex. : Changement de responsable de formation'],
            ])
            ->add('youtubeUrl', UrlType::class, [
                'label' => 'Lien YouTube',
                'default_protocol' => 'https',
                'help' => 'Lien de la vidéo, même non répertoriée (ex. https://www.youtube.com/watch?v=...).',
                'attr' => ['placeholder' => 'https://www.youtube.com/watch?v=...'],
            ])
            ->add('slug', TextType::class, [
                'label' => 'Adresse de la page',
                'required' => false,
                'help' => 'Fin du lien /tutoriels/... à partager. Laissez vide pour la déduire du titre. Évitez de la modifier une fois le lien envoyé : les anciens liens ne fonctionneraient plus.',
                'attr' => ['placeholder' => 'changement-responsable'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'help' => 'Quelques lignes affichées sous la vidéo.',
                'attr' => ['rows' => 4],
            ])
            ->add('duree', TextType::class, [
                'label' => 'Durée',
                'required' => false,
                'help' => 'Facultatif, ex. « 4 min ».',
            ])
            ->add('ordre', IntegerType::class, [
                'label' => 'Ordre d\'affichage',
                'help' => 'Les vidéos sont affichées de la plus petite à la plus grande valeur.',
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Publier cette vidéo',
                'required' => false,
            ])
            // Avant validation : slug déduit du titre s'il est vide, et normalisé sinon.
            ->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event): void {
                $tutoriel = $event->getData();
                if (!$tutoriel instanceof Tutoriel) {
                    return;
                }

                $source = trim((string) $tutoriel->getSlug()) !== '' ? (string) $tutoriel->getSlug() : (string) $tutoriel->getTitre();
                $slug = (new AsciiSlugger('fr'))->slug($source)->lower()->truncate(120)->trim('-')->toString();
                $tutoriel->setSlug($slug !== '' ? $slug : null);
            });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Tutoriel::class]);
    }
}
