<?php

namespace App\MessageHandler;

use App\Classes\Mailer;
use App\Message\WorkflowEmailNotification;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler(fromTransport: 'async_export')]
readonly class WorkflowEmailNotificationHandler
{
    public function __construct(private MailerInterface $mailer)
    {
    }

    public function __invoke(WorkflowEmailNotification $message): void
    {
        $email = (new Email())
            ->from(Mailer::MAIL_GENERIC)
            ->replyTo(Mailer::MAIL_GENERIC)
            ->to(...$message->getRecipients())
            ->subject($message->getSubject())
            ->html($message->getHtml());

        $this->mailer->send($email);
    }
}
