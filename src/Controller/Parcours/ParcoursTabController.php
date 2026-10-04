<?php

namespace App\Controller\Parcours;

use App\Entity\Parcours;
use App\Repository\ParcoursTabStateRepository;
use App\Service\Parcours\ParcoursFieldUpdater;
use App\Service\Parcours\ParcoursTabCompletionChecker;
use App\Service\Parcours\ParcoursTabRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/parcours/v2', name: 'parcours_v2_')]
final class ParcoursTabController extends AbstractController
{
    #[Route('/{id}/tab/{tabKey}/autosave', name: 'tab_autosave', methods: ['POST'])]
    public function autosave(
        Request                    $request,
        Parcours                   $parcours,
        string                     $tabKey,
        ParcoursFieldUpdater         $updater,
        ParcoursTabStateRepository $states,
        ParcoursTabCompletionChecker $checker,
        EntityManagerInterface     $em,
    ): Response
    {
        $field = (string)$request->request->get('field');
        $value = $request->request->get('value');

        $updater->applyForTab($parcours, $tabKey, $field, $value);

        $state = $states->getOrCreate($parcours, $tabKey);
//
        $state->setDone(false);
        $issues = $checker->getTabIssues($parcours, $tabKey);
        $isComplete = count($issues) === 0;

        $state->setDone(false);
        $state->setStatus($checker->computeStatus($isComplete, false));
        $em->flush();

        return $this->turboStreamsResponse($parcours, $tabKey, $state->getStatus(), $state->isDone(), $issues);

    }


    #[Route('/{id}/tab/{tabKey}/done', name: 'tab_done', methods: ['POST'])]
    public function done(
        Request                    $request,
        Parcours                   $parcours,
        string                     $tabKey,
        ParcoursTabStateRepository $states,
        ParcoursTabCompletionChecker $checker,
        EntityManagerInterface     $em,
    ): Response
    {
        ParcoursTabRegistry::assertTab($tabKey);

        $state = $states->getOrCreate($parcours, $tabKey);

        // CSRF (si tu l’as mis)
        $token = (string)$request->request->get('_token');
        if (!$this->isCsrfTokenValid('parcours_tab_done_' . $parcours->getId() . '_' . $tabKey, $token)) {
            return new Response('Invalid CSRF token', 419);
        }

        $askedDone = (bool)$request->request->get('done');

        $issues = $checker->getTabIssues($parcours, $tabKey);
        $isComplete = count($issues) === 0;

        $state->setDone($askedDone && $isComplete);
        $state->setStatus($checker->computeStatus($isComplete, $state->isDone()));
        $em->flush();

        return $this->turboStreamsResponse($parcours, $tabKey, $state->getStatus(), $state->isDone(), $issues);

    }


    private function turboStreamsResponse(Parcours $parcours, string $tabKey, string $status, bool $done, array $issues = []): Response
    {
        $response = $this->render('parcours_v2/tabs/_autosave.stream.html.twig', [
            'parcours' => $parcours,
            'tabKey' => $tabKey,
            'status' => $status,
            'done' => $done,
            'issues' => $issues,
        ]);
        $response->headers->set('Content-Type', 'text/vnd.turbo-stream.html');
        return $response;
    }
}
