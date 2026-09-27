<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\FicheMatiere;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Service\Versioning\VersioningInspectorService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class VersioningExtension extends AbstractExtension
{
    public function __construct(
        private readonly VersioningInspectorService $inspectorService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('versioning_formation_info', [$this, 'getFormationInfo']),
            new TwigFunction('versioning_parcours_info', [$this, 'getParcoursInfo']),
            new TwigFunction('versioning_fiche_info', [$this, 'getFicheInfo']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getFormationInfo(Formation $formation): array
    {
        return $this->inspectorService->getFormationVersioningInfo($formation);
    }

    /**
     * @return array<string, mixed>
     */
    public function getParcoursInfo(Parcours $parcours): array
    {
        return $this->inspectorService->getParcoursVersioningInfo($parcours);
    }

    /**
     * @return array<string, mixed>
     */
    public function getFicheInfo(FicheMatiere $fiche): array
    {
        return $this->inspectorService->getFicheMatiereVersioningInfo($fiche);
    }
}
