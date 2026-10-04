<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Twig/BadgeDpeExtension.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Twig;

use App\DTO\BadgeView;
use App\Entity\FicheMatiere;
use App\Enums\TypeModificationDpeEnum;
use App\Presenter\BadgePresenter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Class AppExtension.
 */
class BadgeDpeExtension extends AbstractExtension
{
    public function __construct(private readonly BadgePresenter $badgePresenter)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('badgeDpeDto', $this->badgeDpeDto(...)),
            new TwigFilter('badgeTypeModificationDto', $this->badgeTypeModificationDto(...)),
            new TwigFilter('badgeStepDto', $this->badgeStepDto(...)),
            new TwigFilter('badgeEtatComposanteDto', $this->badgeEtatComposanteDto(...)),
            new TwigFilter('badgeFormationDto', $this->badgeFormationDto(...)),
            new TwigFilter('badgeEcDto', $this->badgeEcDto(...)),
            new TwigFilter('badgeFicheDto', $this->badgeFicheDto(...)),
            new TwigFilter('badgeDto', $this->badgeDto(...)),
            new TwigFilter('badgeValideDto', $this->badgeValideDto(...)),
            new TwigFilter('badgeChangeRfDto', $this->badgeChangeRfDto(...)),
            new TwigFilter('displayErreurs', $this->displayErreurs(...), ['is_safe' => ['html']]),
            new TwigFilter('isFicheValidable', $this->isFicheValidable(...), ['is_safe' => ['html']])
        ];
    }

    public function isFicheValidable(FicheMatiere $fiche, string $type): string
    {
        if ($fiche->getRemplissage()->calcul() < 100.0) {
            return 'disabled';
        }

        return match ($type) {
            'formation', 'parcours', 'dpe' => in_array('en_cours_redaction', $fiche->getEtatFiche()) || count($fiche->getEtatFiche()) === 0 ? '' : 'disabled',
            default => 'disabled',
        };
    }

    public function displayErreurs(?array $erreurs = []): string
    {
        if (null === $erreurs || 0 === count($erreurs)) {
            return '';
        }

        //retirer les cellules vides du tableau erreurs
        $erreurs = array_filter($erreurs, function ($erreur) {
            return !empty($erreur);
        });


        $texte = '<ul>';
        foreach ($erreurs as $erreur) {
            $texte .= '<li>' . $erreur . '</li>';
        }
        $texte .= '</ul>';
        return '<twig:UX:Icon name="icon:question:bold" class="h-4 w-4"
                   data-controller="tooltip"
                   aria-label="' . $texte . '"
                   title="' . $texte . '"></twig:UX:Icon>';
    }

    /**
     * @return list<BadgeView>
     */
    public function badgeEcDto(array $etatsEc): array
    {
        return $this->badgePresenter->fromEtatDpeStates($etatsEc);
    }

    public function badgeDto(string $texte, string $type): BadgeView
    {
        return $this->badgePresenter->fromText($texte, $type);
    }

    public function badgeValideDto(?string $etat): BadgeView
    {
        return $this->badgePresenter->fromValide($etat);
    }

    /**
     * @return list<BadgeView>
     */
    public function badgeFormationDto(array $etatsFormation): array
    {
        return $this->badgePresenter->fromEtatDpeStates($etatsFormation);
    }

    /**
     * @return list<BadgeView>
     */
    public function badgeEtatComposanteDto(array $etatsComposante): array
    {
        return $this->badgePresenter->fromEtatDpeStates($etatsComposante);
    }

    /**
     * @return list<BadgeView>
     */
    public function badgeDpeDto(array $etatsDpe): array
    {
        return $this->badgePresenter->fromEtatDpeStates($etatsDpe);
    }

    public function badgeTypeModificationDto(?TypeModificationDpeEnum $typeModificationDpe): BadgeView
    {
        return $this->badgePresenter->fromTypeModification($typeModificationDpe);
    }

    public function badgeStepDto(?bool $etatsDpe): BadgeView
    {
        return $this->badgePresenter->fromStep($etatsDpe);
    }

    /**
     * @return list<BadgeView>
     */
    public function badgeFicheDto(array $etatFiche): array
    {
        return $this->badgePresenter->fromEtatDpeStates($etatFiche);
    }

    /**
     * @return list<BadgeView>
     */
    public function badgeChangeRfDto(array $etatsEc): array
    {
        return $this->badgePresenter->fromEtatChangeRfStates($etatsEc);
    }
}
