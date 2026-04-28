<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Service/ChangeParcoursService.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 07/12/2025 18:31
 */

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

class ChangeParcoursService
{
    public function __construct(
        private EntityManagerInterface $em,
        private VersioningParcours     $versioningParcours // pour versionner après modification
    )
    {
    }

    /**
     * Applique une demande marquée APPROVED.
     */
    public function applyApproved(DemandeAction $demande): void
    {
        if ($demande->getStatus() !== ActionStatus::APPROVED) {
            throw new \LogicException('Demande non approuvée');
        }

        $type = $demande->getType();
        $payload = $demande->getPayload() ?? [];

        switch ($type) {
            case ActionType::MODIFY_LABEL:
                $parcours = $demande->getParcours();
                if ($parcours === null) {
                    throw new \RuntimeException('Parcours manquant pour modification');
                }
                $newLabel = $payload['libelle'] ?? null;
                if ($newLabel !== null) {
                    $parcours->setLibelle($newLabel);
                    $this->em->persist($parcours);
                    // optionnel : versionner
                    $this->versioningParcours->saveVersionOfParcours($parcours, new \DateTimeImmutable('now'), true);
                }
                break;

            case ActionType::CLOSE_PARCOURS:
                $parcours = $demande->getParcours();
                if ($parcours === null) {
                    throw new \RuntimeException('Parcours manquant pour fermeture');
                }
                $parcours->setActif(false); // exemple : champ `actif`
                $this->em->persist($parcours);
                $this->versioningParcours->saveVersionOfParcours($parcours, new \DateTimeImmutable('now'), true);
                break;

            case ActionType::CREATE_PARCOURS:
                // payload doit contenir les champs nécessaires pour créer le parcours
                $data = $payload['data'] ?? [];
                $parcours = new \App\Entity\Parcours($data['formation']); // adapter selon constructeur
                $parcours->setLibelle($data['libelle'] ?? 'Nouveau parcours');
                $this->em->persist($parcours);
                // versioning si vous voulez
                break;

            default:
                throw new \RuntimeException('Type d\'action non géré');
        }

        $this->em->flush();
    }
}
