<?php

namespace App\TypeDiplome\Licence;

final class MasterHandler extends AbstractLicenceHandler
{
    public function getLibelleCourt(): string
    {
        return 'M';
    }
}
