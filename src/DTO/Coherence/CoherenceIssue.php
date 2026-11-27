<?php

declare(strict_types=1);

namespace App\DTO\Coherence;

final readonly class CoherenceIssue
{
    public const SEVERITY_ALERT = 'alert';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_INFO = 'info';

    public const CATEGORY_FICTIF = 'fictif';
    public const CATEGORY_REMPLISSAGE = 'remplissage';
    public const CATEGORY_INCOHERENCE = 'incoherence';
    public const CATEGORY_COMPLETUDE_SEMANTIQUE = 'completude_semantique';

    public const SOURCE_DETERMINISTIC = 'deterministic';
    public const SOURCE_AI = 'ai';

    public function __construct(
        public string  $field,
        public string  $fieldLabel,
        public string  $severity,
        public string  $category,
        public string  $source,
        public string  $message,
        public ?string $excerpt = null,
    )
    {
    }

    /**
     * @return array{
     *     field: string,
     *     fieldLabel: string,
     *     severity: string,
     *     category: string,
     *     source: string,
     *     message: string,
     *     excerpt: ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'fieldLabel' => $this->fieldLabel,
            'severity' => $this->severity,
            'category' => $this->category,
            'source' => $this->source,
            'message' => $this->message,
            'excerpt' => $this->excerpt,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $severity = (string)($data['severity'] ?? self::SEVERITY_WARNING);
        if (!in_array($severity, [self::SEVERITY_ALERT, self::SEVERITY_WARNING, self::SEVERITY_INFO], true)) {
            $severity = self::SEVERITY_WARNING;
        }

        $category = (string)($data['category'] ?? self::CATEGORY_INCOHERENCE);
        if (!in_array($category, [self::CATEGORY_FICTIF, self::CATEGORY_REMPLISSAGE, self::CATEGORY_INCOHERENCE, self::CATEGORY_COMPLETUDE_SEMANTIQUE], true)) {
            $category = self::CATEGORY_INCOHERENCE;
        }

        $source = (string)($data['source'] ?? self::SOURCE_DETERMINISTIC);
        if (!in_array($source, [self::SOURCE_DETERMINISTIC, self::SOURCE_AI], true)) {
            $source = self::SOURCE_DETERMINISTIC;
        }

        return new self(
            field: (string)($data['field'] ?? 'global'),
            fieldLabel: (string)($data['fieldLabel'] ?? 'Général'),
            severity: $severity,
            category: $category,
            source: $source,
            message: (string)($data['message'] ?? ''),
            excerpt: isset($data['excerpt']) && $data['excerpt'] !== '' ? (string)$data['excerpt'] : null,
        );
    }

    public function getCategoryLabel(): string
    {
        return match ($this->category) {
            self::CATEGORY_FICTIF => 'Contenu fictif / placeholder',
            self::CATEGORY_REMPLISSAGE => 'Contenu de remplissage / faible densité',
            self::CATEGORY_INCOHERENCE => 'Incohérence de contenu',
            self::CATEGORY_COMPLETUDE_SEMANTIQUE => 'Complétude sémantique',
            default => 'Point d\'attention',
        };
    }
}
