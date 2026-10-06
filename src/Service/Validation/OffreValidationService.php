<?php

declare(strict_types=1);

namespace App\Service\Validation;

use App\Entity\CampagneCollecte;
use App\Entity\DpeParcours;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\PlateformeAdmissionParametre;
use App\Enums\TypeParcoursEnum;

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
     * @param array<int, list<\App\Entity\TypeDiplomePlateformeAdmission>>|null $tpaByTypeDiplome
     * @return array<int, string>
     */
    public function getAnomaliesParcours(
        Parcours $parcours,
        CampagneCollecte $campagne,
        ?DpeParcours $dpeParcours = null,
        ?array $paramsByAnnee = null,
        ?array $annees = null,
        ?array $tpaByTypeDiplome = null
    ): array {
        $anomalies = [];
        
        // Check if parcours is open for this campaign
        $isOuvert = false;
        if ($dpeParcours !== null) {
            $isOuvert = $dpeParcours->isOuvert();
        } else {
            foreach ($parcours->getDpeParcours() as $d) {
                if ($d->getCampagneCollecte() === $campagne) {
                    $isOuvert = $d->isOuvert();
                    break;
                }
            }
        }

        if ($isOuvert) {
            // Formation (type de diplôme + mention), parcours et type de parcours : deux parcours d'une même
            // formation peuvent porter le même nom (ex. classique / en alternance).
            $typeParcours = $parcours->getTypeParcours();
            $sujet = sprintf(
                '%s › parcours « %s »%s',
                $parcours->getFormation()?->getDisplayLong() ?? 'Formation inconnue',
                $parcours->getLibelle(),
                $typeParcours !== null && $typeParcours !== TypeParcoursEnum::TYPE_PARCOURS_CLASSIQUE
                    ? ' – ' . $typeParcours->libelle()
                    : ''
            );
            $hasOpenAnnee = false;
            $anneesList = $annees ?? $parcours->getAnnees();
            foreach ($anneesList as $annee) {
                if ($annee->isOuvert() === true) {
                    $hasOpenAnnee = true;
                    
                    // Capacité de l'année négative
                    if ($annee->getCapaciteAccueil() < 0) {
                        $anomalies[] = sprintf(
                            '%s (Année %d) : capacité d\'accueil globale négative (%d).',
                            $sujet,
                            $annee->getOrdre(),
                            $annee->getCapaciteAccueil()
                        );
                    }

                    // Récupérer la configuration des plateformes pour le type de diplôme
                    $formation = $parcours->getFormation();
                    $typeDiplome = $formation?->getTypeDiplome();
                    $tpaMap = [];
                    if ($typeDiplome !== null) {
                        $typeDiplId = $typeDiplome->getId();
                        $tpaList = ($tpaByTypeDiplome !== null && $typeDiplId !== null)
                            ? ($tpaByTypeDiplome[$typeDiplId] ?? [])
                            : $typeDiplome->getTypeDiplomePlateformeAdmissions();

                        foreach ($tpaList as $tpa) {
                            if ($tpa->getCampagne() === $campagne && $tpa->getPlateforme() !== null) {
                                $tpaMap[$tpa->getPlateforme()->getId()] = $tpa;
                            }
                        }
                    }

                    // Parcourir les plateformes (préchargées ou via relation)
                    $params = $paramsByAnnee !== null
                        ? ($paramsByAnnee[$annee->getId()] ?? [])
                        : $annee->getAdmissionPlateformeParametres();

                    $seenPlateformeIds = [];

                    foreach ($params as $param) {
                        if ($param->getCampagne() !== $campagne) {
                            continue;
                        }

                        $plateforme = $param->getPlateforme();
                        $platId = $plateforme?->getId();
                        $platLibelle = $plateforme?->getLibelle() ?? 'Inconnue';
                        if ($platId !== null) {
                            $seenPlateformeIds[$platId] = true;
                        }
                        $tpa = $platId ? ($tpaMap[$platId] ?? null) : null;
                        $anneeOrdre = $annee->getOrdre();
                        $isCapaciteRequise = $tpa !== null && $anneeOrdre !== null && $tpa->isCapaciteRequise($anneeOrdre);

                        $capaciteGlobale = $param->getCapaciteGlobale();
                        $capaciteFi = $param->getCapaciteFi();
                        $capaciteAlternance = $param->getCapaciteAlternance();
                        $capaciteSpecifique = $param->getCapaciteSpecifique();

                        $hasNegativeCapacite = ($capaciteGlobale !== null && $capaciteGlobale < 0)
                            || ($capaciteFi !== null && $capaciteFi < 0)
                            || ($capaciteAlternance !== null && $capaciteAlternance < 0)
                            || ($capaciteSpecifique !== null && $capaciteSpecifique < 0);

                        $sommeCapacites = ($capaciteGlobale ?? 0)
                            + ($capaciteFi ?? 0)
                            + ($capaciteAlternance ?? 0)
                            + ($capaciteSpecifique ?? 0);

                        if ($hasNegativeCapacite) {
                            $anomalies[] = sprintf(
                                '%s (Année %d) : capacité négative renseignée sur la plateforme %s.',
                                $sujet,
                                $anneeOrdre,
                                $platLibelle
                            );
                        }

                        if ($param->isActive()) {
                            // Contrôle 1 : Plateforme active sans capacité (somme <= 0) alors que la capacité est obligatoire
                            if ($isCapaciteRequise && $sommeCapacites <= 0 && !$hasNegativeCapacite) {
                                $anomalies[] = sprintf(
                                    '%s (Année %d) : plateforme %s active mais capacité (obligatoire) non renseignée ou nulle.',
                                    $sujet,
                                    $anneeOrdre,
                                    $platLibelle
                                );
                            }
                        } else {
                            // Contrôle 2 : Plateforme inactive mais avec capacité renseignée
                            if ($sommeCapacites > 0) {
                                $anomalies[] = sprintf(
                                    '%s (Année %d) : capacité renseignée sur la plateforme %s alors qu\'elle est inactive.',
                                    $sujet,
                                    $anneeOrdre,
                                    $platLibelle
                                );
                            } elseif ($isCapaciteRequise && !$hasNegativeCapacite) {
                                // Contrôle 3 : Plateforme inactive et sans capacité alors que la capacité est obligatoire
                                $anomalies[] = sprintf(
                                    '%s (Année %d) : plateforme %s inactive et sans capacité alors que sa capacité est obligatoire.',
                                    $sujet,
                                    $anneeOrdre,
                                    $platLibelle
                                );
                            }
                        }
                    }

                    // Contrôle 4 : Plateforme avec capacité obligatoire configurée pour l'année mais sans aucun paramètre enregistré
                    $anneeOrdre = $annee->getOrdre();
                    if ($anneeOrdre !== null) {
                        foreach ($tpaMap as $platId => $tpa) {
                            if (!isset($seenPlateformeIds[$platId]) && $tpa->isCapaciteRequise($anneeOrdre)) {
                                $platLibelle = $tpa->getPlateforme()?->getLibelle() ?? 'Inconnue';
                                $anomalies[] = sprintf(
                                    '%s (Année %d) : plateforme %s inactive et sans capacité alors que sa capacité est obligatoire.',
                                    $sujet,
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
                    '%s : ouvert mais toutes ses années sont fermées.',
                    $sujet
                );
            }
        }

        return $anomalies;
    }
}
