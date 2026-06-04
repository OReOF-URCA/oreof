<?php

namespace App\TypeDiplome\Licence;

final class DeustHandler extends AbstractLicenceHandler
{
    public function getLibelleCourt(): string
    {
        return 'DEUST';
    }
}
