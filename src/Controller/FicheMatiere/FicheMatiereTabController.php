<?php

namespace App\Controller\FicheMatiere;

use App\Entity\FicheMatiere;
use App\Repository\FicheMatiereTabStateRepository;
use App\Service\FicheMatiere\FicheMatiereFieldUpdater;
use App\Service\FicheMatiere\FicheMatiereTabCompletionChecker;
use App\Service\FicheMatiere\FicheMatiereTabRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/fiche-matiere/v2', name: 'fiche_matiere_v2_')]
final class FicheMatiereTabController extends AbstractController
{
    #[Route('/{id}/tab/{tabKey}/autosave', name: 'tab_autosave', methods: ['POST'])]
    #[IsGranted('EDIT_FICHE_MATIERE', 'fiche_matiere')]
    public function autosave(
        Request                          $request,
        FicheMatiere                     $fiche_matiere,
        string                           $tabKey,
        FicheMatiereFieldUpdater         $updater,
        FicheMatiereTabStateRepository   $states,
        FicheMatiereTabCompletionChecker $checker,
        EntityManagerInterface           $em,
    ): Response
    {
        $field = (string)$request->request->get('field');
        $value = $request->request->get('value');

        $updater->applyForTab($fiche_matiere, $tabKey, $field, $value);

        $state = $states->getOrCreate($fiche_matiere, $tabKey);
//
        $state->setDone(false);
        $issues = $checker->getTabIssues($fiche_matiere, $tabKey);
        $isComplete = count($issues) === 0;

        $state->setDone(false);
        $state->setStatus($checker->computeStatus($isComplete, false));
        $em->flush();

        return $this->turboStreamsResponse($fiche_matiere, $tabKey, $state->getStatus(), $state->isDone(), $issues);

    }

    private function turboStreamsResponse(FicheMatiere $fiche_matiere, string $tabKey, string $status, bool $done, array $issues = []): Response
    {
        $response = $this->render('fiche_matiere_v2/tabs/_autosave.stream.html.twig', [
            'fiche_matiere' => $fiche_matiere,
            'tabKey' => $tabKey,
            'status' => $status,
            'done' => $done,
            'issues' => $issues,
//            'form' => $form,
        ]);
        $response->headers->set('Content-Type', 'text/vnd.turbo-stream.html');
        return $response;
    }

    #[Route('/{id}/tab/{tabKey}/done', name: 'tab_done', methods: ['POST'])]
    #[IsGranted('EDIT_FICHE_MATIERE', 'fiche_matiere')]
    public function done(
        Request                          $request,
        FicheMatiere                     $fiche_matiere,
        string                           $tabKey,
        FicheMatiereTabStateRepository   $states,
        FicheMatiereTabCompletionChecker $checker,
        EntityManagerInterface           $em,
    ): Response
    {
        FicheMatiereTabRegistry::assertTab($tabKey);

        $state = $states->getOrCreate($fiche_matiere, $tabKey);

        // CSRF (si tu l’as mis)
        $token = (string)$request->request->get('_token');
        if (!$this->isCsrfTokenValid('fiche_matiere_tab_done_' . $fiche_matiere->getId() . '_' . $tabKey, $token)) {
            return new Response('Invalid CSRF token', 419);
        }

        $askedDone = (bool)$request->request->get('done');

        $issues = $checker->getTabIssues($fiche_matiere, $tabKey);
        $isComplete = count($issues) === 0;

        $state->setDone($askedDone && $isComplete);
        $state->setStatus($checker->computeStatus($isComplete, $state->isDone()));
        $em->flush();

        return $this->turboStreamsResponse($fiche_matiere, $tabKey, $state->getStatus(), $state->isDone(), $issues);

    }
}
