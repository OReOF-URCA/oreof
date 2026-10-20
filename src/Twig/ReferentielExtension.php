<?php
namespace App\Twig;

use App\Referentiel\ReferentielManager;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ReferentielExtension extends AbstractExtension
{
    public function __construct(private readonly ReferentielManager $manager) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('referentiel_label', $this->manager->label(...)),
            new TwigFunction('referentiel_valeurs', $this->manager->getValeurs(...)),
        ];
    }
}
