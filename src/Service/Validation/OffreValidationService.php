<?php

declare(strict_types=1);

namespace App\Service\Validation;

use App\Entity\CampagneCollecte;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Enums\TypeModificationDpeEnum;

final class OffreValidationService
{
    /**
     * @return array<int, array{message: string}>
     */
    public function getAnomaliesFormation(Formation $formation, CampagneCollecte $campagne): array
    {
        $anomalies = [];
        foreach ($this->getAnomaliesMessagesFormation($formation, $campagne) as $msg) {
            $anomalies[] = ['message' => $msg];
        }
        return $anomalies;
    }

    /**
     * @return array<int, string>
     */
    public function getAnomaliesMessagesFormation(Formation $formation, CampagneCollecte $campagne): array
    {
        $anomalies = [];
        foreach ($formation->getParcours() as $parcours) {
            $anomalies = array_merge($anomalies, $this->getAnomaliesParcours($parcours, $campagne));
        }
        return $anomalies;
    }

    /**
     * @return array<int, string>
     */
    public function getAnomaliesParcours(Parcours $parcours, CampagneCollecte $campagne): array
    {
        $anomalies = [];
        
        // Check if parcours is open for this campaign
        $isOuvert = false;
        foreach ($parcours->getDpeParcours() as $d) {
            if ($d->getCampagneCollecte() === $campagne) {
                $isOuvert = ($d->getEtatReconduction() === TypeModificationDpeEnum::OUVERT);
                break;
            }
        }

        if ($isOuvert) {
            $hasOpenAnnee = false;
            foreach ($parcours->getAnnees() as $annee) {
                if ($annee->isOuvert() === true) {
                    $hasOpenAnnee = true;
                    
                    // Anomalie 1: Capacité globale nulle ou non renseignée
                    if ($annee->getCapaciteAccueil() <= 0) {
                        $anomalies[] = sprintf(
                            "Le parcours \"%s\" (Année %d) est ouvert mais sa capacité globale est nulle ou non renseignée.",
                            $parcours->getLibelle(),
                            $annee->getOrdre()
                        );
                    }

                    // Parcourir les plateformes actives
                    foreach ($annee->getAdmissionPlateformeParametres() as $param) {
                        if ($param->getCampagne() === $campagne && $param->isActive()) {
                            // Anomalie 2: Plateforme active sans aucune capacité renseignée
                            if (($param->getCapaciteGlobale() === null || $param->getCapaciteGlobale() <= 0) &&
                                ($param->getCapaciteFi() === null || $param->getCapaciteFi() <= 0) &&
                                ($param->getCapaciteAlternance() === null || $param->getCapaciteAlternance() <= 0)) {
                                
                                $anomalies[] = sprintf(
                                    "Le parcours \"%s\" (Année %d) a la plateforme %s active, mais aucune capacité n'est renseignée.",
                                    $parcours->getLibelle(),
                                    $annee->getOrdre(),
                                    $param->getPlateforme()?->getLibelle()
                                );
                            }
                        }
                    }
                }
            }

            // Anomalie 3: Parcours ouvert mais aucune année n'est ouverte
            if (!$hasOpenAnnee) {
                $anomalies[] = sprintf(
                    "Le parcours \"%s\" est ouvert, mais toutes ses années sont fermées.",
                    $parcours->getLibelle()
                );
            }
        }

        return $anomalies;
    }
}
