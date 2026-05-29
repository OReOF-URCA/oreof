<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/TypeDiplome/But/Services/AbstractButMccc.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 15/06/2025 14:07
 */

namespace App\TypeDiplome\But\Services;

class AbstractButMccc
{
// Pages
    public const PAGE_MODELE = 'modele';
    // Cellules
    public const CEL_DOMAINE = 'K1';
    public const CEL_INTITULE_FORMATION = 'K4';
    public const CEL_INTITULE_PARCOURS = 'K5';
    public const CEL_SEMESTRE_ETUDE = 'K7';
    public const CEL_COMPOSANTE = 'K2';
    public const CEL_SITE_FORMATION = 'K3';
    public const CEL_ANNEE_UNIVERSITAIRE = 'K6';
    public const CEL_REGIME_FI = 'E9';
    public const CEL_REGIME_FC = 'E11';
    public const CEL_REGIME_FI_APPRENTISSAGE = 'E13';
    public const CEL_REGIME_FC_CONTRAT_PRO = 'E15';
    public const CEL_PARCOURS_ECTS = 'AF26';
    public const CEL_PARCOURS = 'A26';

    //Colonnes sur Modèles

    public const COL_CODE_ELEMENT = 1;
    public const COL_CODE_EC = 2;
    public const COL_INTITULE = 3;
    public const COL_VOL_ETUDIANT = 4;
    public const COL_CM = 5;
    public const COL_TD = 6;
    public const COL_TP = 7;
    public const COL_HEURE_AUTONOMIE = 8;
    public const COL_FIRST_UE = 39;
    public const CEL_DATE_CONSEIL = 'N32';
    public const CEL_DATE_CFVU = 'N34';
}
