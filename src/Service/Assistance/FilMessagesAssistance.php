<?php

namespace App\Service\Assistance;

use App\Entity\MessageAssistance;

/**
 * Range les messages d'une demande en arbre (« qui répond à qui », d'après In-Reply-To), façon Thunderbird.
 *
 * Un message dont le parent n'est pas dans la demande (ou sans In-Reply-To, notes internes comprises) est une racine.
 * Racines et réponses sont triées par date. Un fil mal formé (messages qui se répondent mutuellement) n'empêche pas
 * l'affichage : chaque message apparaît exactement une fois.
 */
class FilMessagesAssistance
{
    /** Au-delà, le décalage n'augmente plus (un long échange ne déborde pas). */
    public const PROFONDEUR_MAX = 3;

    public function __construct(private readonly SeparateurCitation $separateur)
    {
    }

    /**
     * @param iterable<MessageAssistance> $messages
     *
     * @return list<array{message: MessageAssistance, profondeur: int}> ordre de lecture (parcours en profondeur)
     */
    public function arbre(iterable $messages): array
    {
        $liste = [...$messages];
        usort($liste, static fn (MessageAssistance $a, MessageAssistance $b) => $a->getDateEnvoi() <=> $b->getDateEnvoi());

        $parId = [];
        foreach ($liste as $message) {
            if ($message->getMessageId() !== null) {
                $parId[$message->getMessageId()] ??= $message;
            }
        }

        $racines = [];
        $enfants = [];
        foreach ($liste as $message) {
            $parent = $parId[$message->getInReplyTo() ?? ''] ?? null;
            if ($parent === null || $parent === $message) {
                $racines[] = $message;
            } else {
                $enfants[spl_object_id($parent)][] = $message;
            }
        }

        $arbre = [];
        $vus = [];
        $parcourir = function (MessageAssistance $message, int $profondeur) use (&$parcourir, &$arbre, &$vus, $enfants): void {
            if (isset($vus[spl_object_id($message)])) {
                return;
            }
            $vus[spl_object_id($message)] = true;
            $arbre[] = ['message' => $message, 'profondeur' => min($profondeur, self::PROFONDEUR_MAX)];
            foreach ($enfants[spl_object_id($message)] ?? [] as $enfant) {
                $parcourir($enfant, $profondeur + 1);
            }
        };

        foreach ($racines as $racine) {
            $parcourir($racine, 0);
        }
        // messages pris dans une boucle de réponses : jamais atteints depuis une racine
        foreach ($liste as $message) {
            $parcourir($message, 0);
        }

        return $arbre;
    }

    /**
     * Début du texte propre du message (sans l'historique cité), sur une ligne.
     */
    public function extrait(MessageAssistance $message, int $longueur = 100): string
    {
        $texte = trim((string) preg_replace('/\s+/u', ' ', $this->separateur->separerTexte($message->getCorpsTexte())[0]));

        return mb_strlen($texte) > $longueur ? mb_substr($texte, 0, $longueur) . '…' : $texte;
    }
}
