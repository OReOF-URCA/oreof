<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/AnneeUniversitaireController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Config;

use App\Entity\CampagneCollecte;
use App\Enums\CampagnePublicationTagEnum;
use App\Enums\ConfigurationPublicationEnum;
use App\Form\CampagneCollecteType;
use App\Form\ConfigurePublicationType;
use App\Repository\CampagneCollecteRepository;
use App\Repository\DpeParcoursRepository;
use App\Utils\JsonRequest;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/administration/campagne-collecte')]
class CampagneCollecteController extends AbstractController
{
    #[Route('/', name: 'app_campagne_collecte_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('config/campagne_collecte/index.html.twig');
    }

    #[Route('/ouvrir-dpe/{id}', name: 'app_campagne_collecte_open_dpe', methods: ['GET'])]
    public function openDpe(
        EntityManagerInterface $entityManager,
        CampagneCollecteRepository $campagneCollecteRepository,
        DpeParcoursRepository $dpeParcoursRepository,
        CampagneCollecte $campagneCollecte): Response
    {
        $campagneCollectePrecedente = $campagneCollecteRepository->findOneBy(['defaut' => true]);
        if ($campagneCollectePrecedente === null) {
            throw $this->createNotFoundException('Aucune campagne précédente trouvée');
        }

//        $dpeParcoursRepository->duplicateParcours($campagneCollectePrecedente, $campagneCollecte);
        $campagneCollectePrecedente->setDefaut(false);
        $campagneCollecte->setMailDpeEnvoye(true);
        $campagneCollecte->setDefaut(true);

        $entityManager->flush();

        //todo: prévenir les responsables DPE

        return $this->json(true);
    }

    #[Route('/liste', name: 'app_campagne_collecte_liste', methods: ['GET'])]
    public function liste(CampagneCollecteRepository $campagneCollecteRepository): Response
    {
        return $this->render('config/campagne_collecte/_liste.html.twig', [
            'campagne_collectes' => $campagneCollecteRepository->findAll(),
            'libelleConfigurationPublication' => [
                ConfigurationPublicationEnum::MAQUETTE->value => 'Maquette des enseignements',
                ConfigurationPublicationEnum::MCCC->value => 'MCCC (PDF)'
            ]
        ]);
    }

    #[Route('/new', name: 'app_campagne_collecte_new', methods: ['GET', 'POST'])]
    public function new(Request $request, CampagneCollecteRepository $campagneCollecteRepository): Response
    {
        $campagne_collecte = new CampagneCollecte();
        $form = $this->createForm(
            CampagneCollecteType::class,
            $campagne_collecte,
            ['action' => $this->generateUrl('app_campagne_collecte_new')]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $campagneCollecteRepository->save($campagne_collecte, true);

            return $this->json(true);
        }

        return $this->render('config/campagne_collecte/new.html.twig', [
            'campagne_collecte' => $campagne_collecte,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_campagne_collecte_show', methods: ['GET'])]
    public function show(CampagneCollecte $campagne_collecte): Response
    {
        return $this->render('config/campagne_collecte/show.html.twig', [
            'campagne_collecte' => $campagne_collecte,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_campagne_collecte_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request          $request,
        CampagneCollecte $campagne_collecte,
        CampagneCollecteRepository $campagneCollecteRepository
    ): Response {
        $form = $this->createForm(
            CampagneCollecteType::class,
            $campagne_collecte,
            [
                'action' => $this->generateUrl('app_campagne_collecte_edit', [
                    'id' => $campagne_collecte->getId()
                ])
            ]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $campagneCollecteRepository->save($campagne_collecte, true);

            return $this->json(true);
        }

        return $this->render('config/campagne_collecte/new.html.twig', [
            'campagne_collecte' => $campagne_collecte,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/duplicate', name: 'app_campagne_collecte_duplicate', methods: ['GET'])]
    public function duplicate(
        CampagneCollecteRepository $campagneCollecteRepository,
        CampagneCollecte           $campagne_collecte
    ): Response {
        $campagne_collecteNew = clone $campagne_collecte;
        $campagne_collecteNew->setLibelle($campagne_collecte->getLibelle() . ' - Copie');
        $campagneCollecteRepository->save($campagne_collecteNew, true);
        return $this->json(true);
    }

    /**
     * @throws JsonException
     */
    #[Route('/{id}', name: 'app_campagne_collecte_delete', methods: ['DELETE'])]
    public function delete(
        Request          $request,
        CampagneCollecte $campagne_collecte,
        CampagneCollecteRepository $campagneCollecteRepository
    ): Response {
        if ($this->isCsrfTokenValid(
            'delete' . $campagne_collecte->getId(),
            JsonRequest::getValueFromRequest($request, 'csrf')
        )) {
            $campagneCollecteRepository->remove($campagne_collecte, true);

            return $this->json(true);
        }

        return $this->json(false);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}/configure/publication', name: 'app_campagne_collecte_configure_publication', methods: ['GET', 'POST'])]
    public function configurePublication(
        CampagneCollecte $campagneCollecte,
        Request $request,
        CampagneCollecteRepository $campagneRepository
    ) : Response {
        $form = $this->createForm(ConfigurePublicationType::class, $campagneCollecte, 
            [
               'action' => $this->generateUrl('app_campagne_collecte_configure_publication', ['id' => $campagneCollecte->getId()]),
               'hasPublishedMccc' => $campagneCollecte->getPublicationOptions()[ConfigurationPublicationEnum::MCCC->value] ?? 'none',
               'hasPublishedMaquette' => $campagneCollecte->getPublicationOptions()[ConfigurationPublicationEnum::MAQUETTE->value] ?? 'none',
               'publicationTag' => $campagneCollecte->getPublicationTag() ?? 'none'
            ]
        );

        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()){
            // Configuration de la publication
            $configToSave = [];
            $jsonConfigValues = [
                ConfigurationPublicationEnum::MAQUETTE->value, 
                ConfigurationPublicationEnum::MCCC->value
            ];
            foreach($jsonConfigValues as $configValue){
                if($form->has($configValue)){
                    $configToSave[$configValue] = $form->get($configValue)->getData();
                }
            }
            // Le tag ne peut être présent qu'une seule fois
            $publicationTag = $form->get('campagneTag')->getData();
            if(in_array($publicationTag, [CampagnePublicationTagEnum::ANNEE_COURANTE->value, CampagnePublicationTagEnum::ANNEE_SUIVANTE->value])){
                $statusAlreadyTaken = $campagneRepository->findOneBy(['publicationTag' => $publicationTag]);
                if($statusAlreadyTaken !== null && $statusAlreadyTaken->getId() !== $campagneCollecte->getId()){
                    return $this->json(['message' => 'Ce statut est déjà utilisé'], 500);
                }
            }

            $campagneCollecte->setPublicationTag($publicationTag);
            $campagneCollecte->setPublicationOptions($configToSave);
            $campagneRepository->save($campagneCollecte, true);

            return $this->json(true);
        }

        return $this->render('config/campagne_collecte/_configure_publication.html.twig', [
            'campagneCollecte' => $campagneCollecte,
            'form' => $form
        ]);
    }
}
