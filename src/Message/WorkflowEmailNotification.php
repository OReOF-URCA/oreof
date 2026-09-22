<?php

namespace App\Message;

readonly class WorkflowEmailNotification
{
    /**
     * @param list<string> $recipients
     */
    public function __construct(
        private array $recipients,
        private string $subject,
        private string $html,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getRecipients(): array
    {
        return $this->recipients;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getHtml(): string
    {
        return $this->html;
    }
}
