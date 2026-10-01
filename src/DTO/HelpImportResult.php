<?php

declare(strict_types=1);

namespace App\DTO;

/** Bilan d'un import d'archive d'aides / FAQ / images (HelpTransferService). */
final class HelpImportResult
{
    public int $helpsCreated = 0;
    public int $helpsUpdated = 0;
    public int $helpsSkipped = 0;

    public int $faqsCreated = 0;
    public int $faqsUpdated = 0;
    public int $faqsSkipped = 0;

    public int $imagesCreated = 0;
    public int $imagesUpdated = 0;
    public int $imagesSkipped = 0;

    /** @var string[] */
    public array $warnings = [];

    public function summary(): string
    {
        return sprintf(
            'Aides : %d créée(s), %d mise(s) à jour, %d ignorée(s) — FAQ : %d créée(s), %d mise(s) à jour, %d ignorée(s) — Images : %d ajoutée(s), %d mise(s) à jour, %d ignorée(s).',
            $this->helpsCreated, $this->helpsUpdated, $this->helpsSkipped,
            $this->faqsCreated, $this->faqsUpdated, $this->faqsSkipped,
            $this->imagesCreated, $this->imagesUpdated, $this->imagesSkipped,
        );
    }
}
