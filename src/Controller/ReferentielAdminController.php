<?php

declare(strict_types=1);

namespace App\Controller;

use App\DataTable\ReferentielDataTable;
use App\DataTable\ReferentielValeurDataTable;
use App\DTO\TranslatableKey;
use App\Entity\Referentiel;
use App\Entity\ReferentielValeur;
use App\Form\Referentiel\ReferentielType;
use App\Form\Referentiel\ReferentielValeurType;
use App\Utils\TurboStreamResponseFactory;
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
    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    public function index(ReferentielDataTable $table): Response
    {
        return $this->render('referentiel/index.html.twig', ['table' => $table]);
    }

    #[Route('/{id}/valeurs', name: 'valeurs', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function valeurs(Referentiel $referentiel, ReferentielValeurDataTable $table): Response
    {
        return $this->render('referentiel/valeurs.html.twig', ['referentiel' => $referentiel, 'table' => $table]);
    }

    #[Route('/{id}/modifier', name: 'edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function edit(Referentiel $referentiel, Request $request, EntityManagerInterface $em, TurboStreamResponseFactory $turboStream): Response
    {
        if (!$referentiel->isAdministrable()) {
            throw $this->createAccessDeniedException();
        }
        $form = $this->createForm(ReferentielType::class, $referentiel, [
            'action' => $this->generateUrl('admin_referentiel_edit', ['id' => $referentiel->getId()]),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            return $turboStream->streamToastSuccess('Référentiel modifié avec succès', true);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('Modifier le référentiel', [], 'modal'),
            'Référentiel : ' . $referentiel->getLibelle(),
            '_ui/_modal_new_generic.html.twig',
            ['form' => $form->createView()],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}/ajouter', name: 'add_value', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function addValue(Referentiel $referentiel, Request $request, EntityManagerInterface $em, TurboStreamResponseFactory $turboStream): Response
    {
        if (!$referentiel->isExtensible()) {
            throw $this->createAccessDeniedException();
        }
        $valeur = (new ReferentielValeur())->setReferentiel($referentiel);
        $form = $this->createForm(ReferentielValeurType::class, $valeur, [
            'allow_code' => true,
            'action' => $this->generateUrl('admin_referentiel_add_value', ['id' => $referentiel->getId()]),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($valeur);
            $em->flush();
            return $turboStream->streamToastSuccess('Valeur ajoutée avec succès', true);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('Ajouter une valeur', [], 'modal'),
            'Référentiel : ' . $referentiel->getLibelle(),
            '_ui/_modal_new_generic.html.twig',
            ['form' => $form->createView()],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
    }

    #[Route('/valeur/{id}/modifier', name: 'edit_value', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function editValue(ReferentielValeur $valeur, Request $request, EntityManagerInterface $em, TurboStreamResponseFactory $turboStream): Response
    {
        $referentiel = $valeur->getReferentiel();
        if (!$referentiel?->isAdministrable()) {
            throw $this->createAccessDeniedException();
        }
        $form = $this->createForm(ReferentielValeurType::class, $valeur, [
            'action' => $this->generateUrl('admin_referentiel_edit_value', ['id' => $valeur->getId()]),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            return $turboStream->streamToastSuccess('Valeur modifiée avec succès', true);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('Modifier une valeur', [], 'modal'),
            'Référentiel : ' . $referentiel->getLibelle(),
            '_ui/_modal_new_generic.html.twig',
            ['form' => $form->createView()],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
    }
}
