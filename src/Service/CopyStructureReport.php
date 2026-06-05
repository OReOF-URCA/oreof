<?php

namespace App\Service;

/**
 * Bilan d'une copie structurelle : ce qui a été copié et ce qui a été ignoré
 * (fiches matières partagées), pour informer l'utilisateur.
 */
class CopyStructureReport
{
    public int $ecCopies = 0;
    public int $ecCrees = 0;
    public int $fichesCreees = 0;
    public int $fichesReutilisees = 0;
    public int $uesCopiees = 0;
    public int $uesCreees = 0;
    public int $parcoursCrees = 0;

    /** @var string[] Libellés des fiches partagées non écrasées */
    public array $fichesPartageesIgnorees = [];

    /** @var string[] UE non répercutées qu'on n'a pas pu recréer (semestre cible absent) */
    public array $uesNonRecreees = [];
}
