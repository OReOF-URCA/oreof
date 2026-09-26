<?php

declare(strict_types=1);

namespace App\Service\Validation;

use App\Entity\CampagneCollecte;
use App\Entity\DpeParcours;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\PlateformeAdmissionParametre;
use App\Enums\TypeModificationDpeEnum;

final class OffreValidationService
{
    /**
     * @param array<int, DpeParcours>|null $dpeParcoursList
     * @param array<int, list<PlateformeAdmissionParametre>>|null $paramsByAnnee
     * @return array<int, array{message: string}>
     */
    public function getAnomaliesFormation(
        Formation $formation,
        CampagneCollecte $campagne,
        ?array $dpeParcoursList = null,
        ?array $paramsByAnnee = null
    ): array {
        $anomalies = [];
        foreach ($this->getAnomaliesMessagesFormation($formation, $campagne, $dpeParcoursList, $paramsByAnnee) as $msg) {
            $anomalies[] = ['message' => $msg];
        }
        return $anomalies;
    }

    /**
     * @param array<int, DpeParcours>|null $dpeParcoursList
     * @param array<int, list<PlateformeAdmissionParametre>>|null $paramsByAnnee
     * @return array<int, string>
     */
    public function getAnomaliesMessagesFormation(
        Formation $formation,
        CampagneCollecte $campagne,
        ?array $dpeParcoursList = null,
        ?array $paramsByAnnee = null
    ): array {
        $anomalies = [];
        $dpeMap = [];
        if ($dpeParcoursList !== null) {
            foreach ($dpeParcoursList as $dp) {
                if ($dp->getParcours() !== null) {
                    $dpeMap[$dp->getParcours()->getId()] = $dp;
                }
            }
        }

        foreach ($formation->getParcours() as $parcours) {
            $dp = $dpeMap[$parcours->getId()] ?? null;
            $anomalies = array_merge($anomalies, $this->getAnomaliesParcours($parcours, $campagne, $dp, $paramsByAnnee));
        }
        return $anomalies;
    }

    /**
     * @param array<int, list<PlateformeAdmissionParametre>>|null $paramsByAnnee
     * @param array<int, \App\Entity\Annee>|null $annees
     * @return array<int, string>
     */
    public function getAnomaliesParcours(
        Parcours $parcours,
        CampagneCollecte $campagne,
        ?DpeParcours $dpeParcours = null,
        ?array $paramsByAnnee = null,
        ?array $annees = null
    ): array {
        $anomalies = [];
        
        // Check if parcours is open for this campaign
        $isOuvert = false;
        if ($dpeParcours !== null) {
            $isOuvert = ($dpeParcours->getEtatReconduction() === TypeModificationDpeEnum::OUVERT);
        } else {
            foreach ($parcours->getDpeParcours() as $d) {
                if ($d->getCampagneCollecte() === $campagne) {
                    $isOuvert = ($d->getEtatReconduction() === TypeModificationDpeEnum::OUVERT);
                    break;
                }
            }
        }

        if ($isOuvert) {
            $hasOpenAnnee = false;
            $anneesList = $annees ?? $parcours->getAnnees();
            foreach ($anneesList as $annee) {
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

                    // Récupérer la configuration des plateformes pour le type de diplôme
                    $formation = $parcours->getFormation();
                    $typeDiplome = $formation?->getTypeDiplome();
                    $tpaMap = [];
                    if ($typeDiplome !== null) {
                        foreach ($typeDiplome->getTypeDiplomePlateformeAdmissions() as $tpa) {
                            if ($tpa->getCampagne() === $campagne && $tpa->getPlateforme() !== null) {
                                $tpaMap[$tpa->getPlateforme()->getId()] = $tpa;
                            }
                        }
                    }

                    // Parcourir les plateformes (préchargées ou via relation)
                    $params = $paramsByAnnee !== null
                        ? ($paramsByAnnee[$annee->getId()] ?? [])
                        : $annee->getAdmissionPlateformeParametres();

                    foreach ($params as $param) {
                        if ($param->getCampagne() !== $campagne) {
                            continue;
                        }

                        $plateforme = $param->getPlateforme();
                        $platId = $plateforme?->getId();
                        $platLibelle = $plateforme?->getLibelle() ?? 'Inconnue';
                        $tpa = $platId ? ($tpaMap[$platId] ?? null) : null;
                        $anneeOrdre = $annee->getOrdre();
                        $isCapaciteRequise = $tpa !== null && $tpa->isCapaciteRequise($anneeOrdre);

                        $hasCapacite = ($param->getCapaciteGlobale() !== null && $param->getCapaciteGlobale() > 0)
                            || ($param->getCapaciteFi() !== null && $param->getCapaciteFi() > 0)
                            || ($param->getCapaciteAlternance() !== null && $param->getCapaciteAlternance() > 0)
                            || ($param->getCapaciteSpecifique() !== null && $param->getCapaciteSpecifique() > 0);

                        if ($param->isActive()) {
                            // Contrôle 1 : Plateforme active sans capacité alors que la capacité est obligatoire
                            if ($isCapaciteRequise && !$hasCapacite) {
                                $anomalies[] = sprintf(
                                    "Le parcours \"%s\" (Année %d) a la plateforme %s active, mais sa capacité (obligatoire) n'est pas renseignée.",
                                    $parcours->getLibelle(),
                                    $anneeOrdre,
                                    $platLibelle
                                );
                            }
                        } else {
                            // Contrôle 2 : Plateforme inactive mais avec capacité renseignée
                            if ($hasCapacite) {
                                $anomalies[] = sprintf(
                                    "Le parcours \"%s\" (Année %d) a une capacité renseignée sur la plateforme %s, alors que celle-ci est inactive.",
                                    $parcours->getLibelle(),
                                    $anneeOrdre,
                                    $platLibelle
                                );
                            }
                        }
                    }
                }
            }

            // Anomalie 3: Parcours ouvert mais aucune année n'est ouverte
            if (!$hasOpenAnnee && count($anneesList) > 0) {
                $anomalies[] = sprintf(
                    "Le parcours \"%s\" est ouvert, mais toutes ses années sont fermées.",
                    $parcours->getLibelle()
                );
            }
        }

        return $anomalies;
    }
}
