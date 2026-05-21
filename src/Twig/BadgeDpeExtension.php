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
use App\Enums\EtatChangeRfEnum;
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
            new TwigFilter('badgeDpe', $this->badgeDpe(...), ['is_safe' => ['html']]),
            new TwigFilter('badgeDpeDto', $this->badgeDpeDto(...)),
            new TwigFilter('badgeTypeModification', $this->badgeTypeModification(...), ['is_safe' => ['html']]),
            new TwigFilter('badgeTypeModificationDto', $this->badgeTypeModificationDto(...)),
            new TwigFilter('badgeStep', $this->badgeStep(...), ['is_safe' => ['html']]),
            new TwigFilter('badgeStepDto', $this->badgeStepDto(...)),
            new TwigFilter('badgeEtatComposante', $this->badgeEtatComposante(...), ['is_safe' => ['html']]),
            new TwigFilter('badgeEtatComposanteDto', $this->badgeEtatComposanteDto(...)),
            new TwigFilter('badgeFormation', $this->badgeFormation(...), ['is_safe' => ['html']]),
            new TwigFilter('badgeFormationDto', $this->badgeFormationDto(...)),
            new TwigFilter('badgeEc', $this->badgeEc(...), ['is_safe' => ['html']]),
            new TwigFilter('badgeEcDto', $this->badgeEcDto(...)),
            new TwigFilter('badgeFiche', $this->badgeFiche(...), ['is_safe' => ['html']]),
            new TwigFilter('badgeFicheDto', $this->badgeFicheDto(...)),
            new TwigFilter('badge', $this->badge(...), ['is_safe' => ['html']]),
            new TwigFilter('badgeDto', $this->badgeDto(...)),
            new TwigFilter('badgeValide', $this->badgeValide(...), ['is_safe' => ['html']]),
            new TwigFilter('badgeValideDto', $this->badgeValideDto(...)),
            new TwigFilter('badgeChangeRf', $this->badgeChangeRf(...), ['is_safe' => ['html']]),
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

    public function badgeEc(array $etatsEc): string
    {
        @trigger_error(__METHOD__ . '() is deprecated, use the badgeEcDto filter and the Twig Badge component instead.', E_USER_DEPRECATED);

        return $this->renderLegacyBadges($this->badgeEcDto($etatsEc));
    }

    /**
     * @return list<BadgeView>
     */
    public function badgeEcDto(array $etatsEc): array
    {
        return $this->badgePresenter->fromEtatDpeStates($etatsEc);
    }

    public function badge(string $texte, string $type): string
    {
        @trigger_error(__METHOD__ . '() is deprecated, use the badgeDto filter and the Twig Badge component instead.', E_USER_DEPRECATED);

        return $this->renderLegacyBadge($this->badgeDto($texte, $type));
    }

    public function badgeDto(string $texte, string $type): BadgeView
    {
        return $this->badgePresenter->fromText($texte, $type);
    }

    public function badgeValide(?string $etat): string
    {
        @trigger_error(__METHOD__ . '() is deprecated, use the badgeValideDto filter and the Twig Badge component instead.', E_USER_DEPRECATED);

        return $this->renderLegacyBadge($this->badgeValideDto($etat));
    }

    public function badgeValideDto(?string $etat): BadgeView
    {
        return $this->badgePresenter->fromValide($etat);
    }

    public function badgeFormation(array $etatsFormation): string
    {
        @trigger_error(__METHOD__ . '() is deprecated, use the badgeFormationDto filter and the Twig Badge component instead.', E_USER_DEPRECATED);

        return $this->renderLegacyBadges($this->badgeFormationDto($etatsFormation));
    }

    /**
     * @return list<BadgeView>
     */
    public function badgeFormationDto(array $etatsFormation): array
    {
        return $this->badgePresenter->fromEtatDpeStates($etatsFormation);
    }

    public function badgeEtatComposante(array $etatsComposante): string
    {
        @trigger_error(__METHOD__ . '() is deprecated, use the badgeEtatComposanteDto filter and the Twig Badge component instead.', E_USER_DEPRECATED);

        return $this->renderLegacyBadges($this->badgeEtatComposanteDto($etatsComposante));
    }

    /**
     * @return list<BadgeView>
     */
    public function badgeEtatComposanteDto(array $etatsComposante): array
    {
        return $this->badgePresenter->fromEtatDpeStates($etatsComposante);
    }

    public function badgeDpe(array $etatsDpe): string
    {
        @trigger_error(__METHOD__ . '() is deprecated, use the badgeDpeDto filter and the Twig Badge component instead.', E_USER_DEPRECATED);

        return $this->renderLegacyBadges($this->badgeDpeDto($etatsDpe));
    }

    /**
     * @return list<BadgeView>
     */
    public function badgeDpeDto(array $etatsDpe): array
    {
        return $this->badgePresenter->fromEtatDpeStates($etatsDpe);
    }

    public function badgeTypeModification(?TypeModificationDpeEnum $typeModificationDpe): string
    {
        @trigger_error(__METHOD__ . '() is deprecated, use the badgeTypeModificationDto filter and the Twig Badge component instead.', E_USER_DEPRECATED);

        return $this->renderLegacyBadge($this->badgeTypeModificationDto($typeModificationDpe));
    }

    public function badgeTypeModificationDto(?TypeModificationDpeEnum $typeModificationDpe): BadgeView
    {
        return $this->badgePresenter->fromTypeModification($typeModificationDpe);
    }

    public function badgeStep(?bool $etatsDpe): string
    {
        @trigger_error(__METHOD__ . '() is deprecated, use the badgeStepDto filter and the Twig Badge component instead.', E_USER_DEPRECATED);

        return $this->renderLegacyBadge($this->badgeStepDto($etatsDpe));
    }

    public function badgeStepDto(?bool $etatsDpe): BadgeView
    {
        return $this->badgePresenter->fromStep($etatsDpe);
    }

    public function badgeFiche(array $etatFiche): string
    {
        @trigger_error(__METHOD__ . '() is deprecated, use the badgeFicheDto filter and the Twig Badge component instead.', E_USER_DEPRECATED);

        return $this->renderLegacyBadges($this->badgeFicheDto($etatFiche));
    }

    /**
     * @return list<BadgeView>
     */
    public function badgeFicheDto(array $etatFiche): array
    {
        return $this->badgePresenter->fromEtatDpeStates($etatFiche);
    }

    public function badgeChangeRf(array $etatsEc): string
    {
        @trigger_error(__METHOD__ . '() is deprecated, use the badgeChangeRfDto filter and the Twig Badge component instead.', E_USER_DEPRECATED);

        return $this->renderLegacyBadges($this->badgeChangeRfDto($etatsEc));
    }

    /**
     * @return list<BadgeView>
     */
    public function badgeChangeRfDto(array $etatsEc): array
    {
        return $this->badgePresenter->fromEtatChangeRfStates($etatsEc);
    }

    private function renderLegacyBadge(BadgeView $badge): string
    {
        $label = htmlspecialchars($badge->label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $colors = [
            'primary' => 'border border-blue-300 bg-blue-50 text-blue-700',
            'success' => 'border border-emerald-300 bg-emerald-50 text-emerald-700',
            'warning' => 'border border-amber-300 bg-amber-50 text-amber-700',
            'danger' => 'border border-rose-300 bg-rose-50 text-rose-700',
            'info' => 'border border-cyan-300 bg-cyan-50 text-cyan-700',
            'secondary' => 'border border-slate-300 bg-slate-100 text-slate-700',
        ];

        $classes = trim('me-1 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ' . ($colors[$badge->variant] ?? $colors['secondary']));

        return sprintf('<span class="%s">%s</span>', $classes, $label);
    }

    /**
     * @param list<BadgeView> $badges
     */
    private function renderLegacyBadges(array $badges): string
    {
        return implode('', array_map($this->renderLegacyBadge(...), $badges));
    }
}
