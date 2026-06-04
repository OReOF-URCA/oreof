<?php

namespace App\TypeDiplome\Licence;

final class DiplomeIngenieurHandler extends AbstractLicenceHandler
{
    public function getLibelleCourt(): string
    {
        return 'DI';
    }
}
