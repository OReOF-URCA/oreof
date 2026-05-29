<?php

namespace App\Enums;

enum CampagnePublicationTagEnum : string {

    case ANNEE_COURANTE = 'annee_courante';
    case ANNEE_SUIVANTE = 'annee_suivante';
    case ANNEE_ARCHIVEE = 'annee_archivee';
}