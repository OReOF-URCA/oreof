<?php
/*
 * Copyright (c) 2024. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Events/DemandeDpeEvent.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 30/01/2024 08:53
 */

namespace App\Events;

use App\Entity\DpeDemande;
use App\Entity\User;
use Symfony\Component\Security\Core\User\UserInterface;

class DpeDemandeEvent
{
    public const DPE_DEMANDE_CLOSED = 'demande_dpe.closed';
    public const DPE_DEMANDE_OPENED = 'demande_dpe.opened';
    public const DPE_DEMANDE_UPDATED = 'demande_dpe.updated';

    protected DpeDemande $dpeDemande;
    protected ?User $user;

    public function __construct(
        DpeDemande $dpeDemande,
        ?User $user = null
    ) {
        $this->dpeDemande = $dpeDemande;
        $this->user = $user;
    }

    public function getDpeDemande(): DpeDemande
    {
        return $this->dpeDemande;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }
}
