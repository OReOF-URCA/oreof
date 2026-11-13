<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\DemandeAssistance;
use App\Service\Assistance\ContexteUsagerAssistance as ContexteService;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Panneau « contexte ORéOF » du demandeur sur la fiche d'une demande d'assistance.
 *
 * <twig:ContexteUsagerAssistance :demande="demande" />
 */
#[AsTwigComponent('ContexteUsagerAssistance', template: 'components/contexte_usager_assistance.html.twig')]
final class ContexteUsagerAssistance
{
    public DemandeAssistance $demande;

    public function __construct(
        private readonly ContexteService $contexteService,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getContexte(): ?array
    {
        return $this->contexteService->pour($this->demande);
    }
}
