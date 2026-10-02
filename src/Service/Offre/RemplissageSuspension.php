<?php

namespace App\Service\Offre;

use App\Entity\Formation;
use App\Entity\Parcours;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Le remplissage des parcours et des formations (updateRemplissage, PreFlush) parcourt toute la maquette.
 * Il n'est calculé que depuis les pages parcours / formation : les écritures faites depuis les pages
 * de l'offre (capacités, ouverture, validation, ajout de parcours) ne doivent pas le déclencher.
 *
 * À appeler juste avant le flush() : concerne les entités chargées et celles en cours de création.
 */
final class RemplissageSuspension
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function suspendre(): void
    {
        $uow = $this->em->getUnitOfWork();

        $identityMap = $uow->getIdentityMap();
        $entites = [
            ...($identityMap[Parcours::class] ?? []),
            ...($identityMap[Formation::class] ?? []),
            ...$uow->getScheduledEntityInsertions(),
        ];

        foreach ($entites as $entite) {
            if ($entite instanceof Parcours || $entite instanceof Formation) {
                $entite->suspendreRecalculRemplissage();
            }
        }
    }
}
