<?php

declare(strict_types=1);

namespace App\DTO\Coherence;

use App\Entity\CoherenceAnalyse;

final readonly class CoherenceCheckResult
{
    /**
     * @param list<CoherenceIssue> $issues
     */
    public function __construct(
        public string              $status,
        public int                 $score,
        public int                 $deterministicScore,
        public string              $summary,
        public array               $issues,
        public string              $contentHash,
        public bool                $isAiGenerated,
        public string              $providerLabel,
        public bool                $isPersisted = false,
        public bool                $isStale = false,
        public ?\DateTimeImmutable $analyzedAt = null,
        public ?string             $fallbackReason = null,
    )
    {
    }

    public function getAlertsCount(): int
    {
        return count(array_filter(
            $this->issues,
            static fn(CoherenceIssue $issue): bool => $issue->severity === CoherenceIssue::SEVERITY_ALERT
        ));
    }

    public function getWarningsCount(): int
    {
        return count(array_filter(
            $this->issues,
            static fn(CoherenceIssue $issue): bool => $issue->severity === CoherenceIssue::SEVERITY_WARNING
        ));
    }

    public function getInfosCount(): int
    {
        return count(array_filter(
            $this->issues,
            static fn(CoherenceIssue $issue): bool => $issue->severity === CoherenceIssue::SEVERITY_INFO
        ));
    }

    public function hasIssues(): bool
    {
        return $this->issues !== [];
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            CoherenceAnalyse::STATUS_OK => 'Cohérent',
            CoherenceAnalyse::STATUS_WARNING => 'À vérifier',
            CoherenceAnalyse::STATUS_ALERT => 'Incohérent / suspect',
            CoherenceAnalyse::STATUS_PENDING => 'Analyse en cours',
            CoherenceAnalyse::STATUS_ERROR => 'Erreur d\'analyse',
            default => 'Non analysé',
        };
    }

    public function getVariant(): string
    {
        return match ($this->status) {
            CoherenceAnalyse::STATUS_OK => 'success',
            CoherenceAnalyse::STATUS_WARNING => 'warning',
            CoherenceAnalyse::STATUS_ALERT => 'danger',
            CoherenceAnalyse::STATUS_PENDING => 'info',
            default => 'secondary',
        };
    }
}
