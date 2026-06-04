<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/BugReportController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 18/03/2026 21:12
 */

namespace App\Controller;

use App\Classes\JsonReponse;
use App\Classes\Mailer;
use App\Entity\User;
use App\Repository\FormationRepository;
use App\Repository\ParcoursRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;

class BugReportController extends AbstractController
{
    #[Route('/bug-report/modal', name: 'app_bug_report_modal', methods: ['GET', 'POST'])]
    public function modal(
        Request             $request,
        Mailer              $mailer,
        FormationRepository $formationRepository,
        ParcoursRepository  $parcoursRepository,
    ): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return JsonReponse::error('Vous devez être connecté pour signaler un bug.');
        }

        $context = $this->resolveContext($request, $formationRepository, $parcoursRepository);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('bug_report', (string)$request->request->get('_token'))) {
                return JsonReponse::error('Jeton CSRF invalide. Merci de recharger la page.');
            }

            $title = trim((string)$request->request->get('title'));
            $message = trim((string)$request->request->get('message'));

            if ($title === '') {
                return JsonReponse::error('Le titre est obligatoire.');
            }

            if ($message === '') {
                return JsonReponse::error('Le message est obligatoire.');
            }

            try {
                $mailer->initEmail();
                $mailer->setTemplate('mails/bug_report/report.html.twig', [
                    'reporter' => $user,
                    'title' => $title,
                    'message' => $message,
                    'context' => $context,
                    'submittedAt' => new \DateTimeImmutable(),
                ]);
                $mailer->sendMessage(
                    [new Address(Mailer::MAIL_GENERIC, 'ORéOF')],
                    sprintf('[ORéOF][Bug] %s', $title),
                    [
                        'replyTo' => $user->getEmail(),
                    ]
                );

                return JsonReponse::success('Signalement envoyé. Merci pour votre retour.');
            } catch (\Throwable) {
                return JsonReponse::error('Le signalement n\'a pas pu être envoyé. Merci de réessayer.');
            }
        }

        return $this->render('bug_report/_modal.html.twig', [
            'context' => $context,
            'user' => $user,
        ]);
    }

    private function resolveContext(
        Request             $request,
        FormationRepository $formationRepository,
        ParcoursRepository  $parcoursRepository,
    ): array
    {
        $formation = null;
        $parcours = null;

        $formationId = $request->get('formation');
        $parcoursId = $request->get('parcours');

        if ($parcoursId !== null && $parcoursId !== '') {
            $parcours = $parcoursRepository->find((int)$parcoursId);
            $formation = $parcours?->getFormation();
        } elseif ($formationId !== null && $formationId !== '') {
            $formation = $formationRepository->find((int)$formationId);
        }

        $page = trim((string)$request->get('page', ''));
        if ($page === '') {
            $page = (string)($request->headers->get('referer') ?? '');
        }

        if (mb_strlen($page) > 2000) {
            $page = mb_substr($page, 0, 2000);
        }

        return [
            'formationId' => $formation?->getId(),
            'formationLabel' => $formation?->getDisplayLong() ?? 'Non définie',
            'parcoursId' => $parcours?->getId(),
            'parcoursLabel' => $parcours?->getDisplay() ?? 'Non défini',
            'page' => $page,
        ];
    }
}

