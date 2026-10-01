<?php

namespace App\Service;

use App\Entity\CampagneCollecte;
use App\Entity\TimelineDate;
use App\Enums\CampagneModuleEnum;
use App\Enums\TimelineDateFlagEnum;
use Doctrine\ORM\EntityManagerInterface;

class CampagneCollecteService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
    }

    public function updateModuleDates(
        CampagneCollecte $campagne,
        CampagneModuleEnum $module,
        ?\DateTimeInterface $dateDebut,
        ?\DateTimeInterface $dateFin,
        ?\DateTimeInterface $heureFin = null,
        ?string $libelle = null,
        bool $inTimeline = true
    ): TimelineDate {
        $step = null;
        foreach ($campagne->getTimelineDates() as $time) {
            if ($time->hasModule($module)) {
                $step = $time;
                break;
            }
        }

        if ($step === null) {
            // Chercher une ancienne étape de collecte (flag ou libellé)
            foreach ($campagne->getTimelineDates() as $time) {
                if ($time->getFlag() === TimelineDateFlagEnum::OUVERTURE_COLLECTE || $time->getFlag() === TimelineDateFlagEnum::CLOTURE_COLLECTE) {
                    $step = $time;
                    break;
                }
            }
        }

        if ($step === null) {
            $step = new TimelineDate();
            $step->setCampagneCollecte($campagne);
            $step->setIcone('icon:graduation');
            $step->setOrdre(1);
            $campagne->addTimelineDate($step);
            $this->em->persist($step);
        }

        $step->setLibelle($libelle ?: 'Saisie de l\'offre de formation');
        $step->setInTimeline($inTimeline);

        $dtDebut = $dateDebut ? ($dateDebut instanceof \DateTime ? $dateDebut : new \DateTime($dateDebut->format('Y-m-d H:i:s'), $dateDebut->getTimezone())) : null;
        $dtFin = $dateFin ? ($dateFin instanceof \DateTime ? $dateFin : new \DateTime($dateFin->format('Y-m-d H:i:s'), $dateFin->getTimezone())) : ($dtDebut ?? new \DateTime());
        $dtHeure = $heureFin ? ($heureFin instanceof \DateTime ? $heureFin : new \DateTime($heureFin->format('H:i:s'), $heureFin->getTimezone())) : null;

        $step->setDateDebut($dtDebut);
        $step->setDate($dtFin);
        $step->setHeure($dtHeure);

        $modules = $step->getModulesActifs();
        if (!in_array($module->value, $modules, true)) {
            $modules[] = $module->value;
            $step->setModulesActifs($modules);
        }

        $this->em->flush();

        return $step;
    }

    public function updateDates(
        CampagneCollecte $campagne,
        ?\DateTimeInterface $dateOuverture,
        ?\DateTimeInterface $dateCloture
    ): void {
        $this->updateModuleDates($campagne, CampagneModuleEnum::OFFRE_FORMATION, $dateOuverture, $dateCloture);
    }
}

