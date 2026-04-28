<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Service/MutualisationChangeNotifier.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 21/03/2026 09:00
 */

declare(strict_types=1);

namespace App\Service;

use App\Classes\Mailer;
use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\Notification;
use App\Entity\Parcours;
use App\Entity\Semestre;
use App\Entity\Ue;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class MutualisationChangeNotifier
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Mailer                 $mailer,
    )
    {
    }

    public function notifyForFicheMatiere(FicheMatiere $ficheMatiere, array $changedFields = [], ?Parcours $sourceParcours = null, ?User $actor = null): void
    {
        $impactedParcours = [];
        foreach ($ficheMatiere->getFicheMatiereParcours() as $mutualisation) {
            $parcours = $mutualisation->getParcours();
            if ($parcours !== null) {
                $impactedParcours[$parcours->getId()] = $parcours;
            }
        }

        if ($sourceParcours === null) {
            $sourceParcours = $ficheMatiere->getParcours();
        }

        $this->notifyParcours(
            array_values($impactedParcours),
            'fiche_matiere',
            $ficheMatiere->getLibelle() ?? ('Fiche matiere #' . $ficheMatiere->getId()),
            $ficheMatiere->getId(),
            $changedFields,
            $sourceParcours,
            $actor,
            null,
            null
        );
    }

    /**
     * @param Parcours[] $impactedParcours
     */
    private function notifyParcours(
        array     $impactedParcours,
        string    $sourceType,
        string    $sourceLabel,
        ?int      $sourceId,
        array     $changedFields,
        ?Parcours $sourceParcours,
        ?User     $actor,
        ?Ue       $sourceUe,
        ?Semestre $sourceSemestre,
    ): void
    {
        if (count($impactedParcours) === 0) {
            return;
        }

        $changedFields = array_values(array_unique(array_filter($changedFields)));
        $actorLabel = $actor?->getDisplay();
        $sourceUeLabel = $this->formatUeLabel($sourceUe);
        $sourceSemestreLabel = $this->formatSemestreLabel($sourceSemestre);

        foreach ($impactedParcours as $parcours) {
            $recipients = $this->resolveParcoursResponsables($parcours);
            if (count($recipients) === 0) {
                continue;
            }

            foreach ($recipients as $recipient) {
                $notification = new Notification();
                $notification->setDestinataire($recipient);
                $notification->setTitle('Element mutualise modifie');
                $notification->setBody(sprintf(
                    'Un element mutualise (%s) a ete modifie et impacte votre parcours %s. Merci de valider la prise en compte.',
                    $sourceLabel,
                    $parcours->getLibelle()
                ));
                $notification->setPayload([
                    'category' => 'mutualisation_update',
                    'mustAck' => true,
                    'ackAt' => null,
                    'sourceType' => $sourceType,
                    'sourceId' => $sourceId,
                    'sourceLabel' => $sourceLabel,
                    'parcoursId' => $parcours->getId(),
                    'parcoursLabel' => $parcours->getLibelle(),
                    'sourceParcoursId' => $sourceParcours?->getId(),
                    'sourceParcoursLabel' => $sourceParcours?->getLibelle(),
                    'sourceUeId' => $sourceUe?->getId(),
                    'sourceUeLabel' => $sourceUeLabel,
                    'sourceSemestreId' => $sourceSemestre?->getId(),
                    'sourceSemestreLabel' => $sourceSemestreLabel,
                    'changedFields' => $changedFields,
                    'actor' => $actorLabel,
                    'url' => '/parcours/' . $parcours->getId(),
                ]);

                $this->entityManager->persist($notification);
            }

            $this->mailer->initEmail();
            $this->mailer->setTemplate('mails/mutualisation/parcours_mutualisation_update.html.twig', [
                'parcours' => $parcours,
                'sourceType' => $sourceType,
                'sourceLabel' => $sourceLabel,
                'changedFields' => $changedFields,
                'sourceParcours' => $sourceParcours,
                'sourceUeLabel' => $sourceUeLabel,
                'sourceSemestreLabel' => $sourceSemestreLabel,
                'actor' => $actor,
            ]);
            $this->mailer->sendMessage(
                array_map(static fn(User $user): ?string => $user->getEmail(), $recipients),
                '[OReOF] Mise a jour d\'un element mutualise: ' . $sourceLabel
            );
        }
    }

    private function formatUeLabel(?Ue $ue): ?string
    {
        if ($ue === null) {
            return null;
        }

        $label = $ue->display();
        if ($ue->getLibelle() !== null) {
            $label .= ' (' . $ue->getLibelle() . ')';
        }

        return $label;
    }

    private function formatSemestreLabel(?Semestre $semestre): ?string
    {
        return $semestre?->display();
    }

    /**
     * @return User[]
     */
    private function resolveParcoursResponsables(Parcours $parcours): array
    {
        $users = [];

        if ($parcours->getRespParcours() !== null) {
            $users[$parcours->getRespParcours()->getId()] = $parcours->getRespParcours();
        }

        if ($parcours->getCoResponsable() !== null) {
            $users[$parcours->getCoResponsable()->getId()] = $parcours->getCoResponsable();
        }

        return array_values($users);
    }

    public function notifyForUe(
        Ue        $ue,
        array     $changedFields = [],
        ?User     $actor = null,
        ?Semestre $semestreContext = null
    ): void
    {
        $ue = $this->resolveRootUe($ue);
        $semestreContext ??= $ue?->getSemestre();

        $impactedParcours = [];
        foreach ($ue?->getUeMutualisables() ?? [] as $mutualisation) {
            $parcours = $mutualisation->getParcours();
            if ($parcours !== null) {
                $impactedParcours[$parcours->getId()] = $parcours;
            }
        }

        foreach ($semestreContext?->getSemestreMutualisables() ?? [] as $mutualisation) {
            $parcours = $mutualisation->getParcours();
            if ($parcours !== null) {
                $impactedParcours[$parcours->getId()] = $parcours;
            }
        }

        $this->notifyParcours(
            array_values($impactedParcours),
            'ue',
            $ue?->display() ?? 'UE',
            $ue?->getId(),
            $changedFields,
            $ue !== null ? $this->resolveParcoursFromUe($ue) : $this->resolveParcoursFromSemestre($semestreContext),
            $actor,
            $ue,
            $semestreContext
        );
    }

    private function resolveRootUe(?Ue $ue): ?Ue
    {
        while ($ue?->getUeParent() !== null) {
            $ue = $ue->getUeParent();
        }

        return $ue;
    }

    private function resolveParcoursFromUe(Ue $ue): ?Parcours
    {
        foreach ($ue->getElementConstitutifs() as $elementConstitutif) {
            if ($elementConstitutif->getParcours() !== null) {
                return $elementConstitutif->getParcours();
            }
        }

        foreach ($ue->getSemestre()?->getSemestreParcours() ?? [] as $semestreParcours) {
            if ($semestreParcours->getParcours() !== null) {
                return $semestreParcours->getParcours();
            }
        }

        return null;
    }

    private function resolveParcoursFromSemestre(Semestre $semestre): ?Parcours
    {
        foreach ($semestre->getSemestreParcours() as $semestreParcours) {
            if ($semestreParcours->getParcours() !== null) {
                return $semestreParcours->getParcours();
            }
        }

        return null;
    }

    public function notifyForSemestre(Semestre $semestre, array $changedFields = [], ?User $actor = null): void
    {
        $impactedParcours = [];
        foreach ($semestre->getSemestreMutualisables() as $mutualisation) {
            $parcours = $mutualisation->getParcours();
            if ($parcours !== null) {
                $impactedParcours[$parcours->getId()] = $parcours;
            }
        }

        $this->notifyParcours(
            array_values($impactedParcours),
            'semestre',
            $semestre->display(),
            $semestre->getId(),
            $changedFields,
            $this->resolveParcoursFromSemestre($semestre),
            $actor,
            null,
            $semestre
        );
    }

    /**
     * Point d'entrée unifié pour les changements MCCC/heures/ECTS d'un EC.
     * Agrège les parcours impactés via FicheMatiereMutualisable (fiche partagée)
     * ET via UeMutualisable (UE partagée), puis envoie une seule notification par parcours.
     */
    public function notifyForElementConstitutif(
        ElementConstitutif $ec,
        array              $changedFields = [],
        ?Parcours          $sourceParcours = null,
        ?User              $actor = null,
        ?Ue                $ueContext = null,
        ?Semestre          $semestreContext = null
    ): void
    {
        $impactedParcours = [];

        // 1. Parcours impactés via la fiche matière mutualisée
        $ficheMatiere = $ec->getFicheMatiere();
        if ($ficheMatiere !== null) {
            foreach ($ficheMatiere->getFicheMatiereParcours() as $mutualisation) {
                $parcours = $mutualisation->getParcours();
                if ($parcours !== null) {
                    $impactedParcours[$parcours->getId()] = $parcours;
                }
            }
        }

        // 2. Parcours impactés via l'UE mutualisée (UeMutualisable)
        $ue = $this->resolveRootUe($ueContext ?? $ec->getUe());
        if ($ue !== null) {
            foreach ($ue->getUeMutualisables() as $mutualisation) {
                $parcours = $mutualisation->getParcours();
                if ($parcours !== null && !isset($impactedParcours[$parcours->getId()])) {
                    $impactedParcours[$parcours->getId()] = $parcours;
                }
            }
        }

        // 3. Parcours impactés via le semestre mutualisé (UE -> Semestre -> SemestreMutualisable)
        $semestreContext ??= $ue?->getSemestre();
        foreach ($semestreContext?->getSemestreMutualisables() ?? [] as $mutualisation) {
            $parcours = $mutualisation->getParcours();
            if ($parcours !== null && !isset($impactedParcours[$parcours->getId()])) {
                $impactedParcours[$parcours->getId()] = $parcours;
            }
        }

        // Exclure le parcours source de la liste
        if ($sourceParcours !== null) {
            unset($impactedParcours[$sourceParcours->getId()]);
        }

        if ($sourceParcours === null) {
            $sourceParcours = $ec->getParcours() ?? $ficheMatiere?->getParcours();
        }

        $label = $ficheMatiere?->getLibelle()
            ?? $ec->getLibelle()
            ?? ('EC #' . $ec->getId());

        $this->notifyParcours(
            array_values($impactedParcours),
            'fiche_matiere',
            $label,
            $ficheMatiere?->getId() ?? $ec->getId(),
            $changedFields,
            $sourceParcours,
            $actor,
            $ue,
            $semestreContext
        );
    }
}

