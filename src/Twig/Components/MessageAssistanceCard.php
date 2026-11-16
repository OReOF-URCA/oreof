<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\MessageAssistance;
use App\Service\Assistance\LienAutomatique;
use App\Service\Assistance\SeparateurCitation;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Un message du fil d'une demande d'assistance (reçu, réponse envoyée ou note interne).
 * L'historique cité par le mail est replié et les adresses https sont rendues cliquables. Corps et historique sont
 * rendus en HTML prêt à afficher : repassés par le sanitizer « assistance_sanitizer » (le découpage peut couper des
 * balises), ou texte brut échappé.
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
        private readonly LienAutomatique $lien,
        #[Target('assistance_sanitizer')]
        private readonly HtmlSanitizerInterface $sanitizer,
    ) {
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
            [$corps, $historique] = $this->separateur->separerTexte($this->message->getCorpsTexte());

            return $this->parties = [$this->texteAffichable($corps), $historique !== null ? $this->texteAffichable($historique) : null];
        }

        [$corps, $historique] = $this->separateur->separerHtml($html);

        return $this->parties = [$this->htmlAffichable($corps), $historique !== null ? $this->htmlAffichable($historique) : null];
    }

    /**
     * Adresses rendues cliquables, puis nettoyage (le découpage a pu couper des balises ; target/rel imposés sur les liens).
     */
    private function htmlAffichable(string $html): string
    {
        return $this->sanitizer->sanitize($this->lien->html($html));
    }

    private function texteAffichable(string $texte): string
    {
        return $this->sanitizer->sanitize(nl2br($this->lien->texte($texte), false));
    }
}
