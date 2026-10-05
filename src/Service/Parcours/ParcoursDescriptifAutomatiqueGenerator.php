<?php

declare(strict_types=1);

namespace App\Service\Parcours;

use App\Entity\CampagneCollecte;
use App\Entity\DpeParcours;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Enums\TypeModificationDpeEnum;
use App\Repository\DpeParcoursRepository;

final class ParcoursDescriptifAutomatiqueGenerator
{
    public function __construct(
        private readonly DpeParcoursRepository $dpeParcoursRepository,
    ) {
    }

    /**
     * Génère et met à jour le champ descriptifHautPageAutomatique pour tous les parcours d'une formation.
     *
     * @return array<int, string|null> Textes générés indexés par id de parcours
     */
    public function generateForFormation(Formation $formation, ?CampagneCollecte $campagne = null): array
    {
        $results = [];
        foreach ($formation->getParcours() as $parcours) {
            $pId = $parcours->getId();
            $texte = $this->generateForParcours($parcours, $campagne);
            if ($pId !== null) {
                $results[$pId] = $texte;
            }
        }

        return $results;
    }

    /**
     * Génère et met à jour le champ descriptifHautPageAutomatique sur un parcours selon son état de modification.
     */
    public function generateForParcours(Parcours $parcours, ?CampagneCollecte $campagne = null, ?DpeParcours $dpeParcours = null): ?string
    {
        $dpePar = $dpeParcours;
        if ($dpePar === null && $campagne !== null) {
            $dpePar = $parcours->getDpeParcoursPourCampagne($campagne)
                ?? $this->dpeParcoursRepository->findOneBy([
                    'parcours' => $parcours,
                    'campagneCollecte' => $campagne,
                ]);
        }

        if ($dpePar === null) {
            $firstDpe = $parcours->getDpeParcours()->first();
            $dpePar = $firstDpe instanceof DpeParcours ? $firstDpe : null;
        }

        $texte = $this->computeDescriptif($parcours, $campagne, $dpePar);
        $parcours->setDescriptifHautPageAutomatique($texte);

        return $texte;
    }

    /**
     * Calcule le texte automatique selon les règles de fermeture / non-ouverture / fermeture définitive.
     */
    public function computeDescriptif(Parcours $parcours, ?CampagneCollecte $campagne = null, ?DpeParcours $dpeParcours = null): ?string
    {
        $anneeUniv = $this->resolveAnneeUniversitaire($campagne, $dpeParcours);

        // 1. Fermeture définitive (sur le DPE parcours ou la formation)
        $etatRecond = $dpeParcours?->getEtatReconduction();
        $formationEtatRecond = $parcours->getFormation()?->getEtatReconduction();
        if ($etatRecond === TypeModificationDpeEnum::FERMETURE_DEFINITIVE || $formationEtatRecond === TypeModificationDpeEnum::FERMETURE_DEFINITIVE) {
            return 'Parcours en fermeture définitive';
        }

        // 2. Non ouverture au niveau du DPE parcours
        if ($dpeParcours !== null && $dpeParcours->isNonOuvert()) {
            return $anneeUniv !== ''
                ? sprintf("Parcours non ouvert pour l'année %s", $anneeUniv)
                : "Parcours non ouvert";
        }

        // 3. Vérification des années et semestres non ouverts
        $semestresFermes = [];
        $totalSemestres = 0;

        $semestreParcoursList = $parcours->getSemestreParcours()->toArray();
        if (!empty($semestreParcoursList)) {
            usort($semestreParcoursList, static fn($a, $b) => ($a->getOrdre() ?? 0) <=> ($b->getOrdre() ?? 0));
            $totalSemestres = count($semestreParcoursList);

            // Années marquées fermées
            $anneesFermeesOrdre = [];
            foreach ($parcours->getAnnees() as $annee) {
                if ($annee->isOuvert() === false && $annee->getOrdre() !== null) {
                    $anneesFermeesOrdre[$annee->getOrdre()] = true;
                }
            }

            foreach ($semestreParcoursList as $sp) {
                $numSemestre = $sp->getOrdre();
                if ($numSemestre === null || $numSemestre <= 0) {
                    continue;
                }

                $anneeOrdre = (int)ceil($numSemestre / 2);
                $isAnneeFermee = isset($anneesFermeesOrdre[$anneeOrdre]);
                $isSemestreFerme = ($sp->isOuvert() === false) || $isAnneeFermee;

                if ($isSemestreFerme) {
                    $semestresFermes[] = $numSemestre;
                }
            }
        }

        // Si tous les semestres sont fermés -> parcours non ouvert
        if ($totalSemestres > 0 && count($semestresFermes) === $totalSemestres) {
            return $anneeUniv !== ''
                ? sprintf("Parcours non ouvert pour l'année %s", $anneeUniv)
                : "Parcours non ouvert";
        }

        // Si certains semestres sont fermés
        if (!empty($semestresFermes)) {
            $semestresFermes = array_values(array_unique($semestresFermes));
            sort($semestresFermes);

            if (count($semestresFermes) === 1) {
                return sprintf("Le semestre %d n'est pas ouvert.", $semestresFermes[0]);
            }

            return sprintf("Les semestres %s ne sont pas ouverts.", implode(', ', $semestresFermes));
        }

        // 4. Parcours ouvert / sans fermeture
        return null;
    }

    private function resolveAnneeUniversitaire(?CampagneCollecte $campagne, ?DpeParcours $dpeParcours): string
    {
        $camp = $campagne ?? $dpeParcours?->getCampagneCollecte();
        if ($camp === null) {
            return '';
        }

        $libelleAnnee = $camp->getAnneeUniversitaire()?->getLibelle();
        if ($libelleAnnee !== null && trim($libelleAnnee) !== '') {
            return trim($libelleAnnee);
        }

        $libelleCamp = $camp->getLibelle();
        if ($libelleCamp !== null && trim($libelleCamp) !== '') {
            return trim($libelleCamp);
        }

        if ($camp->getAnnee() !== null) {
            return sprintf('%d-%d', $camp->getAnnee(), $camp->getAnnee() + 1);
        }

        return '';
    }
}
