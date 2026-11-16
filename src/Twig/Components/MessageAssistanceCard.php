<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\MessageAssistance;
use App\Service\Assistance\SeparateurCitation;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Un message du fil d'une demande d'assistance (reçu, réponse envoyée ou note interne).
 * L'historique cité par le mail est replié ; les deux parties du HTML repassent par le sanitizer
 * « assistance_sanitizer » (le découpage peut couper des balises).
 *
 * <twig:MessageAssistanceCard :message="message" />
 */
#[AsTwigComponent('MessageAssistanceCard', template: 'components/message_assistance_card.html.twig')]
final class MessageAssistanceCard
{
    /** Nombre de destinataires affichés avant « et N autres ». */
    public const DESTINATAIRES_VISIBLES = 5;

    public MessageAssistance $message;

    /** @var array{0: string, 1: ?string}|null */
    private ?array $parties = null;

    public function __construct(
        private readonly SeparateurCitation $separateur,
        #[Target('assistance_sanitizer')]
        private readonly HtmlSanitizerInterface $sanitizer,
    ) {
    }

    public function estHtml(): bool
    {
        return $this->message->getCorpsHtml() !== null;
    }

    public function getCorps(): string
    {
        return $this->parties()[0];
    }

    public function getHistorique(): ?string
    {
        return $this->parties()[1];
    }

    /**
     * @return list<string>
     */
    public function getDestinatairesVisibles(): array
    {
        return array_slice($this->message->getDestinataires(), 0, self::DESTINATAIRES_VISIBLES);
    }

    /**
     * @return list<string>
     */
    public function getDestinatairesMasques(): array
    {
        return array_slice($this->message->getDestinataires(), self::DESTINATAIRES_VISIBLES);
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function parties(): array
    {
        if ($this->parties !== null) {
            return $this->parties;
        }

        $html = $this->message->getCorpsHtml();
        if ($html === null) {
            return $this->parties = $this->separateur->separerTexte($this->message->getCorpsTexte());
        }

        [$corps, $historique] = $this->separateur->separerHtml($html);
        if ($historique === null) {
            return $this->parties = [$html, null];
        }

        return $this->parties = [$this->sanitizer->sanitize($corps), $this->sanitizer->sanitize($historique)];
    }
}
