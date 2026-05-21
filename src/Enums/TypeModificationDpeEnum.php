<?php
/*
 * Copyright (c) 2024. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Enums/TypeModificationDpeEnum.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 25/01/2024 16:31
 */

namespace App\Enums;

enum TypeModificationDpeEnum: string implements BadgeEnumInterface
{
    /*
     * Création
     * Non ouverture
     * Reconduite identique
     * modification MCCC
     * Reconduite avec modification des textes
     * Reconduite avec modification MCCC et des textes
     */
    case ATTENTE = 'ATTENTE';
    case CREATION = 'CREATION';
    case OUVERT = 'OUVERT';
    case NON_OUVERTURE = 'NON_OUVERTURE';
    case NON_OUVERTURE_SES = 'NON_OUVERTURE_SES';
    case NON_OUVERTURE_CFVU = 'NON_OUVERTURE_CFVU';
    case OUVERTURE_SES = 'OUVERTURE_SES';
    case OUVERTURE_CFVU = 'OUVERTURE_CFVU';
    case MODIFICATION = 'MODIFICATION';
    case MODIFICATION_PARCOURS = 'MODIFICATION_PARCOURS';
    case MODIFICATION_INTITULE = 'MODIFICATION_INTITULE';
    case MODIFICATION_MCCC = 'MODIFICATION_MCCC';
    case MODIFICATION_TEXTE = 'MODIFICATION_TEXTE';
    case MODIFICATION_MCCC_TEXTE = 'MODIFICATION_MCCC_TEXTE';
    case ANNULATION_REOUVERTURE = 'ANNULATION_REOUVERTURE';
    case FERMETURE_DEFINITIVE = 'FERMETURE_DEFINITIVE';

    case FORMATION_SOUMIS_SES = 'FORMATION_SOUMIS_SES';

    public function getLibelle(): string
    {
        return match ($this) {
            self::OUVERT => 'Ouvert',
            self::ATTENTE => 'Attente décision',
            self::CREATION => 'Création',
            self::NON_OUVERTURE => 'Non ouverture',
            self::NON_OUVERTURE_SES => 'Non ouverture, attente validation SES',
            self::NON_OUVERTURE_CFVU => 'Non ouverture, attente validation CFVU',
            self::OUVERTURE_SES => 'Ouverture, attente validation SES',
            self::OUVERTURE_CFVU => 'Ouverture, attente validation CFVU',
            self::MODIFICATION => 'modification(s) MCCC, Maquette ou textes',
            self::MODIFICATION_PARCOURS => 'modification des parcours (ajout, suppression)',
            self::MODIFICATION_INTITULE => 'modification intitulé parcours',
            self::MODIFICATION_MCCC => 'modification MCCC et Maquette et des textes',
            self::MODIFICATION_TEXTE => 'modification des textes',
            self::MODIFICATION_MCCC_TEXTE => 'modification MCCC, maquettes et des textes',
            self::ANNULATION_REOUVERTURE => 'Annulation de la réouverture',
            self::FORMATION_SOUMIS_SES => 'Formation soumise à la validation du SES',
            self::FERMETURE_DEFINITIVE => 'Fermeture definitive',
            default => 'Non défini',
        };
    }

    public function getBadgeVariant(): string
    {
        return match ($this) {
            self::OUVERT => 'success',
            self::ATTENTE => 'primary',
            self::CREATION, self::ANNULATION_REOUVERTURE => 'info',
            self::MODIFICATION, self::MODIFICATION_PARCOURS, self::MODIFICATION_INTITULE, self::MODIFICATION_MCCC, self::MODIFICATION_TEXTE, self::MODIFICATION_MCCC_TEXTE => 'warning',
            default => 'danger',
        };
    }

    /**
     * @deprecated Utiliser getBadgeVariant() et le composant Twig Badge.
     */
    public function getBadge(): string
    {
        @trigger_error(sprintf('%s::getBadge() is deprecated, use %s::getBadgeVariant() and the Twig Badge component instead.', self::class, self::class), E_USER_DEPRECATED);

        return $this->getBadgeVariant();
    }

    public static function listeEtatParcours(): array
    {
        return [
            self::OUVERT,
            self::CREATION,
            self::NON_OUVERTURE,
            self::NON_OUVERTURE_SES,
            self::NON_OUVERTURE_CFVU,
            self::OUVERTURE_SES,
            self::OUVERTURE_CFVU
        ];
    }
}
