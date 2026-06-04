<?php

namespace App\TypeDiplome\Licence;

final class MeefHandler extends AbstractLicenceHandler
{
    public function getLibelleCourt(): string
    {
        return 'MEEF';
    }
}
