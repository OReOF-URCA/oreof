<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/TypeDiplome/McccInterface.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 29/05/2025 15:48
 */

namespace App\TypeDiplome;

use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\HttpFoundation\InputBag;

interface McccInterface
{
    public function clearMcccs(ElementConstitutif|FicheMatiere $objet): void;

    public function getMcccs(ElementConstitutif|FicheMatiere $elementConstitutif): array|Collection;

    public function saveMcccs(ElementConstitutif|FicheMatiere $elementConstitutif, InputBag $request): void;
}
