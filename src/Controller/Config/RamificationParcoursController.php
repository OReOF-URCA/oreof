<?php

namespace App\Controller\Config;

use App\Entity\TypeRamificationParcours;
use App\Form\TypeRamificationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class RamificationParcoursController extends AbstractController
{
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/administration/type_ramification/show', name: 'app_config_type_ramification_show')]
    public function index(EntityManagerInterface $em): Response
    {
        $typeRamifArray = $em->getRepository(TypeRamificationParcours::class)->findAll();

        return $this->render('type_ramification/index.html.twig', [
            'typeRamifications' => $typeRamifArray 
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/administration/type_ramification/new', name: 'app_config_type_ramification_new')]
    public function newTypeRamification(
        Request $request,
        EntityManagerInterface $em
    ) : Response {

        $typeRamification = new TypeRamificationParcours();
        $form = $this->createForm(TypeRamificationType::class, $typeRamification);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $typeRamification = $form->getData();
            $em->persist($typeRamification);
            $em->flush();
            $this->addFlash('toast', [
                'type' => 'success',
                'text' => 'Le type de ramification a été ajouté avec succès.',
                'title' => 'Succès'
            ]);

            return $this->redirectToRoute('app_config_type_ramification_show');
        }

        return $this->render('type_ramification/new_or_edit.html.twig', [
            'formulaire' => $form,
            'form_title' => "Création d'un type de ramification"
        ]);
    }
    
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/administration/type_ramification/{typeRamification}/confirm_deletion', name: 'app_config_type_ramification_delete_confirm')]
    public function confirmDeletionTypeRamification(
        TypeRamificationParcours $typeRamification
    ) : Response {
        return $this->render('type_ramification/_confirm_deletion.html.twig', [
            'idTypeRamification' => $typeRamification->getId()
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/administration/type_ramification/{idType}/delete', name: 'app_config_type_ramification_delete')]
    public function deleteTypeRamification(
        TypeRamificationParcours $idType,
        EntityManagerInterface $em
    ) : Response {
        $em->remove($idType);
        $em->flush();
        $this->addFlash('toast', [
            'type' => 'success',
            'text' => 'Le type de ramification a été supprimé',
            'title' => "Succès"
        ]);

        return $this->redirectToRoute('app_config_type_ramification_show');
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/administration/type_ramification/{typeRamification}/edit', name: 'app_config_type_ramification_edit')]
    public function editTypeRamification(
        TypeRamificationParcours $typeRamification,
        EntityManagerInterface $em,
        Request $request
    ) : Response {
        $form = $this->createForm(TypeRamificationType::class, $typeRamification);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('toast', [
                'type' => 'success',
                'text' => 'Modification réussie',
                'title' => 'Succès'
            ]);
            return $this->redirectToRoute('app_config_type_ramification_show');
        }

        return $this->render('type_ramification/new_or_edit.html.twig', [
            'formulaire' => $form,
            'form_title' => "Modification d'un type de ramification"
        ]);
    }
}
