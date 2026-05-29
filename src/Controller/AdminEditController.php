<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/AdminEditController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 13/06/2025 13:17
 */

namespace App\Controller;

use App\Classes\JsonReponse;
use App\Classes\ValidationProcess;
use App\Events\HistoriqueFormationEvent;
use App\Events\HistoriqueParcoursEvent;
use App\Repository\DpeParcoursRepository;
use App\Repository\FormationRepository;
use App\Repository\ParcoursRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/edit', name: 'app_admin_edit')]
class AdminEditController extends BaseController
{

    public function __construct(
        private readonly ValidationProcess        $validationProcess,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EntityManagerInterface   $entityManager,
    )
    {
    }

    #[Route('/{id}/{type}', name: '_modal')]
    public function afficheModal(
        ParcoursRepository  $parcoursRepository,
        FormationRepository $formationRepository,
        int    $id,
        string $type,
    ): Response
    {
        switch ($type) {
            case 'parcours':
                $object = $parcoursRepository->find($id);
                if (!$object) {
                    throw $this->createNotFoundException('Parcours not found');
                }
                break;
            case 'formation':
                $object = $formationRepository->find($id);
                if (!$object) {
                    throw $this->createNotFoundException('Formation not found');
                }
                break;
            default:
                throw $this->createNotFoundException('Invalid type');
        }


        return $this->render('admin/edit/_modal.html.twig', [
            'id' => $id,
            'type' => $type,
            'object' => $object,
            'etats' => $this->validationProcess->getProcess(),
        ]);
    }

    #[Route('/force-next/{type}/{id}', name: '_force_next', methods: ['POST'])]
    public function forceNext(
        DpeParcoursRepository $dpeParcoursRepository,
        ParcoursRepository    $parcoursRepository,
        FormationRepository   $formationRepository,
        Request               $request,
        int                   $id,
        string                $type,
    ): Response
    {
        $csrfToken = (string)$request->request->get('_token', '');
        if ($this->isCsrfTokenValid('admin_force_next_' . $type . '_' . $id, $csrfToken) === false) {
            return JsonReponse::error('Jeton CSRF invalide');
        }

        $withHistory = $request->request->getBoolean('with_history', false);

        switch ($type) {
            case 'parcours':
                $object = $parcoursRepository->find($id);
                if ($object === null) {
                    return JsonReponse::error('Parcours non trouvé');
                }

                $dpe = $dpeParcoursRepository->findOneBy([
                    'parcours' => $object,
                    'campagneCollecte' => $this->getCampagneCollecte(),
                ]);
                if ($dpe === null) {
                    return JsonReponse::error('DPE du parcours non trouvé');
                }
                break;

            case 'formation':
                $object = $formationRepository->find($id);
                if ($object === null) {
                    return JsonReponse::error('Formation non trouvée');
                }

                $parcours = $object->getParcours()->first();
                if ($parcours === false) {
                    return JsonReponse::error('Parcours de la formation non trouvé');
                }

                $dpe = $dpeParcoursRepository->findOneBy([
                    'parcours' => $parcours,
                    'campagneCollecte' => $this->getCampagneCollecte(),
                ]);
                if ($dpe === null) {
                    return JsonReponse::error('DPE de la formation non trouvé');
                }
                break;

            default:
                return JsonReponse::error('Type invalide');
        }

        $currentPlace = array_key_first($dpe->getEtatValidation());
        if (!is_string($currentPlace) || $currentPlace === '') {
            return JsonReponse::error('Etat courant du workflow introuvable');
        }

        $nextStep = $this->validationProcess->getNextStepFromPlace($currentPlace);
        if ($nextStep === null) {
            return JsonReponse::error('Aucune étape suivante trouvée pour cet état');
        }

        $dpe->setEtatValidation([$nextStep['to'] => 1]);

        if ($withHistory) {
            if ($type === 'parcours') {
                $event = new HistoriqueParcoursEvent($object, $this->getUser(), $currentPlace, 'force_admin', $request);
                $this->eventDispatcher->dispatch($event, HistoriqueParcoursEvent::ADD_HISTORIQUE_PARCOURS);
            } else {
                $event = new HistoriqueFormationEvent($object, $this->getUser(), $currentPlace, 'force_admin', $request);
                $this->eventDispatcher->dispatch($event, HistoriqueFormationEvent::ADD_HISTORIQUE_FORMATION);
            }
        }

        $this->entityManager->flush();

        return JsonReponse::success('Passage forcé vers l\'étape suivante effectué');
    }
}

