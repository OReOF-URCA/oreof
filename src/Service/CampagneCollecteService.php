<?php

namespace App\Service;

use App\Entity\CampagneCollecte;
use App\Entity\TimelineDate;
use App\Enums\TimelineDateFlagEnum;
use Doctrine\ORM\EntityManagerInterface;

class CampagneCollecteService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
    }

    public function updateDates(
        CampagneCollecte $campagne,
        ?\DateTimeInterface $dateOuverture,
        ?\DateTimeInterface $dateCloture
    ): void {
        $this->updateTimelineDate($campagne, TimelineDateFlagEnum::OUVERTURE_COLLECTE, $dateOuverture, 'Ouverture de la collecte', 'fa-solid fa-play');
        $this->updateTimelineDate($campagne, TimelineDateFlagEnum::CLOTURE_COLLECTE, $dateCloture, 'Clôture de la collecte', 'fa-solid fa-stop');
        $this->em->flush();
    }

    private function updateTimelineDate(
        CampagneCollecte $campagne,
        TimelineDateFlagEnum $flag,
        ?\DateTimeInterface $date,
        string $libelle,
        string $icone
    ): void {
        $found = false;
        foreach ($campagne->getTimelineDates() as $time) {
            if ($time->getFlag() === $flag) {
                if ($date === null) {
                    $campagne->removeTimelineDate($time);
                    $this->em->remove($time);
                } else {
                    $dt = $date instanceof \DateTime ? $date : new \DateTime($date->format('Y-m-d H:i:s'), $date->getTimezone());
                    $time->setDate($dt);
                }
                $found = true;
                break;
            }
        }

        if (!$found && $date !== null) {
            $time = new TimelineDate();
            $time->setCampagneCollecte($campagne);
            $time->setLibelle($libelle);
            $time->setIcone($icone);
            $dt = $date instanceof \DateTime ? $date : new \DateTime($date->format('Y-m-d H:i:s'), $date->getTimezone());
            $time->setDate($dt);
            $time->setFlag($flag);

            $campagne->addTimelineDate($time);
            $this->em->persist($time);
        }
    }
}
