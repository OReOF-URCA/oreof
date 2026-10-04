<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Twig/TypeOfExtension.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Twig;

use App\Entity\ElementConstitutif;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class TypeOfExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('isElementConstitutif', $this->isElementConstitutif(...)),
        ];
    }

    public function isElementConstitutif(mixed $value): bool
    {
        return $value instanceof ElementConstitutif;
    }
}
