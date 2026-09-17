<?php

namespace App\MessageHandler;

use App\Classes\Mailer;
use App\Message\ChangeRfWorkflowNotification;
use App\Repository\ChangeRfRepository;
use DateTimeImmutable;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use UnexpectedValueException;

#[AsMessageHandler(fromTransport: 'async_export')]
readonly class ChangeRfWorkflowNotificationHandler
{
    private const EMAIL_CENTRAL = 'cfvu-secretariat@univ-reims.fr';
    private const EMAIL_OREOF = 'oreof@univ-reims.fr';

    public function __construct(
        private ChangeRfRepository $changeRfRepository,
        private Mailer $mailer,
    ) {
    }

    public function __invoke(ChangeRfWorkflowNotification $message): void
    {
        $demande = $this->changeRfRepository->find($message->getChangeRfId());
        if (null === $demande || null === $demande->getFormation()) {
            return;
        }

        $formation = $demande->getFormation();
        $responsableDpe = $formation->getComposantePorteuse()?->getResponsableDpe();
        if (null === $responsableDpe) {
            return;
        }

        $data = [
            'formation' => $formation,
            'responsableDpe' => $responsableDpe,
            'demande' => $demande,
        ];

        [$template, $recipients, $subject, $extraData] = match ($message->getTransition()) {
            'valider_conseil' => [
                'mails/workflow/changerf/valider_conseil.html.twig',
                [self::EMAIL_OREOF, self::EMAIL_CENTRAL],
                '[ORéOF]  Un changement de responsable de formation a été soumis',
                [],
            ],
            'valider_ses' => [
                'mails/workflow/changerf/valider_ses.html.twig',
                [$responsableDpe->getEmail()],
                '[ORéOF]  Un changement de responsable de formation a été validé par le SES',
                [],
            ],
            'reserver_ses' => [
                'mails/workflow/changerf/reserver_ses.html.twig',
                [$responsableDpe->getEmail()],
                '[ORéOF]  Des réserves ont été émises sur un changement de responsable de formation',
                ['motif' => $message->getMotif()],
            ],
            'valider_cfvu_avec_pv' => [
                'mails/workflow/changerf/valider_cfvu_avec_pv.html.twig',
                [$responsableDpe->getEmail(), $formation->getResponsableMention()?->getEmail()],
                '[ORéOF]  Un changement de responsable de formation a été validé par la CFVU',
                ['dateCfvu' => $this->getDate($message)],
            ],
            'valider_cfvu_attente_pv' => [
                'mails/workflow/changerf/valider_cfvu_attente_pv.html.twig',
                [$responsableDpe->getEmail(), $formation->getResponsableMention()?->getEmail()],
                '[ORéOF]  Un changement de responsable de formation a été validé par la CFVU',
                ['dateCfvu' => $this->getDate($message)],
            ],
            'reserver_cfvu' => [
                'mails/workflow/changerf/reserver_cfvu.html.twig',
                [$responsableDpe->getEmail()],
                '[ORéOF]  Des réserves ont été émises sur un changement de responsable de formation',
                ['motif' => $message->getMotif(), 'dateCfvu' => $this->getDate($message)],
            ],
            'deposer_pv' => [
                'mails/workflow/changerf/deposer_pv.html.twig',
                [self::EMAIL_OREOF, self::EMAIL_CENTRAL],
                '[ORéOF]  Un PV a été déposé pour un changement de responsable de formation',
                [],
            ],
            default => throw new UnexpectedValueException(sprintf(
                'La transition ChangeRf "%s" ne possède pas de notification.',
                $message->getTransition(),
            )),
        };

        $this->mailer->initEmail();
        $this->mailer->setTemplate($template, array_merge($data, $extraData));
        $this->mailer->sendMessage($recipients, $subject);
    }

    private function getDate(ChangeRfWorkflowNotification $message): ?DateTimeImmutable
    {
        return null === $message->getDate() ? null : new DateTimeImmutable($message->getDate());
    }
}
