<?php

namespace App\Service\Assistance\Source;

use App\DTO\Assistance\MailEntrant;
use App\DTO\Assistance\PieceJointeEntrante;
use DateTimeImmutable;
use DateTimeInterface;
use Webklex\PHPIMAP\Address;
use Webklex\PHPIMAP\Attribute;
use Webklex\PHPIMAP\Message;

/**
 * Convertit un message webklex/php-imap en MailEntrant (utilisé par les sources IMAP et .eml).
 */
class MailEntrantFactory
{
    public function depuisMessage(Message $message): MailEntrant
    {
        $expediteur = $this->adresses($message->get('from'))[0] ?? null;
        $expediteurEmail = mb_strtolower((string) ($expediteur->mail ?? ''));

        $html = trim($message->getHTMLBody());
        $texte = trim($message->getTextBody());
        if ($texte === '' && $html !== '') {
            $texte = $this->texteDepuisHtml($html);
        }

        $destinataires = [];
        foreach (['to', 'cc'] as $champ) {
            foreach ($this->adresses($message->get($champ)) as $adresse) {
                $email = mb_strtolower((string) $adresse->mail);
                if ($email !== '' && !in_array($email, $destinataires, true)) {
                    $destinataires[] = $email;
                }
            }
        }

        $piecesJointes = [];
        foreach ($message->getAttachments() as $piece) {
            $piecesJointes[] = new PieceJointeEntrante(
                $this->decoder((string) ($piece->getName() ?: 'piece-jointe')),
                (string) ($piece->getMimeType() ?: 'application/octet-stream'),
                (string) $piece->getContent(),
            );
        }

        $sujet = trim($this->decoder($this->premiereValeur($message->get('subject')) ?? ''));

        $autoSubmitted = mb_strtolower(trim($this->premiereValeur($message->get('auto_submitted')) ?? ''));
        $automatique = ($autoSubmitted !== '' && $autoSubmitted !== 'no')
            || str_starts_with($expediteurEmail, 'mailer-daemon@')
            || str_starts_with($expediteurEmail, 'postmaster@')
            // notification envoyée par l'application (« [ORéOF] … ») ; restent à traiter une réponse « Re: [ORéOF] … »
            // et un signalement de bug « [ORéOF][Bug] … » envoyé depuis le formulaire de l'application
            || preg_match('/^\[OR[ée]OF\](?!\s*\[Bug\])/iu', $sujet) === 1;

        return new MailEntrant(
            messageId: $this->ids($message->get('message_id'))[0] ?? null,
            inReplyTo: $this->ids($message->get('in_reply_to'))[0] ?? null,
            references: $this->ids($message->get('references')),
            expediteurEmail: $expediteurEmail,
            expediteurNom: $this->nom($expediteur),
            destinataires: $destinataires,
            sujet: $sujet,
            corpsTexte: $texte,
            corpsHtml: $html !== '' ? $html : null,
            date: $this->date($message->get('date')),
            automatique: $automatique,
            piecesJointes: $piecesJointes,
        );
    }

    /**
     * Message-ID normalisé « <id> » d'un message, lisible sans télécharger le corps.
     */
    public function messageId(Message $message): ?string
    {
        return $this->ids($message->get('message_id'))[0] ?? null;
    }

    /**
     * @return list<Address>
     */
    private function adresses(mixed $attribut): array
    {
        $valeurs = $attribut instanceof Attribute ? $attribut->toArray() : [];

        return array_values(array_filter($valeurs, static fn ($v) => $v instanceof Address));
    }

    private function premiereValeur(mixed $attribut): ?string
    {
        if (!$attribut instanceof Attribute) {
            return null;
        }
        $valeur = $attribut->first();

        return is_scalar($valeur) ? (string) $valeur : null;
    }

    /**
     * Identifiants de mail normalisés « <id> », sans doublon.
     *
     * @return list<string>
     */
    private function ids(mixed $attribut): array
    {
        if (!$attribut instanceof Attribute) {
            return [];
        }
        $ids = [];
        foreach ($attribut->toArray() as $valeur) {
            if (!is_scalar($valeur)) {
                continue;
            }
            foreach (preg_split('/[\s,]+/', (string) $valeur) ?: [] as $brut) {
                $id = trim($brut, " \t<>");
                if ($id !== '' && !in_array('<' . $id . '>', $ids, true)) {
                    $ids[] = '<' . $id . '>';
                }
            }
        }

        return $ids;
    }

    private function nom(?Address $adresse): ?string
    {
        if ($adresse === null) {
            return null;
        }
        $nom = trim($this->decoder((string) $adresse->personal), " \t\"'");

        return $nom !== '' ? $nom : null;
    }

    private function date(mixed $attribut): DateTimeImmutable
    {
        $valeur = $attribut instanceof Attribute ? $attribut->first() : null;

        return $valeur instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($valeur)
            : new DateTimeImmutable();
    }

    private function decoder(string $valeur): string
    {
        if (!str_contains($valeur, '=?')) {
            return $valeur;
        }

        return mb_decode_mimeheader($valeur);
    }

    private function texteDepuisHtml(string $html): string
    {
        $html = (string) preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html);
        $html = (string) preg_replace('#<(br|/p|/div|/li|/tr|/h\d)\b[^>]*>#i', "\n", $html);
        $texte = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace("/[ \t]+/", ' ', $texte));
    }
}
