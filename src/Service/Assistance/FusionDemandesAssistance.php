<?php

namespace App\Service\Assistance;

use App\Entity\DemandeAssistance;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/**
 * Fusionne deux demandes d'assistance : les messages de la source passent sur la cible, la source est supprimée.
 */
class FusionDemandesAssistance
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function fusionner(DemandeAssistance $cible, DemandeAssistance $source): void
    {
        if ($cible === $source || ($cible->getId() !== null && $cible->getId() === $source->getId())) {
            throw new InvalidArgumentException('Impossible de fusionner une demande avec elle-même.');
        }

        foreach ($source->getMessages()->toArray() as $message) {
            $source->removeMessage($message);
            $cible->addMessage($message);
        }

        if ($source->getCreated() < $cible->getCreated()) {
            $cible->setCreated($source->getCreated());
        }
        $cible->setResponsable($cible->getResponsable() ?? $source->getResponsable());
        $cible->setParcours($cible->getParcours() ?? $source->getParcours());
        $cible->setFormation($cible->getFormation() ?? $source->getFormation());
        $cible->recalculerDateDernierMessage();

        $this->entityManager->remove($source);
        $this->entityManager->flush();
    }
}
