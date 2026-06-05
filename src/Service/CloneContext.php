<?php

namespace App\Service;

use App\Entity\CampagneCollecte;
use App\Entity\Parcours;

/**
 * Contexte d'une copie structurelle : la cible et les tables de correspondance
 * « entité d'origine (n'importe quelle génération) → entité de l'année cible ».
 * Permet de relinker les compétences / apprentissages critiques d'une fiche
 * clonée vers les entités de l'année cible, quelle que soit la distance dans
 * la lignée entre source et cible.
 */
class CloneContext
{
    public Parcours $parcoursCible;
    public ?CampagneCollecte $campagne = null;

    /** @var array<int, \App\Entity\Competence> idCompetenceOrigine → compétence cible */
    public array $compMap = [];

    /** @var array<int, \App\Entity\ButApprentissageCritique> idAppCritOrigine → app. critique cible */
    public array $appCritMap = [];
}