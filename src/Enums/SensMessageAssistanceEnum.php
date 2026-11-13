<?php

namespace App\Enums;

enum SensMessageAssistanceEnum: string
{
    case ENTRANT = 'entrant';
    case SORTANT = 'sortant';
    case NOTE = 'note';

    public function getLibelle(): string
    {
        return match ($this) {
            self::ENTRANT => 'Message reçu',
            self::SORTANT => 'Réponse envoyée',
            self::NOTE => 'Note interne',
        };
    }
}
