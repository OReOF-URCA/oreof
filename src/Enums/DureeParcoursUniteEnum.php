<?php

namespace App\Enums;

enum DureeParcoursUniteEnum : string

{
    case HEURES = 'heures';
    case JOURS = 'jours';
    case SEMAINES = 'semaines';
    case MOIS = 'mois';
    case SEMESTRES = 'semestres';
    case ANNEES = 'annees';

    public function libelle(): string
    {
        return match($this) {
            self::HEURES => 'Heure(s)',
            self::JOURS => 'Jour(s)',
            self::SEMAINES => 'Semaine(s)',
            self::MOIS => 'Mois',
            self::SEMESTRES => 'Semestre(s)',
            self::ANNEES => 'Année(s)',
        };
    }
}