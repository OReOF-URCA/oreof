<?php

namespace App\TypeDiplome\Licence;

final class DfgHandler extends AbstractLicenceHandler
{
    public function getLibelleCourt(): string
    {
        return 'DFG';
    }
}
