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
use App\DTO\TranslatableKey;
use App\Entity\User;
use App\Form\BugReportType;
use App\Repository\FormationRepository;
use App\Repository\ParcoursRepository;
use App\Utils\TurboStreamResponseFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;

class BugReportController extends AbstractController
{
    #[Route('/bug-report/modal', name: 'app_bug_report_modal', methods: ['GET', 'POST'])]
    public function modal(
        TurboStreamResponseFactory $turboStreamResponseFactory,
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

        $form = $this->createForm(BugReportType::class, null, [
            'formation_id' => $context['formationId'],
            'parcours_id' => $context['parcoursId'],
            'page' => $context['page'],
            'action' => $this->generateUrl('app_bug_report_modal'),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $data = $form->getData();
                $title = $data['title'] ?? '';
                $message = $data['message'] ?? '';

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

                return $turboStreamResponseFactory->streamToastSuccess(
                    'Signalement envoyé. Merci pour votre retour.',
                    true
                );
            } catch (\Throwable $e) {

                return $turboStreamResponseFactory->streamToastError(
                    'Le signalement n\'a pas pu être envoyé. Merci de réessayer.',
                    true
                );
            }
        }

        return $turboStreamResponseFactory->streamOpenModalFromTemplates(
            new TranslatableKey('bug_report.titre'),
            new TranslatableKey('bug_report.description'),
            'bug_report/_modal.html.twig',
            [
                'form' => $form->createView(),
                'context' => $context,
                'user' => $user,
            ],
            '_ui/_footer_submit_cancel.html.twig',
        );
    }

    private function resolveContext(
        Request             $request,
        FormationRepository $formationRepository,
        ParcoursRepository  $parcoursRepository,
    ): array
    {
        $formation = null;
        $parcours = null;

        $formationId = $request->query->get('formation');
        $parcoursId = $request->query->get('parcours');

        if ($parcoursId !== null && $parcoursId !== '') {
            $parcours = $parcoursRepository->find((int)$parcoursId);
            $formation = $parcours?->getFormation();
        } elseif ($formationId !== null && $formationId !== '') {
            $formation = $formationRepository->find((int)$formationId);
        }

        $page = trim((string)$request->query->get('page', ''));
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

