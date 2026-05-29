<?php

namespace App\Controller;

use App\Classes\JsonReponse;
use App\Classes\Mailer;
use App\Repository\FormationRepository;
use App\Repository\ParcoursRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;

class SesContactController extends AbstractController
{
    public function __construct(
        protected UserRepository $userRepository,
    ) {
    }

    #[Route('/ses/contact', name: 'app_ses_contact')]
    public function index(
        Mailer              $mailer,
        FormationRepository $formationRepository,
        ParcoursRepository  $parcoursRepository,
        Request             $request,
    ): Response {
        if ($request->query->has('formation') && $request->query->get('formation') !== '') {
            $formation = $formationRepository->find($request->query->get('formation'));
            $parcours = null;
        }

        if ($request->query->has('parcours') && $request->query->get('parcours') !== '') {
            $parcours = $parcoursRepository->find($request->query->get('parcours'));
            $formation = $parcours?->getFormation();
        }

        if ($request->isMethod('POST')) {
            $mailer->initEmail();
            $mailer->setTemplate('mails/ses_contact.html.twig', [
                'expediteur' => $this->getUser(),
                'formation' => $formation ?? null,
                'parcours' => $parcours ?? null,
                'message' => $request->request->get('message'),
            ]);
            $mailer->sendMessage(
                $this->getUsers($request->request->all()['destinataires']),
                '[ORéOF] ' . $request->request->get('subject'),
                [
                    'cc' => $this->getUsers($request->request->all()['replyTo'] ?? []),
                ]
            );

            $mailer->initEmail();
            $mailer->setTemplate('mails/ses_contact_copie.html.twig', [
                'message' => $request->request->get('message'),
                'destinataires' => $this->getUsers($request->request->all()['destinataires']),
                'cc' => $this->getUsers($request->request->all()['replyTo'] ?? []),
                'formation' => $formation ?? null,
                'parcours' => $parcours ?? null,
                'expediteur' => $this->getUser(),
            ]);
            $mailer->sendMessage(
                [new Address(Mailer::MAIL_GENERIC, 'ORéOF')],
                '[ORéOF - Copie] ' . $request->request->get('subject'),
            );

            return JsonReponse::success('Message envoyé !');
        }

        return $this->render('ses_contact/_index.html.twig', [
            'users' => $this->userRepository->findAll(),
            'formation' => $formation ?? null,
            'parcours' => $parcours ?? null,
        ]);
    }

    private function getUsers(
        array $idUsers
    ): array {
        $users = $this->userRepository->findBy(['id' => $idUsers]);
        $addresses = [];

        foreach ($users as $user) {
            $addresses[] = new Address($user->getEmail(), $user->getDisplay());
        }

        return $addresses;
    }
}
