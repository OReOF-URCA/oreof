<?php

namespace App\Controller\Config;

use App\Entity\CampagneCollecte;
use App\Entity\Parcours;
use App\Entity\ParcoursRamification;
use App\Entity\TypeRamificationParcours;
use App\Form\TypeRamificationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
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

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/administration/parcours/ramification/manage', name: 'app_config_type_ramification_parcours_manage')]
    public function addParcoursRamificationForm(EntityManagerInterface $em) : Response {
        $typesRamif = $em->getRepository(TypeRamificationParcours::class)->findAll();

        if(count($typesRamif) === 0) {
            $this->addFlash('toast', [
                'type' => 'info',
                'text' => 'Vous devez ajouter des types de ramifications avant de relier les parcours'
            ]);
            return $this->redirectToRoute('app_config_type_ramification_show');
        }

        $typesRamif = array_map(
            fn($t) => ['id' => $t->getId(), 'libelle' => $t->getLibelle()],
            $typesRamif
        );
        return $this->render('type_ramification/manage_parcours.html.twig', [
            'types_ramification' => $typesRamif
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/administration/parcours/ramification/manage/add_data', name: 'app_config_type_ramification_add_data')]
    public function linkParcoursWithRamification(
        Request $r,
        EntityManagerInterface $em
    ) {
        $data = $r->query->all();

        $targetParcours = $em->getRepository(Parcours::class)->findOneBy(['id' => $data['targetId']]);
        $typeRamif = $em->getRepository(TypeRamificationParcours::class)->findOneBy(['id' => $data['typeRamifId']]);
        $sourceParcoursArray = $em->getRepository(Parcours::class)->findBy(['id' => $data['sourceId']]);

        if( ($targetParcours instanceof Parcours === false) 
            || ($typeRamif instanceof TypeRamificationParcours === false)
            || count($sourceParcoursArray) !== count($data['sourceId'])
        ) {
            $this->addFlash('toast', [
                'type' => 'danger',
                'text' => "Une donnée fournie est incorrecte. Veuillez réessayer."
            ]);
            return $this->redirectToRoute('app_config_type_ramification_show');
        }

        foreach($sourceParcoursArray as $sourceP) {
            $ramification = new ParcoursRamification();
            $ramification->setParcoursOrigine($sourceP);
            $ramification->setParcoursCible($targetParcours);
            $ramification->setTypeRamification($typeRamif);
            $em->persist($ramification);
        }

        $em->flush();
        $this->addFlash('toast', [
            'type' => 'success',
            'text' => 'Enregistrement réussi !'
        ]);

        return $this->redirectToRoute('app_config_type_ramification_show');
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/administration/parcours/search_by_name', name: 'app_config_type_ramification_search_parcours_by_name')]
    public function searchParcoursByName(
        EntityManagerInterface $em,
        Request $request
    ) : Response {
        $keyword = $request->query->get('keyword', '');
        if(strlen($keyword) < 4){
            return new JsonResponse(['error' => 'Keyword length too short. Minimum : 4 characters']);
        }

        $campagne = $em->getRepository(CampagneCollecte::class)->findOneBy(['defaut' => 1]);
        return new JsonResponse(
            $em->getRepository(Parcours::class)
                ->findByNomComplet($keyword, $campagne->getId())
        );
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/administration/parcours/ramification/parcours_list', name: 'app_config_type_ramification_parcours_list')]
    public function showParcoursRamificationList(
        EntityManagerInterface $em
    ) {
        $parcoursRamifications = $em->getRepository(ParcoursRamification::class)->findAllRamificationsGroups();
        $accumulator = [];
        $cardResults = [];
        foreach($parcoursRamifications as $pr) {
            $hasBeenTreated = false;
            if(isset($accumulator[$pr['parcours_cible_id']]) && $accumulator[$pr['parcours_cible_id']] === $pr['code_type_ramif']) {
                $hasBeenTreated = true;
            }

            if(!$hasBeenTreated) {
                $cardResults[] = array_values(array_filter($parcoursRamifications, 
                fn($a) => $a['code_type_ramif'] === $pr['code_type_ramif'] 
                    && $a['parcours_cible_id'] === $pr['parcours_cible_id']
                ));
            }

            $accumulator[$pr['parcours_cible_id']] = $pr['code_type_ramif'];
        }

        return $this->render('type_ramification/show_list.html.twig', [
            'parcoursRamifications' => $cardResults
        ]);
    }
}
