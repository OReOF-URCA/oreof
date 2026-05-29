<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Service/Synthese/SyntheseButtonsResolver.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 12/03/2026 10:12
 */

declare(strict_types=1);

namespace App\Service\Synthese;

use App\Entity\Parcours;
use App\Service\Synthese\Dto\SyntheseButtonSet;
use App\Service\Synthese\Dto\SyntheseButtonsContext;
use LogicException;

final class SyntheseButtonsResolver
{
    /** @param iterable<SyntheseButtonsProviderInterface> $providers */
    public function __construct(private readonly iterable $providers)
    {
    }

    public function resolve(Parcours $parcours, SyntheseButtonsContext $context): SyntheseButtonSet
    {
        $typeDiplomeCode = $parcours->getFormation()?->getTypeDiplome()?->getLibelleCourt() ?? '';
        $fallback = null;

        foreach ($this->providers as $provider) {
            if ($provider->supports($typeDiplomeCode, $context)) {
                if ($provider instanceof DefaultSyntheseButtonsProvider) {
                    $fallback = $provider;
                    continue;
                }

                return $provider->getButtons($parcours, $context);
            }
        }

        if ($fallback instanceof DefaultSyntheseButtonsProvider) {
            return $fallback->getButtons($parcours, $context);
        }

        throw new LogicException(sprintf('Aucun provider de boutons de synthèse pour le type "%s".', $typeDiplomeCode));
    }
}

