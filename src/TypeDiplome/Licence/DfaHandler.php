<?php

namespace App\TypeDiplome\Licence;

final class DfaHandler extends AbstractLicenceHandler
{
    public function getLibelleCourt(): string
    {
        return 'DFA';
    }
}
