<?php

declare(strict_types=1);

namespace App\DTO\Comparison;

use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;

final class EcDiff
{
    public const STATUS_IDENTICAL = 'identical';
    public const STATUS_MODIFIED = 'modified';
    public const STATUS_MISSING_IN_N = 'missing_in_n';
    public const STATUS_ADDED_IN_N = 'added_in_n';

    public const ATTACH_OK = 'ok';
    public const ATTACH_MISSING_FICHE = 'missing_fiche';
    public const ATTACH_MISMATCHED_FICHE = 'mismatched_fiche';
    public const ATTACH_NEW_FICHE = 'new_fiche';
    public const ATTACH_NONE = 'none';

    /**
     * @param array<string, FieldDiff> $differences
     */
    public function __construct(
        public readonly string $status,
        public readonly ?string $code,
        public readonly string $libelle,
        public readonly ?int $ordre,
        public readonly ?ElementConstitutif $ecN1,
        public readonly ?ElementConstitutif $ecN,
        public readonly string $attachmentStatus,
        public readonly ?string $attachmentMessage,
        public readonly ?FicheMatiere $ficheN1,
        public readonly ?FicheMatiere $ficheN,
        public readonly array $differences = [],
        public readonly bool $isOriginLinkBroken = false,
    ) {
    }

    public function isIdentical(): bool
    {
        return $this->status === self::STATUS_IDENTICAL && $this->attachmentStatus === self::ATTACH_OK;
    }

    public function hasAttachmentError(): bool
    {
        return $this->attachmentStatus === self::ATTACH_MISSING_FICHE
            || $this->attachmentStatus === self::ATTACH_MISMATCHED_FICHE;
    }

    public function hasDifferences(): bool
    {
        return !$this->isIdentical();
    }
}
