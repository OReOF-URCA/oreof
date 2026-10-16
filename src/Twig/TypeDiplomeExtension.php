<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Twig/TypeDiplomeExtension.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Twig;

use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\TypeDiplome;
use App\TypeDiplome\TypeDiplomeHandlerInterface;
use App\TypeDiplome\TypeDiplomeResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class TypeDiplomeExtension extends AbstractExtension
{
    public function __construct(
        private readonly ?TypeDiplomeResolver $typeDiplomeResolver = null,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('typeDiplome', $this->typeDiplome(...), ['is_safe' => ['html']]),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('type_diplome_handler', $this->typeDiplomeHandler(...)),
        ];
    }

    public function typeDiplomeHandler(TypeDiplome|Formation|Parcours|null $subject): ?TypeDiplomeHandlerInterface
    {
        if ($subject === null || $this->typeDiplomeResolver === null) {
            return null;
        }

        try {
            return $this->typeDiplomeResolver->get($subject);
        } catch (\Throwable) {
            return null;
        }
    }

    public function typeDiplome(?TypeDiplome $value): string
    {
        return ($value !== null && $value->getLibelle() !== null) ? $value->getLibelle() : '<span class="badge bg-warning">Non défini</span>';
    }
}
