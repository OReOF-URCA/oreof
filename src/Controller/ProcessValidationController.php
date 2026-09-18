<?php

declare(strict_types=1);

namespace App\Controller;

use App\Classes\JsonReponse;
use App\Classes\ValidationProcess;
use App\Entity\DpeParcours;
use App\Entity\User;
use App\Enums\TypeModificationDpeEnum;
use App\Events\HistoriqueFormationEvent;
use App\Events\HistoriqueParcoursEvent;
use App\Repository\DpeParcoursRepository;
use App\Repository\FormationRepository;
use App\Repository\ParcoursRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/** Administrative corrections only; user transitions use WorkflowOperationExecutor. */
final class ProcessValidationController extends BaseController
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidationProcess $validationProcess,
    ) {
    }

    #[Route('/validation/edit/{type}/{id}', name: 'app_validation_edit')]
    public function edit(
        DpeParcoursRepository $dpeRepository,
        ParcoursRepository $parcoursRepository,
        FormationRepository $formationRepository,
        Request $request,
        string $type,
        int $id,
    ): Response {
        if ($request->isMethod('POST')) {
            $place = (string) $request->request->get('etat_dpe');

            if ('formation' === $type) {
                $formation = $formationRepository->find($id);
                if (null === $formation || $formation->isHasParcours()) {
                    return JsonReponse::error('Formation non trouvée');
                }

                $dpe = $dpeRepository->findOneBy([
                    'parcours' => $formation->getParcours()->first(),
                    'campagneCollecte' => $this->getCampagneCollecte(),
                ]);
                if (!$dpe instanceof DpeParcours) {
                    return JsonReponse::error('DPE non trouvé');
                }

                $dpe->setEtatValidation([$place => 1]);
                $this->eventDispatcher->dispatch(
                    new HistoriqueFormationEvent($formation, $this->getCurrentUserOrFail(), (string) $request->request->get('etat'), 'valide', $request),
                    HistoriqueFormationEvent::ADD_HISTORIQUE_FORMATION,
                );
                $this->entityManager->flush();

                return JsonReponse::success('Validation modifiée');
            }

            if ('parcours' === $type) {
                $parcours = $parcoursRepository->find($id);
                if (null === $parcours) {
                    return JsonReponse::error('Parcours non trouvé');
                }

                $dpe = $dpeRepository->findOneBy([
                    'parcours' => $parcours,
                    'campagneCollecte' => $this->getCampagneCollecte(),
                ]);
                if (!$dpe instanceof DpeParcours) {
                    return JsonReponse::error('DPE non trouvé');
                }

                $dpe->setEtatValidation([$place => 1]);
                $this->eventDispatcher->dispatch(
                    new HistoriqueParcoursEvent($parcours, $this->getCurrentUserOrFail(), $place, 'valide', $request),
                    HistoriqueParcoursEvent::ADD_HISTORIQUE_PARCOURS,
                );
                $this->entityManager->flush();

                return JsonReponse::success('Validation modifiée');
            }

            return JsonReponse::error('Type de validation inconnu');
        }

        return $this->renderEditForm($type, $id);
    }

    #[Route('/type_modif_dpe/edit/{type}/{id}', name: 'app_type_modif_dpe_edit')]
    public function typeModifDpeEdit(
        DpeParcoursRepository $dpeRepository,
        ParcoursRepository $parcoursRepository,
        FormationRepository $formationRepository,
        Request $request,
        string $type,
        int $id,
    ): Response {
        if (!$request->isMethod('POST')) {
            return $this->renderEditForm($type, $id);
        }

        $modificationType = TypeModificationDpeEnum::tryFrom((string) $request->request->get('type_modif_dpe'));
        if (null === $modificationType) {
            return JsonReponse::error('Type de modification invalide');
        }

        $dpe = null;
        if ('formation' === $type) {
            $formation = $formationRepository->find($id);
            if (null !== $formation && !$formation->isHasParcours()) {
                $dpe = $dpeRepository->findOneBy([
                    'parcours' => $formation->getParcours()->first(),
                    'campagneCollecte' => $this->getCampagneCollecte(),
                ]);
            }
        } elseif ('parcours' === $type) {
            $parcours = $parcoursRepository->find($id);
            if (null !== $parcours) {
                $dpe = $dpeRepository->findOneBy([
                    'parcours' => $parcours,
                    'campagneCollecte' => $this->getCampagneCollecte(),
                ]);
            }
        }

        if (!$dpe instanceof DpeParcours) {
            return JsonReponse::error('DPE non trouvé');
        }

        $dpe->setEtatReconduction($modificationType);
        $this->entityManager->flush();

        return JsonReponse::success('Type de modification modifié');
    }

    private function renderEditForm(string $type, int $id): Response
    {
        return $this->render('process_validation/_edit.html.twig', [
            'etats' => $this->validationProcess->getProcess(),
            'type' => $type,
            'id' => $id,
        ]);
    }

    private function getCurrentUserOrFail(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur ORéOF requis.');
        }

        return $user;
    }
}
