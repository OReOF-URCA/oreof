<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Twig/SyntheseButtonsExtension.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 12/03/2026 10:12
 */

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Parcours;
use App\Enums\TypeModificationDpeEnum;
use App\Service\Synthese\Dto\SyntheseButtonsContext;
use App\Service\Synthese\SyntheseButtonsResolver;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class SyntheseButtonsExtension extends AbstractExtension
{
    public function __construct(
        private readonly SyntheseButtonsResolver       $resolver,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    )
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('synthese_buttons', $this->getButtons(...)),
        ];
    }

    public function getButtons(Parcours $parcours)
    {
        $lastDpe = $parcours->getDpeParcours()->last();
        $etat = $lastDpe?->getEtatValidation() ?? [];

        $context = new SyntheseButtonsContext(
            $lastDpe?->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION_MCCC_TEXTE, //si modif de maquette alors version,
            $this->authorizationChecker->isGranted('ROLE_ADMIN'),
            $etat === ['valide_a_publier' => 1] || $etat === ['publie' => 1],
            $parcours->getParcoursOrigineCopie() === null
        );

        return $this->resolver->resolve($parcours, $context);
    }
}

