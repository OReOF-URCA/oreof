<?php
/*
 * Copyright (c) 2024. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Classes/Json/ExtractTextFromJsonPatch.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 07/06/2024 10:21
 */

namespace App\Classes\Json;

use App\Repository\TypeEpreuveRepository;
use Swaggest\JsonDiff\JsonPointer;

abstract class ExtractTextFromJsonPatch
{
    private static string $key = '';
    private static TypeEpreuveRepository $typeEpreuveRepository;

    public function __construct(
        TypeEpreuveRepository $typeEpreuveRepository,
    ) {
        self::$typeEpreuveRepository = $typeEpreuveRepository;
    }

    public static function getNewValueFromPatch($patch): string
    {
        self::getKey($patch->path);

        if (!is_object($patch->value)) {
            return '"' . self::getField($patch->path) . '" : ' . self::getValue($patch->value);
        }

        return '"' . self::getField($patch->path) . '" : valeur objet...';

    }

    public static function getOriginalValueFromPatch($patch): string
    {
        self::getKey($patch->path);
        return self::getValue($patch->value) ?? 'pas de valeur';
    }

    public static function getTextFromPath($patch): string
    {
        return self::translatePath($patch->path);
    }

    public static function translatePath($path): string
    {
        // partir de cette chaine pour construire une chaine texte compréhensible

        $tab = JsonPointer::splitPath($path);

        if (count($tab) === 2 && $tab[0] === 'heuresEctsFormation') {
            return 'Total Formation';
        }

        if (count($tab) > 2) {
            $texte = 'Semestre ' . $tab[1];

            if ($tab[2] === 'ues') {
                $texte .= ', UE ' . $tab[3];
            }

            return $texte;
        }

        return $path;
    }

    private static function getField($path): string
    {
        $translateField = [
            'libelle' => 'Libellé',
            'cmPres' => 'Volume CM',
            'tdPres' => 'Volume TD',
            'tpPres' => 'Volume TP',
            'slug' => 'Slug',
            'sommeUeCmPres' => 'Total CM UE',
            'sommeUeTdPres' => 'Total TD UE',
            'sommeUeTpPres' => 'Total TP UE',
            'sommeUeEcts' => 'Total ECTS UE',
            'sommeSemestreCmPres' => 'Total CM Semestre',
            'sommeSemestreTdPres' => 'Total TD Semestre',
            'sommeSemestreTpPres' => 'Total TP Semestre',
            'sommeSemestreTePres' => 'Total TA Semestre',
            'sommeSemestreEcts' => 'Total ECTS Semestre',
            'sommeFormationCmPres' => 'Total CM Formation',
            'sommeFormationTdPres' => 'Total TD Formation',
            'sommeFormationTpPres' => 'Total TP Formation',
            'sommeFormationTePres' => 'Total TA Formation',
            'sommeFormationEcts' => 'Total ECTS Formation',
            'id' => 'id',
            'ects' => 'ECTS',
            'pourcentage' => 'Pourcentage',
            'controleContinu' => 'Contrôle Continu',
            'examenTerminal' => 'Examen Terminal',
            'numeroSession' => 'Numéro de session',
            'numeroEpreuve' => 'Numéro d\'épreuve',
            'typeMccc' => 'Type de MCCC',
            'libre' => 'Libre',
            'raccroche' => 'Raccroché',
            'nbEpreuves' => 'Nb épreuves',
            'code' => 'Code',
            'ordre' => 'Ordre',
            'sigle' => 'Sigle',
            'iut_portfolio' => 'Portfolio',
            'iut_livrable' => 'Livrable',
            'iut_rapport' => 'Rapport',
            'iut_soutenance' => 'Soutenance',
            'hors_iut_entreprise' => 'Entreprise',
            'hors_iut_rapport' => 'Rapport Entreprise',
            'tePres' => 'Travail en autonomie',
        ];
        if (array_key_exists(self::$key, $translateField)) {
            return $translateField[self::$key];
        }

        return '?field ' . self::$key;
    }

    public static function getLibelle(
        string $path,
        string  $jsonCourant
    ) {
        $tab = JsonPointer::splitPath($path);
        //selon la dernière valeur du tableau $tab, reconstruire un path avec les éléments précédents
        if (count($tab) > 2 &&
            ($tab[count($tab) - 1] === 'cmPres' ||
            $tab[count($tab) - 1] === 'tdPres' ||
            $tab[count($tab) - 1] === 'tpPres')
        ) {
            //retirer les 2 derniers éléments du tab
            array_pop($tab);
            array_pop($tab);

            if (count($tab) > 2 && $tab[count($tab) - 1] === 'heuresEctsEcEnfants') {
                //EC enfants
                array_pop($tab);
            }

            $elt = JsonPointer::get(json_decode($jsonCourant), $tab);
            if (!is_array($elt)) {
                return $elt->elementConstitutif->code;
            }
        }

        if (count($tab) >= 7 && $tab[count($tab) - 1] === 'typeMccc') {
            //EC enfants
            array_pop($tab);
            $elt = JsonPointer::get(json_decode($jsonCourant), $tab);
            if (!is_array($elt) && property_exists($elt->elementConstitutif, 'code')) {
                return $elt->elementConstitutif->code;
            }
        }

        if (count($tab) > 4 && $tab[count($tab) - 3] === 'mcccs') {
            //EC enfants
            array_pop($tab);
            array_pop($tab);
            array_pop($tab);
            $elt = JsonPointer::get(json_decode($jsonCourant), $tab);

            if (!is_array($elt) && property_exists($elt, 'code')) {
                return $elt->code;
            }
        }

        if (count($tab) > 4 && $tab[count($tab) - 2] === 'typeEpreuve') {
            array_pop($tab);
            array_pop($tab);
            $elt = JsonPointer::get(json_decode($jsonCourant), $tab);
            if (!is_array($elt) && property_exists($elt, 'libelle')) {
                return 'Type d\'épreuve : '.$elt->libelle.', Session : '.$elt->numeroSession.', % '.$elt->pourcentage;
            }
            return 'Type d\'épreuve';
        }

        return '';
    }

    public static function getLastItem(string $path): bool
    {
        $tab = JsonPointer::splitPath($path);

        return !(count($tab) > 1 && ($tab[count($tab) - 1] === 'id' || $tab[count($tab) - 1] === 'slug'));

    }

    private static function getValue($value): string
    {
        if (self::$key === 'libre' || self::$key === 'raccroche') {
            return (bool)$value === 1 ? 'Oui' : 'Non';
        }

        if (self::$key === 'typeMccc') {
            return match ($value) {
                'ct' => 'Contrôle Terminal',
                'cc' => 'Contrôle Continu',
                'cc_tp' => 'Contrôle Continu (EC TP)',
                'cci' => 'Contrôle Continu Intégral',
                'cc_ct' => 'Contrôle Continu + Contrôle Terminal',
                default => 'Erreur'
            };
        }

        if (self::$key === 'typeEpreuve') {
            $te = self::$typeEpreuveRepository->find($value);
            if ($te !== null) {
                return $te->getLibelle();
            }
        }

        return $value;
    }

    private static function getKey($path): void
    {
        $tab = JsonPointer::splitPath($path);

        self::$key = $tab[count($tab) - 1];
    }
}
