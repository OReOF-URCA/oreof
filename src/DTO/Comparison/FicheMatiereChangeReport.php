<?php

declare(strict_types=1);

namespace App\DTO\Comparison;

final class FicheMatiereChangeReport
{
    public const SOURCE_SNAPSHOT_VALIDE = 'snapshot_valide';
    public const SOURCE_SNAPSHOT_JSON = 'snapshot_json';
    public const SOURCE_ORIGINE_N1 = 'origine_n1';
    public const SOURCE_NONE = 'none';

    /**
     * @param list<FicheMatiereChangeItem> $changes
     */
    public function __construct(
        public readonly int $ficheId,
        public readonly string $ficheLibelle,
        public readonly string $ficheSlug,
        public readonly string $sourceType,
        public readonly string $sourceLabel,
        public readonly bool $hasSnapshotAvailable = false,
        public readonly bool $hasOrigineN1Available = false,
        public readonly array $changes = [],
    ) {
    }

    public function hasReference(): bool
    {
        return $this->sourceType !== self::SOURCE_NONE;
    }

    public function hasChanges(): bool
    {
        return count($this->changes) > 0;
    }

    public function countChanges(): int
    {
        return count($this->changes);
    }

    /**
     * @return array<string, list<FicheMatiereChangeItem>>
     */
    public function getChangesByCategory(): array
    {
        $grouped = [];
        foreach ($this->changes as $change) {
            $grouped[$change->category][] = $change;
        }

        return $grouped;
    }

    /**
     * @return list<string>
     */
    public function getImpactedCategoriesLabels(): array
    {
        $labels = [];
        foreach (array_keys($this->getChangesByCategory()) as $cat) {
            $labels[] = FicheMatiereChangeItem::categoryLabel($cat);
        }

        return $labels;
    }

    public function getFingerprint(): string
    {
        $payload = [
            'id' => $this->ficheId,
            'source' => $this->sourceType,
            'changes' => array_map(
                static fn (FicheMatiereChangeItem $item): array => [
                    'c' => $item->category,
                    'f' => $item->field,
                    'b' => $item->before,
                    'a' => $item->after,
                ],
                $this->changes
            ),
        ];

        return hash('sha256', (string) json_encode($payload));
    }
}

