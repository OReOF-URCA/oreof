<?php
namespace App\Controller;

use App\Entity\Referentiel;
use App\Entity\ReferentielValeur;
use App\Form\Referentiel\ReferentielType;
use App\Form\Referentiel\ReferentielValeurType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/administration/referentiels', name: 'admin_referentiel_')]
#[IsGranted('ROLE_ADMIN')]
final class ReferentielAdminController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        return $this->render('referentiel/index.html.twig', [
            'referentiels' => $em->getRepository(Referentiel::class)->findBy([], ['libelle' => 'ASC']),
        ]);
    }

    #[Route('/{id}/valeurs', name: 'valeurs', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function valeurs(Referentiel $referentiel, EntityManagerInterface $em): Response
    {
        return $this->render('referentiel/valeurs.html.twig', [
            'referentiel' => $referentiel,
            'valeurs' => $em->getRepository(ReferentielValeur::class)->findBy(['referentiel' => $referentiel], ['ordre' => 'ASC']),
        ]);
    }

    #[Route('/{id}/modifier', name: 'edit', requirements: ['id' => '\\d+'])]
    public function edit(Referentiel $referentiel, Request $request, EntityManagerInterface $em): Response
    {
        if (!$referentiel->isAdministrable()) { throw $this->createAccessDeniedException(); }
        $form = $this->createForm(ReferentielType::class, $referentiel);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            return $this->redirectToRoute('admin_referentiel_index');
        }
        return $this->render('referentiel/form.html.twig', ['form' => $form, 'titre' => 'Modifier le référentiel']);
    }

    #[Route('/{id}/ajouter', name: 'add_value', requirements: ['id' => '\\d+'])]
    public function addValue(Referentiel $referentiel, Request $request, EntityManagerInterface $em): Response
    {
        if (!$referentiel->isExtensible()) { throw $this->createAccessDeniedException(); }
        $valeur = (new ReferentielValeur())->setReferentiel($referentiel);
        $form = $this->createForm(ReferentielValeurType::class, $valeur, ['allow_code' => true]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($valeur);
            $em->flush();
            return $this->redirectToRoute('admin_referentiel_valeurs', ['id' => $referentiel->getId()]);
        }
        return $this->render('referentiel/form.html.twig', ['form' => $form, 'titre' => 'Ajouter une valeur']);
    }

    #[Route('/valeur/{id}/modifier', name: 'edit_value', requirements: ['id' => '\\d+'])]
    public function editValue(ReferentielValeur $valeur, Request $request, EntityManagerInterface $em): Response
    {
        $referentiel = $valeur->getReferentiel();
        if (!$referentiel?->isAdministrable()) { throw $this->createAccessDeniedException(); }
        $form = $this->createForm(ReferentielValeurType::class, $valeur);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            return $this->redirectToRoute('admin_referentiel_valeurs', ['id' => $referentiel->getId()]);
        }
        return $this->render('referentiel/form.html.twig', ['form' => $form, 'titre' => 'Modifier une valeur']);
    }
}
