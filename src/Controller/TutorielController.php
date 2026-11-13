<?php

namespace App\Controller;

use App\Entity\Tutoriel;
use App\Navigation\Breadcrumb\Attribute\Breadcrumb as BreadcrumbAttribute;
use App\Navigation\Breadcrumb\Breadcrumb;
use App\Repository\TutorielRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Vidéos tutoriel : galerie et une page par vidéo, aux adresses stables pour être envoyées par mail.
 */
class TutorielController extends AbstractController
{
    #[Route('/tutoriels', name: 'app_tutoriel_index', methods: ['GET'])]
    #[BreadcrumbAttribute(label: 'menu.tutoriels')]
    public function index(TutorielRepository $tutorielRepository): Response
    {
        return $this->render('tutoriel/index.html.twig', [
            'tutoriels' => $tutorielRepository->findActiveOrdered(),
        ]);
    }

    #[Route('/tutoriels/{slug}', name: 'app_tutoriel_show', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['GET'])]
    #[BreadcrumbAttribute(label: 'menu.tutoriels', route: 'app_tutoriel_index')]
    public function show(
        #[MapEntity(mapping: ['slug' => 'slug'])] Tutoriel $tutoriel,
        TutorielRepository $tutorielRepository,
        Breadcrumb $breadcrumb,
    ): Response
    {
        // Une vidéo non publiée n'est visible que des administrateurs (aperçu avant envoi).
        if (!$tutoriel->isActive() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createNotFoundException('Vidéo introuvable.');
        }

        $breadcrumb->add((string) $tutoriel->getTitre(), 'app_tutoriel_show', ['slug' => $tutoriel->getSlug()]);

        $autres = array_values(array_filter(
            $tutorielRepository->findActiveOrdered(),
            static fn (Tutoriel $autre): bool => $autre->getId() !== $tutoriel->getId(),
        ));

        return $this->render('tutoriel/show.html.twig', [
            'tutoriel' => $tutoriel,
            'autres' => $autres,
        ]);
    }
}
