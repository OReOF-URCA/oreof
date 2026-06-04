<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Twig/Components/MutualisationAlertsComponent.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 21/03/2026 09:01
 */

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Parcours;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('mutualisation_alerts')]
final class MutualisationAlertsComponent extends AbstractController
{
    public ?User $user = null;
    public ?Parcours $parcours = null;

    public function __construct(private readonly NotificationRepository $notificationRepository)
    {
    }

    public function getCount(): int
    {
        if (!$this->user instanceof User) {
            return 0;
        }

        return $this->notificationRepository->countMutualisationPendingForUser($this->user, $this->parcours);
    }

    public function getNotifications(): array
    {
        if (!$this->user instanceof User) {
            return [];
        }

        return $this->notificationRepository->findMutualisationPendingForUser($this->user, $this->parcours);
    }
}

