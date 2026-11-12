<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Tutoriel;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Vignette d'une vidéo tutoriel (galerie /tutoriels et « Autres vidéos »).
 *
 * <twig:TutorielCard :tutoriel="tutoriel" />
 */
#[AsTwigComponent('TutorielCard', template: 'components/tutoriel_card.html.twig')]
final class TutorielCard
{
    public Tutoriel $tutoriel;

    /** Affiche la description sous le titre */
    public bool $withDescription = true;
}
