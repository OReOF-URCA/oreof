<?php

namespace App\Service;

use App\Classes\DataUserSession;
use App\Entity\CampagneCollecte;
use App\Entity\TimelineDate;
use App\Enums\CampagneModuleEnum;
use App\Repository\CampagneCollecteRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Bundle\SecurityBundle\Security;

class CampagneAccessibilityService
{
    public function __construct(
        private readonly DataUserSession            $dataUserSession,
        private readonly Security                   $security,
        private readonly CampagneCollecteRepository $campagneCollecteRepository,
    ) {
    }

    public function getCampagne(?CampagneCollecte $campagne = null): ?CampagneCollecte
    {
        return $campagne
            ?? $this->dataUserSession->getCampagneCollecte()
            ?? $this->campagneCollecteRepository->findOneBy(['defaut' => true]);
    }

    /**
     * Vérifie si un module est actuellement ouvert dans la campagne selon ses étapes de timeline.
     */
    public function isModuleOpen(
        CampagneModuleEnum|string $module,
        ?CampagneCollecte         $campagne = null,
        ?DateTimeInterface        $date = null
    ): bool {
        $moduleEnum = $this->resolveModule($module);
        if ($moduleEnum === null) {
            return false;
        }

        $targetCampagne = $this->getCampagne($campagne);
        if ($targetCampagne === null) {
            return true;
        }

        $now = $date ? DateTimeImmutable::createFromInterface($date) : new DateTimeImmutable();

        foreach ($targetCampagne->getTimelineDates() as $step) {
            if (!$step->hasModule($moduleEnum)) {
                continue;
            }

            if ($this->isStepActiveAt($step, $now)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vérifie si l'utilisateur courant peut modifier les éléments du module donné.
     * Les administrateurs (ROLE_ADMIN) disposent d'un bypass permanent.
     */
    public function canUserEditModule(
        CampagneModuleEnum|string $module,
        ?CampagneCollecte         $campagne = null,
        ?DateTimeInterface        $date = null
    ): bool {
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        return $this->isModuleOpen($module, $campagne, $date);
    }

    /**
     * Renvoie le statut d'accessibilité détaillé pour un module (état, dates, messages).
     *
     * @return array{
     *     module: CampagneModuleEnum,
     *     isOpen: bool,
     *     canEdit: bool,
     *     activeStep: ?TimelineDate,
     *     allSteps: array<TimelineDate>,
     *     closingDate: ?DateTimeInterface,
     *     nextOpeningDate: ?DateTimeInterface,
     *     message: string
     * }
     */
    public function getModuleStatus(
        CampagneModuleEnum|string $module,
        ?CampagneCollecte         $campagne = null,
        ?DateTimeInterface        $date = null
    ): array {
        $moduleEnum = $this->resolveModule($module) ?? CampagneModuleEnum::OFFRE_FORMATION;
        $targetCampagne = $this->getCampagne($campagne);
        $now = $date ? DateTimeImmutable::createFromInterface($date) : new DateTimeImmutable();

        $isOpen = false;
        $activeStep = null;
        $allSteps = [];
        $closingDate = null;
        $nextOpeningDate = null;

        if ($targetCampagne !== null) {
            foreach ($targetCampagne->getTimelineDates() as $step) {
                if ($step->hasModule($moduleEnum)) {
                    $allSteps[] = $step;
                    if ($this->isStepActiveAt($step, $now)) {
                        $isOpen = true;
                        $activeStep = $step;
                        $closingDate = $step->getDate();
                    } elseif ($step->getDateDebut() && $step->getDateDebut() > $now) {
                        if ($nextOpeningDate === null || $step->getDateDebut() < $nextOpeningDate) {
                            $nextOpeningDate = $step->getDateDebut();
                        }
                    }
                }
            }
        }

        $canEdit = $this->security->isGranted('ROLE_ADMIN') || $isOpen;

        $message = match (true) {
            $isOpen && $closingDate !== null => sprintf('Module ouvert jusqu\'au %s.', $closingDate->format('d/m/Y')),
            $isOpen => 'Module actuellement ouvert.',
            $nextOpeningDate !== null => sprintf('Module fermé. Ouverture prévue le %s.', $nextOpeningDate->format('d/m/Y')),
            count($allSteps) === 0 => 'Aucune période de saisie configurée pour ce module.',
            default => 'Période de saisie terminée pour ce module (consultation seule).'
        };

        return [
            'module' => $moduleEnum,
            'isOpen' => $isOpen,
            'canEdit' => $canEdit,
            'activeStep' => $activeStep,
            'allSteps' => $allSteps,
            'closingDate' => $closingDate,
            'nextOpeningDate' => $nextOpeningDate,
            'message' => $message,
        ];
    }

    private function isStepActiveAt(TimelineDate $step, DateTimeImmutable $now): bool
    {
        $endDate = $step->getDate();
        if ($endDate === null) {
            return false;
        }

        $endLimit = DateTimeImmutable::createFromInterface($endDate);
        if ($step->getHeure() !== null) {
            $endLimit = $endLimit->setTime(
                (int) $step->getHeure()->format('H'),
                (int) $step->getHeure()->format('i'),
                (int) $step->getHeure()->format('s')
            );
        } else {
            $endLimit = $endLimit->setTime(23, 59, 59);
        }

        $startDate = $step->getDateDebut();
        $startLimit = $startDate
            ? DateTimeImmutable::createFromInterface($startDate)->setTime(0, 0, 0)
            : DateTimeImmutable::createFromInterface($endDate)->setTime(0, 0, 0);

        return $now >= $startLimit && $now <= $endLimit;
    }

    private function resolveModule(CampagneModuleEnum|string $module): ?CampagneModuleEnum
    {
        if ($module instanceof CampagneModuleEnum) {
            return $module;
        }

        return CampagneModuleEnum::tryFrom($module);
    }
}
