<?php

namespace App\Service;

/**
 * Bilan d'une copie structurelle : ce qui a été copié et ce qui a été ignoré
 * (fiches matières partagées), pour informer l'utilisateur.
 */
class CopyStructureReport
{
    public int $ecCopies = 0;
    public int $uesCopiees = 0;

    /** @var string[] Libellés des fiches partagées non écrasées */
    public array $fichesPartageesIgnorees = [];
}
