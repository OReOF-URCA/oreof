<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/FicheMatiere/FicheMatiereMutualisationController.php
 * @author davidannebicque
 * @project oreof
 */

namespace App\Controller\FicheMatiere;

use App\Classes\Mailer;
use App\Controller\BaseController;
use App\Entity\FicheMatiere;
use App\Entity\FicheMatiereMutualisable;
use App\Entity\Formation;
use App\Entity\Notification;
use App\Entity\Parcours;
use App\Repository\ComposanteRepository;
use App\Repository\ElementConstitutifRepository;
use App\Repository\FicheMatiereMutualisableRepository;
use App\Repository\FormationRepository;
use App\Repository\ParcoursRepository;
use App\Utils\JsonRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/fiche-matiere/v2/{id}/mutualisation', name: 'fiche_matiere_v2_mutualisation_')]
#[IsGranted('EDIT_FICHE_MATIERE', 'ficheMatiere')]
class FicheMatiereMutualisationController extends BaseController
{
    #[Route('', name: 'liste', methods: ['GET'])]
    public function liste(FicheMatiere $ficheMatiere): Response
    {
        return $this->render('fiche_matiere_v2/mutualisation/_liste.html.twig', [
            'ficheMatiere' => $ficheMatiere,
        ]);
    }

    #[Route('/add', name: 'add', methods: ['GET'])]
    public function addModal(
        ComposanteRepository $composanteRepository,
        FicheMatiere         $ficheMatiere,
    ): Response {
        $composantes = $composanteRepository->findAll();

        return $this->render('fiche_matiere_v2/mutualisation/_modal_add.html.twig', [
            'ficheMatiere' => $ficheMatiere,
            'composantes' => $composantes,
        ]);
    }

    #[Route('/ajax', name: 'ajax', methods: ['POST', 'DELETE'])]
    public function ajax(
        EntityManagerInterface             $entityManager,
        ComposanteRepository               $composanteRepository,
        Request                            $request,
        FormationRepository                $formationRepository,
        ParcoursRepository                 $parcoursRepository,
        FicheMatiereMutualisableRepository $ficheMatiereParcoursRepository,
        FicheMatiere                       $ficheMatiere,
    ): Response {
        $data = JsonRequest::getFromRequest($request);
        $t = [];
        switch ($data['field'] ?? null) {
            case 'formation':
                $composante = $composanteRepository->find($data['value']);
                if ($composante === null) {
                    return $this->json([]);
                }
                $formations = $formationRepository->findByComposante($composante, $this->getCampagneCollecte());
                foreach ($formations as $formation) {
                    $t[] = [
                        'id' => $formation->getId(),
                        'libelle' => $formation->getDisplayLong(),
                    ];
                }
                break;

            case 'parcours':
                $formation = $formationRepository->find($data['value']);
                if ($formation === null) {
                    return $this->json([]);
                }
                $parcours = $parcoursRepository->findByFormation($formation);
                foreach ($parcours as $parcour) {
                    $t[] = [
                        'id' => $parcour->getId(),
                        'libelle' => $parcour->getDisplay(),
                    ];
                }
                break;

            case 'save':
                if (($data['formation'] ?? null) === 'all') {
                    $composante = $composanteRepository->find($data['composante'] ?? null);
                    if ($composante !== null && !$ficheMatiere->getComposante()->contains($composante)) {
                        $ficheMatiere->addComposante($composante);
                        $composante->addFicheMatiere($ficheMatiere);
                        $entityManager->flush();
                    }

                    return $this->json(true);
                }

                if (!isset($data['parcours']) || $data['parcours'] === '') {
                    $formation = $formationRepository->find($data['formation'] ?? null);
                    if ($formation !== null && $formation->isHasParcours() === false && count($formation->getParcours()) === 1) {
                        $parcours = $formation->getParcours()[0];
                        $this->updateFicheMatiereMutualisable($ficheMatiereParcoursRepository, $ficheMatiere, $parcours, $entityManager);
                    }
                } else {
                    if ($data['parcours'] !== 'all') {
                        $parcours = $parcoursRepository->find($data['parcours']);
                        $this->updateFicheMatiereMutualisable($ficheMatiereParcoursRepository, $ficheMatiere, $parcours, $entityManager);
                    } else {
                        $formation = $formationRepository->find($data['formation'] ?? null);
                        if ($formation !== null) {
                            foreach ($formation->getParcours() as $parcours) {
                                $this->updateFicheMatiereMutualisable($ficheMatiereParcoursRepository, $ficheMatiere, $parcours, $entityManager);
                            }
                        }
                    }
                }

                return $this->json(true);

            case 'delete':
                $fiche = $ficheMatiereParcoursRepository->find($data['fiche'] ?? null);
                if ($fiche !== null) {
                    $entityManager->remove($fiche);
                    $entityManager->flush();
                }

                return $this->json(true);
        }

        return $this->json($t);
    }

    #[Route('/impacts', name: 'impacts', methods: ['GET'])]
    public function impacts(FicheMatiere $ficheMatiere): Response
    {
        return $this->render('fiche_matiere_v2/mutualisation/_modal_impacts.html.twig', [
            'ficheMatiere' => $ficheMatiere,
        ]);
    }

    #[Route('/remove-all', name: 'remove_all', methods: ['DELETE'])]
    public function removeAll(
        ElementConstitutifRepository       $ecRepository,
        EntityManagerInterface             $entityManager,
        FicheMatiereMutualisableRepository $ficheMatiereParcoursRepository,
        Mailer                             $mailer,
        FicheMatiere                       $ficheMatiere,
    ): Response {
        $ecUtilsateurs = $ecRepository->findBy(['ficheMatiere' => $ficheMatiere]);
        $liaisons = $ficheMatiereParcoursRepository->findBy(['ficheMatiere' => $ficheMatiere]);

        $notifications = [];
        foreach ($ecUtilsateurs as $ec) {
            $parcours = $ec->getParcours();
            if ($parcours !== null) {
                $resp = $parcours->getRespParcours();
                if ($resp !== null) {
                    $userKey = $resp->getId() ?? spl_object_id($resp);
                    if (!array_key_exists($userKey, $notifications)) {
                        $notifications[$userKey] = [
                            'user' => $resp,
                            'parcours' => [],
                        ];
                    }

                    $notifications[$userKey]['parcours'][$parcours->getId() ?? spl_object_id($parcours)] = $parcours;
                }
                $ec->setFicheMatiere(null);
            }
        }

        foreach ($liaisons as $liaison) {
            $parcours = $liaison->getParcours();
            if ($parcours !== null) {
                $resp = $parcours->getRespParcours();
                if ($resp !== null) {
                    $userKey = $resp->getId() ?? spl_object_id($resp);
                    if (!array_key_exists($userKey, $notifications)) {
                        $notifications[$userKey] = [
                            'user' => $resp,
                            'parcours' => [],
                        ];
                    }

                    $notifications[$userKey]['parcours'][$parcours->getId() ?? spl_object_id($parcours)] = $parcours;
                }
            }
            $entityManager->remove($liaison);
        }

        $ficheMatiere->setEnseignementMutualise(false);
        $entityManager->flush();

        $mailErrors = 0;
        foreach ($notifications as $notificationData) {
            $user = $notificationData['user'];
            $parcoursImpactes = array_values($notificationData['parcours']);
            $listeParcours = array_map(static fn($parcours) => $parcours->getDisplay(), $parcoursImpactes);

            $notification = new Notification();
            $notification->setDestinataire($user);
            $notification->setTitle('Mutualisation supprimée');
            $notification->setBody(sprintf(
                'La fiche matière "%s" n\'est plus mutualisée avec %d parcours : %s. Veuillez vérifier vos éléments constitutifs.',
                $ficheMatiere->getLibelle() ?? 'sans libellé',
                count($listeParcours),
                implode(', ', $listeParcours)
            ));
            $notification->setPayload([
                'ficheMatiere' => $ficheMatiere->getId(),
                'parcours' => array_map(static fn($parcours) => $parcours->getId(), $parcoursImpactes),
            ]);
            $entityManager->persist($notification);

            if ($user->getEmail() !== null) {
                try {
                    $mailer->initEmail();
                    $mailer->setTemplate('mails/mutualisation/fiche_demutualisee.html.twig', [
                        'user' => $user,
                        'ficheMatiere' => $ficheMatiere,
                        'parcoursImpactes' => $parcoursImpactes,
                    ]);
                    $mailer->sendMessage(
                        [$user->getEmail()],
                        '[ORéOF] Suppression de mutualisation d\'une fiche matière'
                    );
                } catch (\Throwable) {
                    ++$mailErrors;
                }
            }
        }

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'count' => count($liaisons),
            'notified' => count($notifications),
            'mailErrors' => $mailErrors,
        ]);
    }

    #[Route('/parcours-porteur', name: 'change_parcours_porteur', methods: ['GET'])]
    public function changeParcoursPorteur(
        FormationRepository $formationRepository,
        FicheMatiere        $ficheMatiere,
    ): Response {
        return $this->render('fiche_matiere_v2/mutualisation/_change_parcours_porteur.html.twig', [
            'ficheMatiere' => $ficheMatiere,
            'formations' => $this->getAvailableFormationsForParcoursPorteur($formationRepository, $ficheMatiere),
        ]);
    }

    #[Route('/parcours-porteur/ajax', name: 'change_parcours_porteur_ajax', methods: ['POST'])]
    public function changeParcoursPorteurAjax(
        Request                            $request,
        EntityManagerInterface             $entityManager,
        FormationRepository                $formationRepository,
        ParcoursRepository                 $parcoursRepository,
        ElementConstitutifRepository       $elementConstitutifRepository,
        FicheMatiereMutualisableRepository $ficheMatiereParcoursRepository,
        FicheMatiere                       $ficheMatiere,
    ): Response {
        $data = JsonRequest::getFromRequest($request);

        switch ($data['field'] ?? null) {
            case 'parcours':
                $formation = isset($data['value']) ? $formationRepository->find($data['value']) : null;
                if (!$this->isFormationAvailableForParcoursPorteur($formationRepository, $ficheMatiere, $formation)) {
                    return $this->json([]);
                }

                $t = [];
                foreach ($parcoursRepository->findByFormation($formation) as $parcours) {
                    $t[] = [
                        'id' => $parcours->getId(),
                        'libelle' => $parcours->isParcoursDefaut() ? Parcours::PARCOURS_DEFAUT : $parcours->getDisplay(),
                    ];
                }

                return $this->json($t);

            case 'save':
                $formation = isset($data['formation']) ? $formationRepository->find($data['formation']) : null;
                if (!$this->isFormationAvailableForParcoursPorteur($formationRepository, $ficheMatiere, $formation)) {
                    return $this->json([
                        'success' => false,
                        'message' => 'La mention sélectionnée n\'est pas disponible pour cette fiche matière.',
                    ], Response::HTTP_BAD_REQUEST);
                }

                $parcours = $this->resolveParcoursPorteurCible(
                    $formation,
                    $data['parcours'] ?? null,
                    $parcoursRepository
                );

                if ($parcours === null || $parcours->getFormation() !== $formation) {
                    return $this->json([
                        'success' => false,
                        'message' => 'Veuillez sélectionner un parcours valide.',
                    ], Response::HTTP_BAD_REQUEST);
                }

                if ($ficheMatiere->getParcours()?->getId() === $parcours->getId()) {
                    return $this->json([
                        'success' => false,
                        'message' => 'Le parcours sélectionné est déjà le parcours porteur de cette fiche matière.',
                    ], Response::HTTP_BAD_REQUEST);
                }

                $ancienParcours = $ficheMatiere->getParcours();
                $ancienParcoursMutualise = false;

                $liaisonCible = $ficheMatiereParcoursRepository->findOneBy([
                    'ficheMatiere' => $ficheMatiere,
                    'parcours' => $parcours,
                ]);
                if ($liaisonCible !== null) {
                    $entityManager->remove($liaisonCible);
                }

                if ($ancienParcours !== null) {
                    $usagesAncienParcours = $elementConstitutifRepository->findBy([
                        'ficheMatiere' => $ficheMatiere,
                        'parcours' => $ancienParcours,
                    ]);

                    if (count($usagesAncienParcours) > 0) {
                        $this->updateFicheMatiereMutualisable(
                            $ficheMatiereParcoursRepository,
                            $ficheMatiere,
                            $ancienParcours,
                            $entityManager
                        );
                        $ancienParcoursMutualise = true;
                    }
                }

                $ficheMatiere->setParcours($parcours);
                $composante = $parcours->getFormation()?->getComposantePorteuse();
                if ($composante !== null && !$ficheMatiere->getComposante()->contains($composante)) {
                    $ficheMatiere->addComposante($composante);
                    $composante->addFicheMatiere($ficheMatiere);
                }

                $entityManager->flush();

                $ficheMatiere->setEnseignementMutualise(
                    $ficheMatiereParcoursRepository->count(['ficheMatiere' => $ficheMatiere]) > 0
                );
                $entityManager->flush();

                return $this->json([
                    'success' => true,
                    'ancienParcoursMutualise' => $ancienParcoursMutualise,
                    'message' => sprintf(
                        'Le parcours porteur est désormais "%s / %s".',
                        $parcours->getFormation()?->getDisplay() ?? 'Formation inconnue',
                        $parcours->isParcoursDefaut() ? Parcours::PARCOURS_DEFAUT : $parcours->getDisplay()
                    ),
                ]);
        }

        return $this->json([]);
    }

    private function updateFicheMatiereMutualisable(
        FicheMatiereMutualisableRepository $ficheMatiereParcoursRepository,
        FicheMatiere                       $ficheMatiere,
        mixed                              $parcours,
        EntityManagerInterface             $entityManager,
    ): void {
        $exist = $ficheMatiereParcoursRepository->findOneBy([
            'ficheMatiere' => $ficheMatiere,
            'parcours' => $parcours,
        ]);

        if ($exist === null) {
            $ficheMatiereParcours = new FicheMatiereMutualisable();
            $ficheMatiereParcours->setFicheMatiere($ficheMatiere);
            $ficheMatiereParcours->setParcours($parcours);
            $entityManager->persist($ficheMatiereParcours);
            $entityManager->flush();
        }
    }

    /**
     * @return Formation[]
     */
    private function getAvailableFormationsForParcoursPorteur(
        FormationRepository $formationRepository,
        FicheMatiere        $ficheMatiere,
    ): array {
        $typeDiplome = $ficheMatiere->getParcours()?->getFormation()?->getTypeDiplome();

        if ($typeDiplome !== null) {
            return $formationRepository->findByDpeAndTypeDiplome($this->getCampagneCollecte(), $typeDiplome);
        }

        return $formationRepository->findByCampagneCollecte($this->getCampagneCollecte());
    }

    private function isFormationAvailableForParcoursPorteur(
        FormationRepository    $formationRepository,
        FicheMatiere           $ficheMatiere,
        ?Formation             $formation,
    ): bool {
        if ($formation === null) {
            return false;
        }

        foreach ($this->getAvailableFormationsForParcoursPorteur($formationRepository, $ficheMatiere) as $availableFormation) {
            if ($availableFormation->getId() === $formation->getId()) {
                return true;
            }
        }

        return false;
    }

    private function resolveParcoursPorteurCible(
        ?Formation             $formation,
        mixed                  $parcoursId,
        ParcoursRepository     $parcoursRepository,
    ): ?Parcours {
        if ($formation === null) {
            return null;
        }

        if ($parcoursId !== null && $parcoursId !== '') {
            return $parcoursRepository->find($parcoursId);
        }

        return $formation->defaultParcours();
    }
}
