<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Service/VolumeHoraireParcoursCalculator.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 07/04/2026 18:42
 */

namespace App\Service;

use App\DTO\HeuresEctsSemestre;
use App\DTO\StructureParcours;
use App\DTO\StructureSemestre;
use App\Entity\CampagneCollecte;
use App\Entity\Parcours;
use App\Entity\VolumeHoraireParcours;
use App\TypeDiplome\TypeDiplomeResolver;
use LogicException;

final readonly class VolumeHoraireParcoursCalculator
{
    public function __construct(private TypeDiplomeResolver $typeDiplomeResolver)
    {
    }

    public function calculate(
        Parcours               $parcours,
        ?CampagneCollecte      $campagneCollecte = null,
        ?VolumeHoraireParcours $volumeHoraireParcours = null,
    ): VolumeHoraireParcours
    {
        $formation = $parcours->getFormation();
        if ($formation === null) {
            throw new LogicException('Le parcours ne possède pas de formation.');
        }

        $typeDiplome = $this->typeDiplomeResolver->getFromFormation($formation);
        $structure = $typeDiplome->calculStructureParcours($parcours);
        $heuresFormation = $structure->heuresEctsFormation;

        $volumeHoraireParcours ??= new VolumeHoraireParcours();
        $volumeHoraireParcours->setParcours($parcours);
        if ($campagneCollecte !== null) {
            $volumeHoraireParcours->setCampagneCollecte($campagneCollecte);
        }

        $volumeHoraireParcours->setHeuresCmPres($heuresFormation->sommeFormationCmPres);
        $volumeHoraireParcours->setHeuresTdPres($heuresFormation->sommeFormationTdPres);
        $volumeHoraireParcours->setHeuresTpPres($heuresFormation->sommeFormationTpPres);
        $volumeHoraireParcours->setHeuresTePres($heuresFormation->sommeFormationTePres);
        $volumeHoraireParcours->setHeuresCmDist($heuresFormation->sommeFormationCmDist);
        $volumeHoraireParcours->setHeuresTdDist($heuresFormation->sommeFormationTdDist);
        $volumeHoraireParcours->setHeuresTpDist($heuresFormation->sommeFormationTpDist);
        $volumeHoraireParcours->setVolumesSemestre($this->buildSemestreVolumes($structure));
        $volumeHoraireParcours->setVolumesAnnee($this->buildAnneeVolumes($structure));
        $volumeHoraireParcours->setDateCalcul(new \DateTime());

        return $volumeHoraireParcours;
    }

    /**
     * @return array<int, array<string, float|int|string>>
     */
    private function buildSemestreVolumes(StructureParcours $structure): array
    {
        $volumes = [];

        foreach ($structure->semestres as $ordre => $semestre) {
            if (!$semestre instanceof StructureSemestre) {
                continue;
            }

            $volumes[(int)$ordre] = $this->serializeHeures(
                $semestre->heuresEctsSemestre,
                'S' . $semestre->ordre,
                $semestre->getAnnee(),
                $semestre->ordre,
            );
        }

        ksort($volumes, SORT_NUMERIC);

        return $volumes;
    }

    /**
     * @return array<string, float|int|string>
     */
    private function serializeHeures(HeuresEctsSemestre $heures, string $libelle, int $annee, int $semestre): array
    {
        return [
            'libelle' => $libelle,
            'annee' => $annee,
            'semestre' => $semestre,
            'cm_pres' => $heures->sommeSemestreCmPres,
            'td_pres' => $heures->sommeSemestreTdPres,
            'tp_pres' => $heures->sommeSemestreTpPres,
            'te_pres' => $heures->sommeSemestreTePres,
            'cm_dist' => $heures->sommeSemestreCmDist,
            'td_dist' => $heures->sommeSemestreTdDist,
            'tp_dist' => $heures->sommeSemestreTpDist,
            'total' => $heures->sommeSemestreTotalPresDist(),
            'ects' => $heures->sommeSemestreEcts,
            'totalCMMajore' => $heures->sommeSemestreCmPres * 1.5 + $heures->sommeSemestreTdPres + $heures->sommeSemestreTpPres,
            'totalDist' => $heures->sommeSemestreTotalDist(),
            'totalPres' => $heures->sommeSemestreTotalPres(),
        ];
    }

    /**
     * @return array<int, array<string, float|int|string>>
     */
    private function buildAnneeVolumes(StructureParcours $structure): array
    {
        $volumes = [];

        foreach ($structure->semestres as $semestre) {
            if (!$semestre instanceof StructureSemestre) {
                continue;
            }

            $annee = $semestre->getAnnee();
            if ($annee === 0) {
                continue;
            }

            if (!array_key_exists($annee, $volumes)) {
                $volumes[$annee] = [
                    'libelle' => 'Année ' . $annee,
                    'annee' => $annee,
                    'cm_pres' => 0.0,
                    'td_pres' => 0.0,
                    'tp_pres' => 0.0,
                    'te_pres' => 0.0,
                    'cm_dist' => 0.0,
                    'td_dist' => 0.0,
                    'tp_dist' => 0.0,
                    'total' => 0.0,
                    'ects' => 0.0,
                    'totalCMMajore' => 0.0,
                    'totalDist' => 0.0,
                    'totalPres' => 0.0,

                ];
            }

            $heures = $semestre->heuresEctsSemestre;
            $volumes[$annee]['cm_pres'] += $heures->sommeSemestreCmPres;
            $volumes[$annee]['td_pres'] += $heures->sommeSemestreTdPres;
            $volumes[$annee]['tp_pres'] += $heures->sommeSemestreTpPres;
            $volumes[$annee]['te_pres'] += $heures->sommeSemestreTePres;
            $volumes[$annee]['cm_dist'] += $heures->sommeSemestreCmDist;
            $volumes[$annee]['td_dist'] += $heures->sommeSemestreTdDist;
            $volumes[$annee]['tp_dist'] += $heures->sommeSemestreTpDist;
            $volumes[$annee]['total'] += $heures->sommeSemestreTotalPresDist();
            $volumes[$annee]['totalCMMajore'] += $volumes[$annee]['cm_pres'] * 1.5 + $volumes[$annee]['td_pres'] + $volumes[$annee]['tp_pres'];
            $volumes[$annee]['totalDist'] += $heures->sommeSemestreTotalDist();
            $volumes[$annee]['totalPres'] += $heures->sommeSemestreTotalPres();
            $volumes[$annee]['ects'] += $heures->sommeSemestreEcts;
        }

        ksort($volumes, SORT_NUMERIC);

        return $volumes;
    }
}
