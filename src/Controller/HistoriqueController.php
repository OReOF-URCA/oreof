<?php

namespace App\Controller;

use App\Classes\GetDpeParcours;
use App\Classes\GetHistorique;
use App\Classes\JsonReponse;
use App\Classes\Process\ParcoursProcess;
use App\Classes\ValidationProcess;
use App\DTO\TranslatableKey;
use App\Entity\FicheMatiere;
use App\Entity\Formation;
use App\Entity\Historique;
use App\Entity\HistoriqueFicheMatiere;
use App\Entity\HistoriqueFormation;
use App\Entity\HistoriqueParcours;
use App\Entity\Parcours;
use App\Events\HistoriqueFormationEditEvent;
use App\Twig\HistoriqueExtension;
use App\Utils\Tools;
use App\Utils\TurboStreamResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\Turbo\TurboStreamResponse;

class HistoriqueController extends BaseController
{
    public function __construct(
        private readonly ValidationProcess $validationProcess,
        private readonly ParcoursProcess $parcoursProcess,
    ) {
    }

    #[Route('/historique/formation/{formation}', name: 'app_historique_formation')]
    public function formation(
        TurboStreamResponseFactory $turboStream,
        Formation $formation
    ): Response {
        $historiques = $this->getHistoriquesFormation($formation);

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('formation.historique.title'),
            new TranslatableKey('formation.historique.description'),
            'historique/_formation.html.twig',
            [
                'historiques' => $historiques,
                'formation' => $formation,
                'type' => 'formation',
            ]
        );
    }

    #[Route('/historique/parcours/{parcours}', name: 'app_historique_parcours')]
    public function parcours(
        TurboStreamResponseFactory $turboStream,
        Parcours $parcours
    ): Response {
        $historiques = $this->getHistoriquesParcours($parcours);

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('parcours.historique.title'),
            new TranslatableKey('parcours.historique.description'),
            'historique/_formation.html.twig',
            [
                'historiques' => $historiques,
                'parcours' => $parcours,
                'formation' => $parcours->getFormation(),
                'type' => 'parcours',
            ]
        );
    }

    #[Route('/historique/fiche_matiere/{ficheMatiere}', name: 'app_historique_fiche_matiere')]
    public function fiche_matiere(
        TurboStreamResponseFactory $turboStream,
        FicheMatiere $ficheMatiere
    ): Response {
        $historiques = $this->getHistoriquesFicheMatiere($ficheMatiere);

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('fiche_matiere.historique.title'),
            new TranslatableKey('fiche_matiere.historique.description'),
            'historique/_formation.html.twig',
            [
                'historiques' => $historiques,
                'ficheMatiere' => $ficheMatiere,
                'type' => 'fiche_matiere',
            ]
        );
    }

    #[Route('/historique/edit/{historique}', name: 'app_historique_edit', methods: ['GET', 'POST'])]
    public function edit(
        GetHistorique $getHistorique,
        Historique $historique,
        Request $request,
        TurboStreamResponseFactory $turboStream,
        EntityManagerInterface $entityManager,
        EventDispatcherInterface $eventDispatcher,
        TranslatorInterface $translator
    ): Response {
        $type = get_class($historique);
        $etape = $historique->getEtape();
        $etapeKey = $etape;
        if (array_key_exists($etape, HistoriqueExtension::TRADUCTIONS)) {
            $etapeKey = HistoriqueExtension::TRADUCTIONS[$etape];
        }

        $process = $this->validationProcess->getEtape($etapeKey);

        // Resolve context (formation, parcours, fiche_matiere)
        $contextType = $request->query->get('contextType') ?? $request->request->get('contextType');
        $contextId = $request->query->get('contextId') ?? $request->request->get('contextId');

        if (!$contextType) {
            if ($historique instanceof HistoriqueParcours) {
                $contextType = 'parcours';
                $contextId = $historique->getParcours()?->getId();
            } elseif ($historique instanceof HistoriqueFormation) {
                $contextType = 'formation';
                $contextId = $historique->getFormation()?->getId();
            } elseif ($historique instanceof HistoriqueFicheMatiere) {
                $contextType = 'fiche_matiere';
                $contextId = $historique->getFicheMatiere()?->getId();
            } else {
                $contextType = 'formation';
            }
        }

        // Build back URL for canceling and returning to history modal
        $backUrl = match ($contextType) {
            'parcours' => $this->generateUrl('app_historique_parcours', ['parcours' => $contextId]),
            'fiche_matiere' => $this->generateUrl('app_historique_fiche_matiere', ['ficheMatiere' => $contextId]),
            default => $this->generateUrl('app_historique_formation', ['formation' => $contextId]),
        };

        if ($historique instanceof HistoriqueParcours) {
            $parcours = $historique->getParcours();
            if ($parcours !== null) {
                $objet = GetDpeParcours::getFromParcours($parcours);
                if ($objet !== null) {
                    $processData = $this->parcoursProcess->etatParcours($objet, $process);

                    if ($etape === 'cfvu') {
                        $laisserPasser = $getHistorique->getHistoriqueParcoursLastStep($objet, 'conseil');
                    }
                }
            }
        }

        if ($request->isMethod('POST')) {
            if ($historique instanceof HistoriqueParcours) {
                $this->parcoursProcess->editParcours($historique, $this->getUser(), $etape, $request);
            } elseif ($historique instanceof HistoriqueFormation) {
                $histoEvent = new HistoriqueFormationEditEvent($historique, $this->getUser(), $etape, 'valide', $request);
                $eventDispatcher->dispatch($histoEvent, HistoriqueFormationEditEvent::EDIT_HISTORIQUE_FORMATION);
            } elseif ($historique instanceof HistoriqueFicheMatiere) {
                if ($request->request->has('date') && $request->request->get('date') !== null) {
                    $historique->setDate(Tools::convertDate($request->request->get('date')));
                }
                if ($request->request->has('argumentaire')) {
                    $historique->setCommentaire($request->request->get('argumentaire'));
                }
                $entityManager->flush();
            }

            // Reload refreshed history entries for the parent context
            if ($contextType === 'parcours') {
                $parcours = $entityManager->getRepository(Parcours::class)->find($contextId);
                $historiques = $parcours ? $this->getHistoriquesParcours($parcours) : [];
                $bodyHtml = $this->renderView('historique/_formation.html.twig', [
                    'historiques' => $historiques,
                    'parcours' => $parcours,
                    'formation' => $parcours?->getFormation(),
                    'type' => 'parcours',
                ]);
                $title = $translator->trans('parcours.historique.title');
                $subtitle = $translator->trans('parcours.historique.description');
            } elseif ($contextType === 'fiche_matiere') {
                $ficheMatiere = $entityManager->getRepository(FicheMatiere::class)->find($contextId);
                $historiques = $ficheMatiere ? $this->getHistoriquesFicheMatiere($ficheMatiere) : [];
                $bodyHtml = $this->renderView('historique/_formation.html.twig', [
                    'historiques' => $historiques,
                    'ficheMatiere' => $ficheMatiere,
                    'type' => 'fiche_matiere',
                ]);
                $title = $translator->trans('fiche_matiere.historique.title');
                $subtitle = $translator->trans('fiche_matiere.historique.description');
            } else {
                $formation = $entityManager->getRepository(Formation::class)->find($contextId);
                $historiques = $formation ? $this->getHistoriquesFormation($formation) : [];
                $bodyHtml = $this->renderView('historique/_formation.html.twig', [
                    'historiques' => $historiques,
                    'formation' => $formation,
                    'type' => 'formation',
                ]);
                $title = $translator->trans('formation.historique.title');
                $subtitle = $translator->trans('formation.historique.description');
            }

            $footerHtml = $this->renderView('_ui/_footer_cancel.html.twig');
            $toastHtml = $this->renderView('_ui/_toast.stream.html.twig', [
                'toast_type' => 'success',
                'message' => 'L\'historique a été modifié avec succès.',
            ]);

            return $turboStream->appendStreams(
                $this->renderView('_ui/open.stream.html.twig', [
                    'title' => $title,
                    'subtitle' => $subtitle,
                    'body' => $bodyHtml,
                    'footer' => $footerHtml,
                ]),
                $toastHtml
            );
        }

        // GET request: render edit modal content
        $bodyHtml = $this->renderView('historique/_edit.html.twig', [
            'process' => $process,
            'type' => $type,
            'etape' => $etape,
            'processData' => $processData ?? null,
            'historique' => $historique,
            'laisserPasser' => $laisserPasser ?? null,
            'contextType' => $contextType,
            'contextId' => $contextId,
            'backUrl' => $backUrl,
        ]);

        $footerHtml = $this->renderView('historique/_footer_edit.html.twig', [
            'backUrl' => $backUrl,
        ]);

        return $turboStream->streamOpenModal(
            'Modifier l\'enregistrement de l\'historique',
            'Étape : ' . ($process['label'] ?? $etape),
            $bodyHtml,
            $footerHtml
        );
    }

    #[Route('/historique/delete/{historique}', name: 'app_historique_delete', methods: ['POST', 'DELETE'])]
    public function delete(
        EntityManagerInterface $entityManager,
        TurboStreamResponseFactory $turboStream,
        Request $request,
        Historique $historique
    ): Response {
        $id = $historique->getId();
        $token = $request->request->get('_token') ?? $request->request->get('csrf_token');

        if ($this->isCsrfTokenValid('delete' . $id, $token)) {
            $entityManager->remove($historique);
            $entityManager->flush();

            if (
                $request->getPreferredFormat() === TurboStreamResponse::STREAM_FORMAT
                || str_contains((string)$request->headers->get('Accept'), 'text/vnd.turbo-stream.html')
            ) {
                return $turboStream->appendStreams(
                    '<turbo-stream action="remove" target="historique_' . $id . '"></turbo-stream>',
                    $this->renderView('_ui/_toast.stream.html.twig', [
                        'toast_type' => 'success',
                        'message' => 'L\'entrée de l\'historique a été supprimée.',
                    ])
                );
            }

            return JsonReponse::success('Historique supprimé');
        }

        if (
            $request->getPreferredFormat() === TurboStreamResponse::STREAM_FORMAT
            || str_contains((string)$request->headers->get('Accept'), 'text/vnd.turbo-stream.html')
        ) {
            return $turboStream->streamToastError('Erreur lors de la suppression de l\'historique.');
        }

        return JsonReponse::error('Erreur lors de la suppression');
    }

    private function getHistoriquesFormation(Formation $formation): array
    {
        $historiques = [];
        foreach ($formation->getParcours() as $parcours) {
            foreach ($parcours->getHistoriqueParcours() as $h) {
                $key = ($h->getCreated()?->getTimestamp() ?? 0) . '_' . $h->getId();
                $historiques[$key] = $h;
            }
        }

        foreach ($formation->getHistoriqueFormations() as $h) {
            $key = ($h->getCreated()?->getTimestamp() ?? 0) . '_' . $h->getId();
            $historiques[$key] = $h;
        }
        krsort($historiques);

        return $historiques;
    }

    private function getHistoriquesParcours(Parcours $parcours): array
    {
        $historiques = [];
        foreach ($parcours->getHistoriqueParcours() as $h) {
            $key = ($h->getCreated()?->getTimestamp() ?? 0) . '_' . $h->getId();
            $historiques[$key] = $h;
        }

        if ($parcours->getFormation() !== null) {
            foreach ($parcours->getFormation()->getHistoriqueFormations() as $h) {
                $key = ($h->getCreated()?->getTimestamp() ?? 0) . '_' . $h->getId();
                $historiques[$key] = $h;
            }
        }
        krsort($historiques);

        return $historiques;
    }

    private function getHistoriquesFicheMatiere(FicheMatiere $ficheMatiere): array
    {
        $historiques = [];
        foreach ($ficheMatiere->getHistoriqueFicheMatieres() as $h) {
            $key = ($h->getCreated()?->getTimestamp() ?? 0) . '_' . $h->getId();
            $historiques[$key] = $h;
        }
        krsort($historiques);

        return $historiques;
    }
}
