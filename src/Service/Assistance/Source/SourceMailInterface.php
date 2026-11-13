<?php

namespace App\Service\Assistance\Source;

use App\DTO\Assistance\MailEntrant;
use Closure;
use DateTimeImmutable;

interface SourceMailInterface
{
    /**
     * @param (Closure(string): bool)|null $estConnu indique si un Message-ID est déjà intégré : la source peut
     *                                                alors éviter de télécharger ce mail
     *
     * @return iterable<MailEntrant>
     */
    public function lire(DateTimeImmutable $depuis, ?Closure $estConnu = null): iterable;
}
