<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/TypeDiplome/TypeDiplomeBccInterface.php
 * @author davidannebicque
 * @project oreofv2
 */

namespace App\TypeDiplome;

use App\Entity\Parcours;

interface TypeDiplomeBccInterface
{
    /**
     * Calcule et retourne les données nécessaires pour l'affichage du tableau croisé compétences/EC.
     * Si $semestre est spécifié, calcule prioritairement les données pour ce semestre.
     *
     * @param Parcours $parcours
     * @param int|null $semestre
     * @return array<string, mixed>
     */
    public function getBccCroiseData(Parcours $parcours, ?int $semestre = null): array;

    /**
     * Retourne le chemin du template Twig à utiliser pour le tableau croisé complet.
     *
     * @return string
     */
    public function getBccCroiseTemplate(): string;

    /**
     * Retourne le chemin du template Twig pour le rendu d'un semestre individuel en lazy load.
     *
     * @return string
     */
    public function getBccCroiseSemestreTemplate(): string;
}
