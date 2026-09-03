<?php

namespace App\Enums;

enum TimelineDateFlagEnum: string
{
    case NONE = 'none';
    case OUVERTURE_COLLECTE = 'ouverture_collecte';
    case CLOTURE_COLLECTE = 'cloture_collecte';
    case CFVU = 'cfvu';
    case TRANSMISSION_SES = 'transmission_ses';
    case PUBLICATION = 'publication';
}
