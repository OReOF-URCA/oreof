<?php

namespace App\Controller\Formation;

use App\Entity\Formation;
use App\Repository\FormationTabStateRepository;
use App\Service\Formation\FormationFieldUpdater;
use App\Service\Formation\FormationTabCompletionChecker;
use App\Service\Formation\FormationTabRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/formation/v2', name: 'formation_v2_')]
#[IsGranted('ROLE_ADMIN')]
final class FormationTabController extends AbstractController
{
    #[Route('/{id}/tab/{tabKey}/autosave', name: 'tab_autosave', methods: ['POST'])]
    public function autosave(
        Request                     $request,
        Formation                   $formation,
        string                      $tabKey,
        FormationFieldUpdater         $updater,
        FormationTabStateRepository $states,
        FormationTabCompletionChecker $checker,
        EntityManagerInterface      $em,
    ): Response
    {
        $field = (string)$request->request->get('field');
        $value = $request->request->get('value');

        $updater->applyForTab($formation, $tabKey, $field, $value);

        $state = $states->getOrCreate($formation, $tabKey);
//
//        // Toute modification décoche done
        $state->setDone(false);
//        $isComplete = $checker->isTabComplete($formation, $tabKey);
//        $state->setStatus($checker->computeStatus($isComplete, false));
//        $em->flush();
//
//        return $this->turboStreamsResponse(
//            $formation,
//            $tabKey,
//            $state->getStatus(),
//            $state->isDone(),
//        );
        $issues = $checker->getTabIssues($formation, $tabKey);
        $isComplete = count($issues) === 0;

        $state->setDone(false);
        $state->setIssues($issues); //todo: sauvegardedes issues dans la BDD
        $state->setStatus($checker->computeStatus($isComplete, false));
        $em->flush();

        return $this->turboStreamsResponse($formation, $tabKey, $state->getStatus(), $state->isDone(), $issues);

    }


    #[Route('/{id}/tab/{tabKey}/done', name: 'tab_done', methods: ['POST'])]
    public function done(
        Request                     $request,
        Formation                   $formation,
        string                      $tabKey,
        FormationTabStateRepository $states,
        FormationTabCompletionChecker $checker,
        EntityManagerInterface      $em,
    ): Response
    {
        FormationTabRegistry::assertTab($tabKey);

        $state = $states->getOrCreate($formation, $tabKey);

        // CSRF (si tu l’as mis)
        $token = (string)$request->request->get('_token');
        if (!$this->isCsrfTokenValid('formation_tab_done_' . $formation->getId() . '_' . $tabKey, $token)) {
            return new Response('Invalid CSRF token', 419);
        }

        $askedDone = (bool)$request->request->get('done');

        $issues = $checker->getTabIssues($formation, $tabKey);
        $isComplete = count($issues) === 0;

        $state->setDone($askedDone && $isComplete);
        $state->setIssues($issues); //todo: sauvegardedes issues dans la BDD
        $state->setStatus($checker->computeStatus($isComplete, $state->isDone()));
        $em->flush();

        return $this->turboStreamsResponse($formation, $tabKey, $state->getStatus(), $state->isDone(), $issues);

    }


    private function turboStreamsResponse(Formation $formation, string $tabKey, string $status, bool $done, array $issues = []): Response
    {
        $response = $this->render('formation_v2/tabs/_autosave.stream.html.twig', [
            'formation' => $formation,
            'tabKey' => $tabKey,
            'status' => $status,
            'done' => $done,
            'issues' => $issues,
//            'form' => $form,
        ]);
        $response->headers->set('Content-Type', 'text/vnd.turbo-stream.html');
        return $response;
    }
}
