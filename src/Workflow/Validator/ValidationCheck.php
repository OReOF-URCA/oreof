<?php

declare(strict_types=1);

namespace App\Workflow\Validator;

/**
 * Synthèse d'un groupe de contrôles réellement exécuté par un validateur.
 */
final readonly class ValidationCheck
{
    public const STATUS_PASSED = 'passed';
    public const STATUS_WARNING = 'warning';
    public const STATUS_FAILED = 'failed';

    public function __construct(
        public string $code,
        public string $label,
        public string $status,
        public int $errorCount = 0,
        public int $warningCount = 0,
    ) {
    }

    /**
     * @param array{errors: array, warnings: array} $result
     */
    public static function fromResult(string $code, string $label, array $result): self
    {
        $errorCount = count($result['errors']);
        $warningCount = count($result['warnings']);

        return new self(
            $code,
            $label,
            $errorCount > 0
                ? self::STATUS_FAILED
                : ($warningCount > 0 ? self::STATUS_WARNING : self::STATUS_PASSED),
            $errorCount,
            $warningCount,
        );
    }

    /** @return array{code: string, label: string, status: string, errorCount: int, warningCount: int} */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'label' => $this->label,
            'status' => $this->status,
            'errorCount' => $this->errorCount,
            'warningCount' => $this->warningCount,
        ];
    }
}
