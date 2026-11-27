<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\DTO\Coherence\CoherenceCheckResult;
use App\Entity\CoherenceAnalyse;
use App\Entity\FicheMatiere;
use App\Entity\Parcours;
use App\Service\Coherence\CoherenceAnalyzerService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsTwigComponent('CoherenceBadge', template: 'components/CoherenceBadge.html.twig')]
final class CoherenceBadge
{
    public ?Parcours $parcours = null;

    public ?FicheMatiere $ficheMatiere = null;

    public bool $compact = false;

    public bool $clickable = true;

    public ?CoherenceCheckResult $result = null;

    public function __construct(
        private readonly CoherenceAnalyzerService $coherenceAnalyzer,
        private readonly UrlGeneratorInterface    $urlGenerator,
        private readonly Security                 $security,
    )
    {
    }

    #[PostMount]
    public function mount(): void
    {
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            $this->result = null;

            return;
        }

        if ($this->result !== null) {
            return;
        }

        if ($this->parcours !== null) {
            $this->result = $this->coherenceAnalyzer->getIndicatorForParcours($this->parcours);

            return;
        }

        if ($this->ficheMatiere !== null) {
            $this->result = $this->coherenceAnalyzer->getIndicatorForFicheMatiere($this->ficheMatiere);
        }
    }

    public function getTargetUrl(): ?string
    {
        if (!$this->clickable || $this->result === null) {
            return null;
        }

        if ($this->parcours !== null && $this->parcours->getId() !== null) {
            return $this->urlGenerator->generate('app_coherence_parcours_modal', [
                'parcours' => $this->parcours->getId(),
            ]);
        }

        if ($this->ficheMatiere !== null && $this->ficheMatiere->getId() !== null) {
            return $this->urlGenerator->generate('app_coherence_fiche_modal', [
                'ficheMatiere' => $this->ficheMatiere->getId(),
            ]);
        }

        return null;
    }

    public function getStreamMarkerClass(): string
    {
        if ($this->parcours !== null && $this->parcours->getId() !== null) {
            return 'js-coherence-badge-parcours-' . $this->parcours->getId();
        }

        if ($this->ficheMatiere !== null && $this->ficheMatiere->getId() !== null) {
            return 'js-coherence-badge-fiche-' . $this->ficheMatiere->getId();
        }

        return '';
    }

    public function getBadgeLabel(): string
    {
        if ($this->result === null) {
            return '';
        }

        if ($this->compact) {
            $short = match ($this->result->status) {
                CoherenceAnalyse::STATUS_OK => 'Cohérent',
                CoherenceAnalyse::STATUS_WARNING => 'À vérifier',
                CoherenceAnalyse::STATUS_ALERT => 'Alerte',
                default => 'Analyse',
            };

            return sprintf('%s · %d/100', $short, $this->result->score);
        }

        return sprintf('%s (%d/100)', $this->result->getStatusLabel(), $this->result->score);
    }

    public function getBadgeIcon(): string
    {
        if ($this->result === null) {
            return 'icon:star';
        }

        return match ($this->result->status) {
            CoherenceAnalyse::STATUS_ALERT => 'icon:danger',
            CoherenceAnalyse::STATUS_WARNING => 'icon:warning',
            CoherenceAnalyse::STATUS_OK => 'icon:check-circle',
            default => 'icon:star',
        };
    }

    public function getTooltipText(): string
    {
        if ($this->result === null) {
            return '';
        }

        $parts = [
            sprintf('Cohérence : %s (%d/100)', $this->result->getStatusLabel(), $this->result->score),
            $this->result->summary,
        ];

        if ($this->result->isAiGenerated) {
            $parts[] = 'Analyse IA effectuée.';
        } else {
            $parts[] = 'Pré-filtre déterministe uniquement — cliquez pour lancer l\'analyse IA.';
        }

        if ($this->result->isStale) {
            $parts[] = 'Contenu modifié depuis la dernière analyse.';
        }

        return implode(' ', array_filter($parts));
    }
}
