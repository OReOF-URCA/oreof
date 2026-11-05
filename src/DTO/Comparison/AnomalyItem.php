<?php

declare(strict_types=1);

namespace App\DTO\Comparison;

final class AnomalyItem
{
    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_INFO = 'info';

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public readonly string $severity,
        public readonly string $category,
        public readonly string $message,
        public readonly array $context = [],
    ) {
    }

    public function isError(): bool
    {
        return $this->severity === self::SEVERITY_ERROR;
    }

    public function isWarning(): bool
    {
        return $this->severity === self::SEVERITY_WARNING;
    }

    public function isInfo(): bool
    {
        return $this->severity === self::SEVERITY_INFO;
    }
}
