<?php

namespace App\DTO\Assistance;

use DateTimeImmutable;

/**
 * Mail lu dans une source (IMAP ou fichier .eml), indépendant de la bibliothèque de lecture.
 * Les identifiants de mail sont au format « <id@domaine> ».
 */
final readonly class MailEntrant
{
    /**
     * @param list<string>              $references
     * @param list<string>              $destinataires  destinataires et copies, en minuscules
     * @param list<PieceJointeEntrante> $piecesJointes
     */
    public function __construct(
        public ?string $messageId,
        public ?string $inReplyTo,
        public array $references,
        public string $expediteurEmail,
        public ?string $expediteurNom,
        public array $destinataires,
        public string $sujet,
        public string $corpsTexte,
        public ?string $corpsHtml,
        public DateTimeImmutable $date,
        public bool $automatique,
        public array $piecesJointes = [],
    ) {
    }
}
