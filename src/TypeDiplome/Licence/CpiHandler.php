<?php

namespace App\TypeDiplome\Licence;

final class CpiHandler extends AbstractLicenceHandler
{
    public function getLibelleCourt(): string
    {
        return 'CPI';
    }
}
