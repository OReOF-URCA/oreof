<?php

namespace App\Service\Assistance\Source;

use App\DTO\Assistance\MailEntrant;
use DateTimeImmutable;

interface SourceMailInterface
{
    /**
     * @return iterable<MailEntrant>
     */
    public function lire(DateTimeImmutable $depuis): iterable;
}
