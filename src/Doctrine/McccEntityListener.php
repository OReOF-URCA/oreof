<?php


// src/Doctrine/McccEntityListener.php
namespace App\Doctrine;

use App\Service\McccCompletionChecker;
use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\Mccc;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;

#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: Mccc::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: Mccc::class)]
#[AsEntityListener(event: Events::preRemove, method: 'preRemove', entity: Mccc::class)]
#[AsEntityListener(event: Events::postRemove, method: 'postRemove', entity: Mccc::class)]
#[AsDoctrineListener(event: Events::postFlush)]
final class McccEntityListener
{
    /** @var array<FicheMatiere|ElementConstitutif> */
    private array $ownersToFlush = [];
    private bool $isFlushing = false;

    public function __construct(
        private readonly McccCompletionChecker $checker,
    )
    {
    }

    public function postPersist(Mccc $mccc, PostPersistEventArgs $args): void
    {
        if ($owner = $mccc->getOwner()) {
            $this->scheduleOwnerUpdate($owner);
        }
    }

    public function preUpdate(Mccc $mccc, PreUpdateEventArgs $args): void
    {
        if ($args->hasChangedField('ficheMatiere')) {
            [$old, $new] = [$args->getOldValue('ficheMatiere'), $args->getNewValue('ficheMatiere')];
            if ($old instanceof FicheMatiere) {
                $this->scheduleOwnerUpdate($old);
            }
            if ($new instanceof FicheMatiere) {
                $this->scheduleOwnerUpdate($new);
            }
        }

        if ($args->hasChangedField('elementConstitutif')) {
            [$old, $new] = [$args->getOldValue('elementConstitutif'), $args->getNewValue('elementConstitutif')];
            if ($old instanceof ElementConstitutif) {
                $this->scheduleOwnerUpdate($old);
            }
            if ($new instanceof ElementConstitutif) {
                $this->scheduleOwnerUpdate($new);
            }
        }

        if ($owner = $mccc->getOwner()) {
            $this->scheduleOwnerUpdate($owner);
        }
    }

    public function preRemove(Mccc $mccc, PreRemoveEventArgs $args): void
    {
        // Capture l'owner avant suppression: postRemove peut perdre l'association selon le mapping.
        if ($owner = $mccc->getOwner()) {
            $this->scheduleOwnerUpdate($owner);
        }
    }

    public function postRemove(Mccc $mccc, PostRemoveEventArgs $args): void
    {
        // Fallback si l'owner est encore disponible à ce stade.
        if ($owner = $mccc->getOwner()) {
            $this->scheduleOwnerUpdate($owner);
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->isFlushing || $this->ownersToFlush === []) {
            return;
        }

        $em = $args->getObjectManager();
        $this->isFlushing = true;
        $owners = $this->ownersToFlush;
        $this->ownersToFlush = [];

        try {
            foreach ($owners as $owner) {
                $owner->setEtatMccc($this->checker->isCompletedForOwner($owner) ? 'Complet' : 'A saisir');
                $em->persist($owner);
            }

            $em->flush();
        } finally {
            $this->isFlushing = false;
        }
    }

    private function scheduleOwnerUpdate(FicheMatiere|ElementConstitutif $owner): void
    {
        // Déduplique par instance d'objet pour éviter les recalculs multiples sur un même flush.
        $this->ownersToFlush[spl_object_id($owner)] = $owner;
    }

    public function getOwnersToFlush(): array
    {
        return $this->ownersToFlush;
    }

    public function clearOwnersToFlush(): void
    {
        $this->ownersToFlush = [];
    }
}
