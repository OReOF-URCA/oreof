<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CoherenceAnalyseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CoherenceAnalyseRepository::class)]
#[ORM\Table(name: 'coherence_analyse')]
#[ORM\UniqueConstraint(name: 'UNIQ_COHERENCE_ENTITY', columns: ['entity_type', 'entity_id'])]
#[ORM\Index(name: 'IDX_COHERENCE_STATUS', columns: ['status'])]
#[ORM\Index(name: 'IDX_COHERENCE_ANALYZED_AT', columns: ['analyzed_at'])]
#[ORM\HasLifecycleCallbacks]
class CoherenceAnalyse
{
    public const TYPE_PARCOURS = 'parcours';
    public const TYPE_FICHE_MATIERE = 'fiche_matiere';

    public const STATUS_OK = 'ok';
    public const STATUS_WARNING = 'warning';
    public const STATUS_ALERT = 'alert';
    public const STATUS_PENDING = 'pending';
    public const STATUS_ERROR = 'error';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'entity_type', length: 32)]
    private string $entityType = self::TYPE_PARCOURS;

    #[ORM\Column(name: 'entity_id')]
    private int $entityId = 0;

    #[ORM\OneToOne(inversedBy: 'coherenceAnalyse')]
    #[ORM\JoinColumn(name: 'parcours_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Parcours $parcours = null;

    #[ORM\OneToOne(inversedBy: 'coherenceAnalyse')]
    #[ORM\JoinColumn(name: 'fiche_matiere_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?FicheMatiere $ficheMatiere = null;

    #[ORM\Column(name: 'content_hash', length: 64)]
    private string $contentHash = '';

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(nullable: true)]
    private ?int $score = null;

    #[ORM\Column(name: 'deterministic_score', nullable: true)]
    private ?int $deterministicScore = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summary = null;

    /**
     * @var list<array{
     *     field: string,
     *     fieldLabel: string,
     *     severity: string,
     *     category: string,
     *     source: string,
     *     message: string,
     *     excerpt?: string|null
     * }>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $issues = [];

    #[ORM\Column(name: 'is_ai_generated')]
    private bool $isAiGenerated = false;

    #[ORM\Column(name: 'provider_label', length: 120, nullable: true)]
    private ?string $providerLabel = null;

    #[ORM\Column(name: 'analyzed_at', nullable: true)]
    private ?\DateTimeImmutable $analyzedAt = null;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): static
    {
        $this->entityType = $entityType;

        return $this;
    }

    public function getEntityId(): int
    {
        return $this->entityId;
    }

    public function setEntityId(int $entityId): static
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getParcours(): ?Parcours
    {
        return $this->parcours;
    }

    public function setParcours(?Parcours $parcours): static
    {
        $this->parcours = $parcours;
        if ($parcours !== null) {
            $this->entityType = self::TYPE_PARCOURS;
            $this->entityId = (int)$parcours->getId();
        }

        return $this;
    }

    public function getFicheMatiere(): ?FicheMatiere
    {
        return $this->ficheMatiere;
    }

    public function setFicheMatiere(?FicheMatiere $ficheMatiere): static
    {
        $this->ficheMatiere = $ficheMatiere;
        if ($ficheMatiere !== null) {
            $this->entityType = self::TYPE_FICHE_MATIERE;
            $this->entityId = (int)$ficheMatiere->getId();
        }

        return $this;
    }

    public function getContentHash(): string
    {
        return $this->contentHash;
    }

    public function setContentHash(string $contentHash): static
    {
        $this->contentHash = $contentHash;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(?int $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function getDeterministicScore(): ?int
    {
        return $this->deterministicScore;
    }

    public function setDeterministicScore(?int $deterministicScore): static
    {
        $this->deterministicScore = $deterministicScore;

        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): static
    {
        $this->summary = $summary;

        return $this;
    }

    /**
     * @return list<array{
     *     field: string,
     *     fieldLabel: string,
     *     severity: string,
     *     category: string,
     *     source: string,
     *     message: string,
     *     excerpt?: string|null
     * }>
     */
    public function getIssues(): array
    {
        return $this->issues;
    }

    /**
     * @param list<array{
     *     field: string,
     *     fieldLabel: string,
     *     severity: string,
     *     category: string,
     *     source: string,
     *     message: string,
     *     excerpt?: string|null
     * }> $issues
     */
    public function setIssues(array $issues): static
    {
        $this->issues = $issues;

        return $this;
    }

    public function isAiGenerated(): bool
    {
        return $this->isAiGenerated;
    }

    public function setIsAiGenerated(bool $isAiGenerated): static
    {
        $this->isAiGenerated = $isAiGenerated;

        return $this;
    }

    public function getProviderLabel(): ?string
    {
        return $this->providerLabel;
    }

    public function setProviderLabel(?string $providerLabel): static
    {
        $this->providerLabel = $providerLabel;

        return $this;
    }

    public function getAnalyzedAt(): ?\DateTimeImmutable
    {
        return $this->analyzedAt;
    }

    public function setAnalyzedAt(?\DateTimeImmutable $analyzedAt): static
    {
        $this->analyzedAt = $analyzedAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function countAlerts(): int
    {
        return count(array_filter(
            $this->issues,
            static fn(array $issue): bool => ($issue['severity'] ?? '') === 'alert'
        ));
    }

    public function countWarnings(): int
    {
        return count(array_filter(
            $this->issues,
            static fn(array $issue): bool => ($issue['severity'] ?? '') === 'warning'
        ));
    }
}
