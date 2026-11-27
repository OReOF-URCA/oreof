<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Coherence\CoherenceCheckResult;
use App\DTO\TranslatableKey;
use App\Entity\FicheMatiere;
use App\Entity\Parcours;
use App\Service\Coherence\CoherenceAnalyzerService;
use App\Utils\TurboStreamResponseFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/coherence', name: 'app_coherence_')]
#[IsGranted('ROLE_ADMIN')]
class CoherenceController extends BaseController
{
    public function __construct(
        private readonly CoherenceAnalyzerService   $coherenceAnalyzer,
        private readonly TurboStreamResponseFactory $turboStream,
    )
    {
    }

    #[Route('/parcours/{parcours}', name: 'parcours_modal', requirements: ['parcours' => '\d+'], methods: ['GET'])]
    public function parcoursModal(Request $request, Parcours $parcours): Response
    {
        $result = $this->coherenceAnalyzer->getIndicatorForParcours($parcours);
        $context = $this->buildParcoursModalContext($parcours, $result);

        if ($this->isTurboOrAjax($request)) {
            return $this->turboStream->streamOpenModalFromTemplates(
                new TranslatableKey('coherence.parcours.modal_title', ['%libelle%' => (string)$parcours->getDisplay()], 'modal'),
                new TranslatableKey('coherence.parcours.modal_subtitle', ['%formation%' => (string)($parcours->getFormation()?->getDisplay() ?? '')], 'modal'),
                'coherence/_modal.html.twig',
                $context,
                'coherence/_modal_footer.html.twig',
                $context
            );
        }

        return $this->render('coherence/show.html.twig', array_merge($context, [
            'title' => sprintf('Cohérence — %s', $parcours->getDisplay()),
        ]));
    }

    #[Route('/parcours/{parcours}/analyser', name: 'parcours_analyze', requirements: ['parcours' => '\d+'], methods: ['POST'])]
    public function analyzeParcours(Request $request, Parcours $parcours): Response
    {
        $token = (string)$request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('coherence_analyze_parcours_' . $parcours->getId(), $token)) {
            if ($this->isTurboOrAjax($request)) {
                return $this->turboStream->streamToastError('Jeton de sécurité invalide. Veuillez recharger la page.');
            }

            $this->addFlash('danger', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('parcours_v2_voir', ['parcours' => $parcours->getId()]);
        }

        $useAi = $request->request->getBoolean('use_ai', true);
        $force = $request->request->getBoolean('force', true);

        $result = $this->coherenceAnalyzer->analyzeParcours(
            $parcours,
            useAi: $useAi,
            forceRefresh: $force,
            persist: true
        );

        if ($this->isTurboOrAjax($request)) {
            $context = $this->buildParcoursModalContext($parcours, $result);

            $modalStream = $this->turboStream->renderModalStreams(
                new TranslatableKey('coherence.parcours.modal_title', ['%libelle%' => (string)$parcours->getDisplay()], 'modal'),
                new TranslatableKey('coherence.parcours.modal_subtitle', ['%formation%' => (string)($parcours->getFormation()?->getDisplay() ?? '')], 'modal'),
                'coherence/_modal.html.twig',
                $context,
                'coherence/_modal_footer.html.twig',
                $context
            );

            $badgeStream = $this->renderView('coherence/_badge_stream.html.twig', [
                'targets' => '.js-coherence-badge-parcours-' . $parcours->getId(),
                'parcours' => $parcours,
                'ficheMatiere' => null,
                'result' => $result,
            ]);

            $toastMessage = $result->fallbackReason !== null
                ? 'Analyse déterministe enregistrée (IA indisponible : ' . $result->fallbackReason . ').'
                : ($result->isAiGenerated
                    ? sprintf('Analyse IA terminée : score %d/100.', $result->score)
                    : sprintf('Pré-filtre déterministe enregistré : score %d/100.', $result->score));

            $toastStream = $this->turboStream->renderToastStream(
                $toastMessage,
                $result->fallbackReason !== null ? 'warning' : 'success',
                false
            );

            return $this->turboStream->appendStreams($modalStream, $badgeStream, $toastStream);
        }

        $this->addFlash('success', sprintf('Analyse de cohérence mise à jour (%d/100).', $result->score));

        return $this->redirectToRoute('parcours_v2_voir', ['parcours' => $parcours->getId()]);
    }

    #[Route('/fiche-matiere/{ficheMatiere}', name: 'fiche_modal', requirements: ['ficheMatiere' => '\d+'], methods: ['GET'])]
    public function ficheModal(Request $request, FicheMatiere $ficheMatiere): Response
    {
        $result = $this->coherenceAnalyzer->getIndicatorForFicheMatiere($ficheMatiere);
        $context = $this->buildFicheModalContext($ficheMatiere, $result);

        if ($this->isTurboOrAjax($request)) {
            return $this->turboStream->streamOpenModalFromTemplates(
                new TranslatableKey('coherence.fiche.modal_title', ['%libelle%' => (string)$ficheMatiere->getLibelle()], 'modal'),
                new TranslatableKey('coherence.fiche.modal_subtitle', ['%parcours%' => (string)($ficheMatiere->getParcours()?->getDisplay() ?? 'Hors diplôme')], 'modal'),
                'coherence/_modal.html.twig',
                $context,
                'coherence/_modal_footer.html.twig',
                $context
            );
        }

        return $this->render('coherence/show.html.twig', array_merge($context, [
            'title' => sprintf('Cohérence — %s', $ficheMatiere->getLibelle()),
        ]));
    }

    #[Route('/fiche-matiere/{ficheMatiere}/analyser', name: 'fiche_analyze', requirements: ['ficheMatiere' => '\d+'], methods: ['POST'])]
    public function analyzeFiche(Request $request, FicheMatiere $ficheMatiere): Response
    {
        $token = (string)$request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('coherence_analyze_fiche_' . $ficheMatiere->getId(), $token)) {
            if ($this->isTurboOrAjax($request)) {
                return $this->turboStream->streamToastError('Jeton de sécurité invalide. Veuillez recharger la page.');
            }

            $this->addFlash('danger', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('fiche_matiere_v2_voir', ['slug' => $ficheMatiere->getSlug()]);
        }

        $useAi = $request->request->getBoolean('use_ai', true);
        $force = $request->request->getBoolean('force', true);

        $result = $this->coherenceAnalyzer->analyzeFicheMatiere(
            $ficheMatiere,
            useAi: $useAi,
            forceRefresh: $force,
            persist: true
        );

        if ($this->isTurboOrAjax($request)) {
            $context = $this->buildFicheModalContext($ficheMatiere, $result);

            $modalStream = $this->turboStream->renderModalStreams(
                new TranslatableKey('coherence.fiche.modal_title', ['%libelle%' => (string)$ficheMatiere->getLibelle()], 'modal'),
                new TranslatableKey('coherence.fiche.modal_subtitle', ['%parcours%' => (string)($ficheMatiere->getParcours()?->getDisplay() ?? 'Hors diplôme')], 'modal'),
                'coherence/_modal.html.twig',
                $context,
                'coherence/_modal_footer.html.twig',
                $context
            );

            $badgeStream = $this->renderView('coherence/_badge_stream.html.twig', [
                'targets' => '.js-coherence-badge-fiche-' . $ficheMatiere->getId(),
                'parcours' => null,
                'ficheMatiere' => $ficheMatiere,
                'result' => $result,
            ]);

            $toastMessage = $result->fallbackReason !== null
                ? 'Analyse déterministe enregistrée (IA indisponible : ' . $result->fallbackReason . ').'
                : ($result->isAiGenerated
                    ? sprintf('Analyse IA terminée : score %d/100.', $result->score)
                    : sprintf('Pré-filtre déterministe enregistré : score %d/100.', $result->score));

            $toastStream = $this->turboStream->renderToastStream(
                $toastMessage,
                $result->fallbackReason !== null ? 'warning' : 'success',
                false
            );

            return $this->turboStream->appendStreams($modalStream, $badgeStream, $toastStream);
        }

        $this->addFlash('success', sprintf('Analyse de cohérence mise à jour (%d/100).', $result->score));

        return $this->redirectToRoute('fiche_matiere_v2_voir', ['slug' => $ficheMatiere->getSlug()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildParcoursModalContext(Parcours $parcours, CoherenceCheckResult $result): array
    {
        $canEdit = $this->isGranted('EDIT', $parcours);

        return [
            'entityType' => 'parcours',
            'parcours' => $parcours,
            'ficheMatiere' => null,
            'result' => $result,
            'aiConfigured' => $this->coherenceAnalyzer->isAiConfigured(),
            'analyzeRoute' => $this->generateUrl('app_coherence_parcours_analyze', ['parcours' => $parcours->getId()]),
            'csrfTokenId' => 'coherence_analyze_parcours_' . $parcours->getId(),
            'editUrl' => $canEdit ? $this->generateUrl('parcours_v2_modifier', ['parcours' => $parcours->getId()]) : null,
            'viewUrl' => $this->generateUrl('parcours_v2_voir', ['parcours' => $parcours->getId()]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFicheModalContext(FicheMatiere $ficheMatiere, CoherenceCheckResult $result): array
    {
        $canEdit = $this->isGranted('EDIT', $ficheMatiere);

        return [
            'entityType' => 'fiche_matiere',
            'parcours' => null,
            'ficheMatiere' => $ficheMatiere,
            'result' => $result,
            'aiConfigured' => $this->coherenceAnalyzer->isAiConfigured(),
            'analyzeRoute' => $this->generateUrl('app_coherence_fiche_analyze', ['ficheMatiere' => $ficheMatiere->getId()]),
            'csrfTokenId' => 'coherence_analyze_fiche_' . $ficheMatiere->getId(),
            'editUrl' => $canEdit ? $this->generateUrl('fiche_matiere_v2_modifier', ['id' => $ficheMatiere->getId()]) : null,
            'viewUrl' => $this->generateUrl('fiche_matiere_v2_voir', ['slug' => $ficheMatiere->getSlug()]),
        ];
    }

    private function isTurboOrAjax(Request $request): bool
    {
        return $request->getPreferredFormat() === 'turbo-stream'
            || str_contains((string)$request->headers->get('Accept', ''), 'text/vnd.turbo-stream.html')
            || $request->headers->get('Turbo-Frame') !== null
            || $request->isXmlHttpRequest();
    }
}
