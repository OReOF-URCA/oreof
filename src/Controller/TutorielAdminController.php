<?php

namespace App\Controller;

use App\Entity\Tutoriel;
use App\Form\TutorielType;
use App\Repository\TutorielRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/administration/tutoriels')]
#[IsGranted('ROLE_ADMIN')]
class TutorielAdminController extends AbstractController
{
    #[Route('/', name: 'app_tutoriel_admin_index', methods: ['GET'])]
    public function index(TutorielRepository $tutorielRepository): Response
    {
        return $this->render('tutoriel_admin/index.html.twig', [
            'tutoriels' => $tutorielRepository->findAllOrdered(),
        ]);
    }

    #[Route('/new', name: 'app_tutoriel_admin_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, TutorielRepository $tutorielRepository): Response
    {
        $tutoriel = new Tutoriel();
        $tutoriel->setOrdre($tutorielRepository->getNextOrdre());

        $form = $this->createForm(TutorielType::class, $tutoriel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($tutoriel);
            $em->flush();
            $this->addFlash('success', 'Vidéo ajoutée.');

            return $this->redirectToRoute('app_tutoriel_admin_index');
        }

        return $this->render('tutoriel_admin/form.html.twig', [
            'form' => $form,
            'tutoriel' => $tutoriel,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_tutoriel_admin_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Tutoriel $tutoriel, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(TutorielType::class, $tutoriel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $tutoriel->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();
            $this->addFlash('success', 'Vidéo mise à jour.');

            return $this->redirectToRoute('app_tutoriel_admin_index');
        }

        return $this->render('tutoriel_admin/form.html.twig', [
            'form' => $form,
            'tutoriel' => $tutoriel,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_tutoriel_admin_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Tutoriel $tutoriel, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_tutoriel'.$tutoriel->getId(), $request->request->getString('_token'))) {
            $em->remove($tutoriel);
            $em->flush();
            $this->addFlash('success', 'Vidéo supprimée.');
        }

        return $this->redirectToRoute('app_tutoriel_admin_index');
    }
}
