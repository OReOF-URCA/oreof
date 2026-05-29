<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/EventSubscriber/DoctrineMutualisationUpdateSubscriber.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 21/03/2026 09:00
 */

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\Mccc;
use App\Entity\Parcours;
use App\Entity\Semestre;
use App\Entity\Ue;
use App\Entity\User;
use App\Service\MutualisationChangeNotifier;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

//#[AsDoctrineListener(event: Events::onFlush)]
//#[AsDoctrineListener(event: Events::postFlush)]
class DoctrineMutualisationUpdateSubscriber
{
    private const EC_HEURES_FIELDS = [
        'volumeCmPresentiel', 'volumeTdPresentiel', 'volumeTpPresentiel',
        'volumeCmDistanciel', 'volumeTdDistanciel', 'volumeTpDistanciel',
        'volumeTe', 'ects', 'sansHeure', 'heuresSpecifiques', 'mcccEnfantsIdentique',
        'heuresEnfantsIdentiques', 'typeMccc', 'etatMccc',
    ];
    private const EC_STRUCTURE_FIELDS = ['ue', 'ecParent'];

    // Champs d'EC qui concernent les heures/structure
    private const IGNORED_FIELDS = ['updated', 'created', 'remplissage', 'etatSteps', 'etatFiche'];
    /** @var array<string, array{entity: object, changedFields: string[], force: bool, ueContext: ?Ue, semestreContext: ?Semestre, sourceParcours: ?Parcours}> keyed by dedup key */
    private array $pendingNotifications = [];

    // Champs à ignorer (lifecycle, interne)
    private bool $isProcessingPostFlush = false;

    public function __construct(
        private readonly MutualisationChangeNotifier $notifier,
        private readonly Security                    $security,
    )
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if ($this->isProcessingPostFlush) {
            return;
        }

        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        // --- 1. Modifications directes sur FicheMatiere / Ue / Semestre ---
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof FicheMatiere && !$entity instanceof Ue && !$entity instanceof Semestre) {
                continue;
            }
            $changeSet = $uow->getEntityChangeSet($entity);
            $fields = $this->filterChangedFields(array_keys($changeSet));
            if (count($fields) === 0) {
                continue;
            }
            $this->addPending($entity, $fields);
        }

        // --- 2. Modifications directes sur ElementConstitutif (heures, ects, structure) ---
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof ElementConstitutif) {
                continue;
            }
            $changeSet = $uow->getEntityChangeSet($entity);
            $fields = array_values(array_intersect(array_keys($changeSet), self::EC_HEURES_FIELDS));
            $structureFields = $this->extractEcStructureFields($changeSet);
            $fields = array_values(array_unique(array_merge($fields, $structureFields)));
            if (count($fields) === 0) {
                continue;
            }

            $oldRootUe = $this->extractOldRootUeForEc($entity, $changeSet);
            $oldSemestre = $oldRootUe?->getSemestre();

            $this->addPending(
                $entity,
                $fields,
                force: $this->isMutualizedUeOrSemestre($oldRootUe, $oldSemestre),
                ueContext: $oldRootUe,
                semestreContext: $oldSemestre,
            );
        }

        // --- 2 bis. Modifications directes sur UE (rattachement Semestre/choix d'UE) ---
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof Ue) {
                continue;
            }

            $changeSet = $uow->getEntityChangeSet($entity);
            $structureFields = $this->extractUeStructureFields($changeSet);
            if (count($structureFields) === 0) {
                continue;
            }

            $oldRootUe = $this->extractOldRootUeForUe($entity, $changeSet);
            $oldSemestre = $this->extractOldSemestreForUe($entity, $changeSet);

            $this->addPending(
                $entity,
                $structureFields,
                force: $this->isMutualizedUeOrSemestre($oldRootUe, $oldSemestre),
                ueContext: $oldRootUe,
                semestreContext: $oldSemestre,
            );
        }

        // --- 2 ter. Suppressions directes sur EC / UE ---
        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof ElementConstitutif) {
                $rootUe = $this->resolveRootUe($entity->getUe());
                $semestre = $rootUe?->getSemestre();
                $this->addPending(
                    $entity,
                    ['deleted_ec'],
                    force: $this->isMutualizedUeOrSemestre($rootUe, $semestre),
                    ueContext: $rootUe,
                    semestreContext: $semestre,
                );
                continue;
            }

            if ($entity instanceof Ue) {
                $rootUe = $this->resolveRootUe($entity);
                $semestre = $rootUe?->getSemestre();
                $this->addPending(
                    $entity,
                    ['deleted_ue'],
                    force: $this->isMutualizedUeOrSemestre($rootUe, $semestre),
                    ueContext: $rootUe,
                    semestreContext: $semestre,
                );
            }
        }

        // --- 3. Modifications / insertions / suppressions de Mccc (MCCC réels) ---
        $mcccEntities = array_merge(
            array_values($uow->getScheduledEntityInsertions()),
            array_values($uow->getScheduledEntityUpdates()),
            array_values($uow->getScheduledEntityDeletions()),
        );
        foreach ($mcccEntities as $entity) {
            if (!$entity instanceof Mccc) {
                continue;
            }
            // Remonter vers l'EC parent s'il existe
            $parentEc = $entity->getEc();
            if ($parentEc !== null) {
                $this->addPending($parentEc, ['mccc']);
                continue;
            }
            // Sinon vers la FicheMatiere
            $parentFm = $entity->getFicheMatiere();
            if ($parentFm !== null) {
                $this->addPending($parentFm, ['mccc']);
            }
        }
    }

    private function filterChangedFields(array $keys): array
    {
        return array_values(array_filter($keys, static fn(string $f): bool => !in_array($f, self::IGNORED_FIELDS, true)));
    }

    // -------------------------------------------------------------------------

    private function addPending(
        object    $entity,
        array     $fields,
        bool      $force = false,
        ?Ue       $ueContext = null,
        ?Semestre $semestreContext = null,
        ?Parcours $sourceParcours = null,
    ): void
    {
        $key = get_class($entity) . '_' . spl_object_id($entity);
        if (isset($this->pendingNotifications[$key])) {
            $this->pendingNotifications[$key]['changedFields'] = array_values(
                array_unique(array_merge($this->pendingNotifications[$key]['changedFields'], $fields))
            );
            $this->pendingNotifications[$key]['force'] = $this->pendingNotifications[$key]['force'] || $force;
            $this->pendingNotifications[$key]['ueContext'] ??= $ueContext;
            $this->pendingNotifications[$key]['semestreContext'] ??= $semestreContext;
            $this->pendingNotifications[$key]['sourceParcours'] ??= $sourceParcours;
        } else {
            $this->pendingNotifications[$key] = [
                'entity' => $entity,
                'changedFields' => $fields,
                'force' => $force,
                'ueContext' => $ueContext,
                'semestreContext' => $semestreContext,
                'sourceParcours' => $sourceParcours,
            ];
        }
    }

    /** @param array<string, array{0:mixed,1:mixed}> $changeSet */
    private function extractEcStructureFields(array $changeSet): array
    {
        $fields = [];

        if (isset($changeSet['ue'])) {
            $old = $changeSet['ue'][0] ?? null;
            $new = $changeSet['ue'][1] ?? null;
            if ($old !== null && $new === null) {
                $fields[] = 'removed_from_ue';
            } elseif ($old !== null && $new !== $old) {
                $fields[] = 'moved_to_other_ue';
            }
        }

        if (isset($changeSet['ecParent'])) {
            $old = $changeSet['ecParent'][0] ?? null;
            $new = $changeSet['ecParent'][1] ?? null;
            if ($old !== null && $new === null) {
                $fields[] = 'removed_from_ec_choice';
            } elseif ($old !== null && $new !== $old) {
                $fields[] = 'moved_to_other_ec_choice';
            }
        }

        return array_values(array_unique($fields));
    }

    /** @param array<string, array{0:mixed,1:mixed}> $changeSet */
    private function extractOldRootUeForEc(ElementConstitutif $ec, array $changeSet): ?Ue
    {
        if (isset($changeSet['ue']) && $changeSet['ue'][0] instanceof Ue) {
            return $this->resolveRootUe($changeSet['ue'][0]);
        }

        if (isset($changeSet['ecParent']) && $changeSet['ecParent'][0] instanceof ElementConstitutif) {
            return $this->resolveRootUe($changeSet['ecParent'][0]->getUe());
        }

        return $this->resolveRootUe($ec->getUe());
    }

    private function resolveRootUe(?Ue $ue): ?Ue
    {
        while ($ue?->getUeParent() !== null) {
            $ue = $ue->getUeParent();
        }

        return $ue;
    }

    private function isMutualizedUeOrSemestre(?Ue $ue, ?Semestre $semestre): bool
    {
        if ($ue !== null && ($ue->getUeMutualisables()->count() > 0 || $ue->getUeRaccrochee() !== null)) {
            return true;
        }

        return $semestre !== null
            && ($semestre->getSemestreMutualisables()->count() > 0 || $semestre->getSemestreRaccroche() !== null);
    }

    /** @param array<string, array{0:mixed,1:mixed}> $changeSet */
    private function extractUeStructureFields(array $changeSet): array
    {
        $fields = [];

        if (isset($changeSet['semestre'])) {
            $old = $changeSet['semestre'][0] ?? null;
            $new = $changeSet['semestre'][1] ?? null;
            if ($old !== null && $new === null) {
                $fields[] = 'removed_from_semestre';
            } elseif ($old !== null && $new !== $old) {
                $fields[] = 'moved_to_other_semestre';
            }
        }

        if (isset($changeSet['ueParent'])) {
            $old = $changeSet['ueParent'][0] ?? null;
            $new = $changeSet['ueParent'][1] ?? null;
            if ($old !== null && $new === null) {
                $fields[] = 'removed_from_ue_choice';
            } elseif ($old !== null && $new !== $old) {
                $fields[] = 'moved_to_other_ue_choice';
            }
        }

        return array_values(array_unique($fields));
    }

    /** @param array<string, array{0:mixed,1:mixed}> $changeSet */
    private function extractOldRootUeForUe(Ue $ue, array $changeSet): ?Ue
    {
        if (isset($changeSet['ueParent']) && $changeSet['ueParent'][0] instanceof Ue) {
            return $this->resolveRootUe($changeSet['ueParent'][0]);
        }

        return $this->resolveRootUe($ue);
    }

    /** @param array<string, array{0:mixed,1:mixed}> $changeSet */
    private function extractOldSemestreForUe(Ue $ue, array $changeSet): ?Semestre
    {
        if (isset($changeSet['semestre']) && $changeSet['semestre'][0] instanceof Semestre) {
            return $changeSet['semestre'][0];
        }

        return $this->resolveRootUe($ue)?->getSemestre();
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->isProcessingPostFlush || count($this->pendingNotifications) === 0) {
            return;
        }
        $this->isProcessingPostFlush = true;
        $hasWork = false;
        $user = $this->security->getUser();
        $actor = $user instanceof User ? $user : null;

        $pending = $this->pendingNotifications;
        $this->pendingNotifications = [];

        foreach ($pending as $item) {
            $entity = $item['entity'];
            $changedFields = $item['changedFields'];

            if ($entity instanceof ElementConstitutif) {
                $ueContext = $item['ueContext'] ?? null;
                $semestreContext = $item['semestreContext'] ?? null;
                $force = $item['force'] ?? false;

                // Vérifie si l'EC est dans un contexte mutualisé (Fiche, UE, Semestre)
                if ($force || $this->ecIsInMutualizedContext($entity)) {
                    $this->notifier->notifyForElementConstitutif(
                        $entity,
                        $changedFields,
                        $item['sourceParcours'] ?? null,
                        $actor,
                        $ueContext,
                        $semestreContext
                    );
                    $hasWork = true;
                }
                continue;
            }

            if ($entity instanceof FicheMatiere && $entity->getFicheMatiereParcours()->count() > 0) {
                $this->notifier->notifyForFicheMatiere($entity, $changedFields, null, $actor);
                $hasWork = true;
                continue;
            }

            if ($entity instanceof Ue) {
                $semestreContext = $item['semestreContext'] ?? null;
                $force = $item['force'] ?? false;
                if (!$force && !$this->ueIsInMutualizedContext($entity, $semestreContext)) {
                    continue;
                }

                $this->notifier->notifyForUe($entity, $changedFields, $actor, $semestreContext);
                $hasWork = true;
                continue;
            }

            if ($entity instanceof Semestre && $entity->getSemestreMutualisables()->count() > 0) {
                $this->notifier->notifyForSemestre($entity, $changedFields, $actor);
                $hasWork = true;
            }
        }

        if ($hasWork) {
            $args->getObjectManager()->flush();
        }

        $this->isProcessingPostFlush = false;
    }

    private function ecIsInMutualizedContext(ElementConstitutif $ec): bool
    {
        // Remonter à l'EC racine (cas EC enfant → ecParent)
        $rootEc = $ec;
        while ($rootEc->getEcParent() !== null) {
            $rootEc = $rootEc->getEcParent();
        }

        // Via la fiche matière mutualisée (utilisée dans plusieurs parcours)
        if ($rootEc->getFicheMatiere()?->getFicheMatiereParcours()->count() > 0) {
            return true;
        }

        // Remonter à l'UE racine (cas UE enfant → ueParent)
        $ue = $rootEc->getUe();
        while ($ue?->getUeParent() !== null) {
            $ue = $ue->getUeParent();
        }

        if ($ue === null) {
            return false;
        }

        // L'UE est source d'une mutualisation (elle est partagée vers d'autres parcours)
        if ($ue->getUeMutualisables()->count() > 0) {
            return true;
        }

        // L'UE est accrochée à une mutualisation (elle consomme une UE mutualisée)
        if ($ue->getUeRaccrochee() !== null) {
            return true;
        }

        $semestre = $ue->getSemestre();

        // Le semestre est source d'une mutualisation
        if ($semestre?->getSemestreMutualisables()->count() > 0) {
            return true;
        }

        // Le semestre est raccroché à une mutualisation (consommateur)
        if ($semestre?->getSemestreRaccroche() !== null) {
            return true;
        }

        return false;
    }

    private function ueIsInMutualizedContext(Ue $ue, ?Semestre $semestreContext = null): bool
    {
        $rootUe = $this->resolveRootUe($ue);
        if ($rootUe === null) {
            return false;
        }

        if ($rootUe->getUeMutualisables()->count() > 0 || $rootUe->getUeRaccrochee() !== null) {
            return true;
        }

        $semestre = $semestreContext ?? $rootUe->getSemestre();

        return $semestre?->getSemestreMutualisables()->count() > 0 || $semestre?->getSemestreRaccroche() !== null;
    }
}
