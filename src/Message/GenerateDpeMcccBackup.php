<?php

declare(strict_types=1);

namespace App\Message;

final readonly class GenerateDpeMcccBackup
{
    public function __construct(public int $dpeParcoursId)
    {
    }
}
