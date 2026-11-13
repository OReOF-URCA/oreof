<?php

namespace App\Enums;

enum StatutDemandeAssistanceEnum: string implements BadgeEnumInterface
{
    case NOUVELLE = 'nouvelle';
    case EN_COURS = 'en_cours';
    case EN_ATTENTE_USAGER = 'en_attente_usager';
    case RESOLUE = 'resolue';

    public function getLibelle(): string
    {
        return match ($this) {
            self::NOUVELLE => 'Nouvelle',
            self::EN_COURS => 'En cours',
            self::EN_ATTENTE_USAGER => 'En attente de l\'usager',
            self::RESOLUE => 'Résolue',
        };
    }

    public function getBadgeVariant(): string
    {
        return match ($this) {
            self::NOUVELLE => 'danger',
            self::EN_COURS => 'warning',
            self::EN_ATTENTE_USAGER => 'info',
            self::RESOLUE => 'success',
        };
    }

    public function estOuverte(): bool
    {
        return $this !== self::RESOLUE;
    }
}
