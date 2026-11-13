<?php
/*
 * Copyright (c) 2024. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Utils/Access.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 23/11/2024 10:35
 */

namespace App\Utils;

use App\Classes\GetDpeParcours;
use App\Entity\DpeParcours;
use App\Entity\FicheMatiere;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Enums\TypeModificationDpeEnum;

abstract class Access {
    public static function isAccessible(DpeParcours $dpeParcours, string $state = 'cfvu'): bool
    {
        if (!self::isEditable($dpeParcours)) {
            return false;
        }

        $places = array_keys($dpeParcours->getEtatValidation());
        $currentPlace = $places[0] ?? null;

        if ($state === 'cfvu') {
            return $currentPlace !== 'en_cours_redaction_ss_cfvu'
                && $dpeParcours->getEtatReconduction() !== TypeModificationDpeEnum::MODIFICATION_TEXTE;
        }

        return true;
    }

    public static function isAccessibleMention(Formation $formation, string $state): bool
    {
        if ($state === 'cfvu') {
            return $formation->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION ||
                $formation->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION_MCCC_TEXTE ||
                $formation->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION_MCCC ||
                $formation->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION_PARCOURS;
        }

        return $formation->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION_TEXTE;
    }

    public static function isOuvert(Parcours|Formation|DpeParcours|FicheMatiere $entity): bool
    {
        if ($entity instanceof FicheMatiere) {
            return $entity->isModifiable();
        }

        if ($entity instanceof DpeParcours) {
            $places = array_keys($entity->getEtatValidation());
            $currentPlace = $places[0] ?? null;
            if (in_array($currentPlace, ['en_cours_redaction', 'en_cours_redaction_ss_cfvu', 'tacite_reconduction'], true)) {
                return true;
            }

            return $entity->getEtatReconduction()?->isOuvert() ?? false;
        }

        if ($entity instanceof Parcours) {
            $dpeParcours = GetDpeParcours::getFromParcours($entity);
            if ($dpeParcours !== null) {
                return self::isOuvert($dpeParcours);
            }
        }

        if ($entity instanceof Formation) {
            if ($entity->getEtatReconduction() === null) {
                return true;
            }

            return $entity->getEtatReconduction()->isOuvert();
        }

        return false;
    }

    public static function isEditable(Parcours|DpeParcours|FicheMatiere|null $entity): bool
    {
        if ($entity instanceof FicheMatiere) {
            return $entity->isModifiable();
        }

        if ($entity instanceof Parcours) {
            $entity = GetDpeParcours::getFromParcours($entity);
        }

        if (!$entity instanceof DpeParcours || !self::isOuvert($entity)) {
            return false;
        }

        $places = array_keys($entity->getEtatValidation());
        $currentPlace = $places[0] ?? null;

        return in_array($currentPlace, [
            'en_cours_redaction',
            'en_cours_redaction_ss_cfvu',
            'soumis_dpe_composante_reserve_cfvu',
        ], true);
    }

    public static function isTabEditable(Parcours|DpeParcours|null $entity, string $tab): bool
    {
        if ($entity instanceof Parcours) {
            $entity = GetDpeParcours::getFromParcours($entity);
        }

        if (!$entity instanceof DpeParcours || !self::isEditable($entity)) {
            return false;
        }

        return match ($tab) {
            'maquette', 'semestre', 'annee', 'competences' => self::isAccessible($entity, 'cfvu'),
            'presentation', 'descriptif', 'admission', 'et_apres', 'configuration' => self::isAccessible($entity, 'texte'),
            default => false,
        };
    }
}
