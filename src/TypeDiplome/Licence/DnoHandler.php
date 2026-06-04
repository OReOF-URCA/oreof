<?php

namespace App\TypeDiplome\Licence;

final class DnoHandler extends AbstractLicenceHandler
{
    public function getLibelleCourt(): string
    {
        return 'DNO';
    }
}
