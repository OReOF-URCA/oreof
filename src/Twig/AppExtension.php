<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Twig/AppExtension.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Twig;

use App\DTO\BadgeView;
use App\Entity\UeMutualisable;
use App\Entity\UserProfil;
use App\Enums\BadgeEnumInterface;
use App\Enums\CentreGestionEnum;
use App\Presenter\BadgePresenter;
use App\Utils\Tools;
use DateTimeInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Class AppExtension.
 */
class AppExtension extends AbstractExtension
{
    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly BadgePresenter        $badgePresenter,
    )
    {

    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('url', $this->url(...)),
            new TwigFilter('basename', $this->basename(...)),
            new TwigFilter('tel_format', $this->telFormat(...)),
            new TwigFilter('mailto', $this->mailto(...), ['is_safe' => ['html']]),
            new TwigFilter('open_url', $this->openUrl(...), ['is_safe' => ['html']]),
            new TwigFilter('dateFr', $this->dateFr(...), ['is_safe' => ['html']]),
            new TwigFilter('dateTimeFr', $this->dateTimeFr(...), ['is_safe' => ['html']]),
            new TwigFilter('rncp_link', $this->rncpLink(...), ['is_safe' => ['html']]),
            // new TwigFilter('badgeBoolean', $this->badgeBoolean(...), ['is_safe' => ['html']]),  //deprecated
            new TwigFilter('badgeBooleanDto', $this->badgeBooleanDto(...)),
            // new TwigFilter('badgeDroits', $this->badgeDroits(...), ['is_safe' => ['html']]),
            //new TwigFilter('badgeTypeCentre', $this->badgeTypeCentre(...), ['is_safe' => ['html']]),  //deprecated
            new TwigFilter('badgeTypeCentreDto', $this->badgeTypeCentreDto(...)),
            new TwigFilter('centre', $this->centre(...), ['is_safe' => ['html']]),
            new TwigFilter('displayOrBadge', $this->displayOrBadge(...), ['is_safe' => ['html']]),
            new TwigFilter('etatRemplissage', $this->etatRemplissage(...), ['is_safe' => ['html']]),
            new TwigFilter('printTexte', $this->printTexte(...), ['is_safe' => ['html']]),
            new TwigFilter('filtreHeures', $this->filtreHeures(...), ['is_safe' => ['html']]),
            //   new TwigFilter('badgeEnum', $this->badgeEnum(...), ['is_safe' => ['html']]),  //deprecated
            new TwigFilter('badgeEnumDto', $this->badgeEnumDto(...)),
            //  new TwigFilter('badgeStatus', $this->badgeStatus(...), ['is_safe' => ['html']]), //deprecated
            new TwigFilter('badgeStatusDto', $this->badgeStatusDto(...)),
            new TwigFilter('startWith', $this->startWith(...), ['is_safe' => ['html']]),
            new TwigFilter('isUeUtilisee', $this->isUeUtilisee(...), ['is_safe' => ['html']]),
        ];
    }

    public function basename(string $path): string
    {
        return basename($path);
    }
    public function isUeUtilisee(UeMutualisable $ue): bool
    {
        foreach ($ue->getUes() as $u) {
            foreach ($u->getSemestre()?->getSemestreParcours() as $semestre) {
                if ($semestre->getParcours() !== null) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @deprecated */
    public function badgeEnum(?BadgeEnumInterface $value): string
    {
        @trigger_error(__METHOD__ . '() is deprecated, use the badgeEnumDto filter and the Twig Badge component instead.', E_USER_DEPRECATED);

        return $this->renderLegacyBadge($this->badgeEnumDto($value));
    }

    public function badgeEnumDto(?BadgeEnumInterface $value): BadgeView
    {
        return $this->badgePresenter->fromEnum($value);
    }

    /** @deprecated */
    public function badgeStatus(?string $value): string
    {
        @trigger_error(__METHOD__ . '() is deprecated, use the badgeStatusDto filter and the Twig Badge component instead.', E_USER_DEPRECATED);

        return $this->renderLegacyBadge($this->badgeStatusDto($value));
    }

    public function badgeStatusDto(?string $value): BadgeView
    {
        return $this->badgePresenter->fromStatus($value);
    }

    public function displayOrBadge(?string $value): string
    {
        return ($value !== null && trim($value) !== '') ? $value : '<span class="badge bg-danger">Non renseigné</span>';
    }

    public function filtreHeures(?float $heures): string
    {
        return Tools::filtreHeures($heures);
    }

    public function printTexte(?string $texte): string
    {
        if (null === $texte) {
            return '';
        }

        $texte = nl2br(trim($texte));

        //retirer <div> de début et de fin
        if (str_starts_with($texte, '<div>') && str_ends_with($texte, '</div>')) {
            $texte = mb_substr($texte, 5);
            $texte = mb_substr($texte, 0, -6);
        }

        if (str_ends_with($texte, '<br>')) {
            $texte = mb_substr($texte, 0, -4);
        }

        if (str_ends_with($texte, '<br/>')) {
            $texte = mb_substr($texte, 0, -5);
        }

        if (str_ends_with($texte, '<br />')) {
            $texte = mb_substr($texte, 0, -6);
        }

        return '<div>' . $texte . '</div>';
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('displaySort', $this->displaySort(...), ['is_safe' => ['html']]),
            new TwigFunction('getDirection', $this->getDirection(...), ['is_safe' => ['html']]),
            new TwigFunction('get_page_help', [$this, 'getPageHelp']),

        ];
    }

    public function startWith(string $haystack, string $needle): bool
    {
        return str_starts_with($haystack, $needle);
    }

    public function displaySort(string $field, ?string $sort, ?string $direction): ?string
    {
        if ($field === $sort) {
            if ($direction === 'asc') {
                return '<twig:UX:Icon name="icon:sort-up" class="h-4 w-4" />';
            }
            if ($direction === 'desc') {
                return '<twig:UX:Icon name="icon:sort-down" class="h-4 w-4" />';
            }
        }

        return '<twig:UX:Icon name="icon:sort" class="h-4 w-4" />';
    }

    public function url(string $url): string
    {
        $baseurl = $this->parameterBag->get('BASE_URL');
        return $baseurl . $url;
    }

    public function getDirection(string $field, ?string $sort, ?string $direction): ?string
    {
        if ($field === $sort) {
            return $direction === 'asc' ? 'desc' : 'asc';
        }

        return 'asc';
    }

    /** @deprecated */
    public function badgeBoolean(?bool $value = false): string
    {
        @trigger_error(__METHOD__ . '() is deprecated, use the badgeBooleanDto filter and the Twig Badge component instead.', E_USER_DEPRECATED);

        return $this->renderLegacyBadge($this->badgeBooleanDto($value));
    }

    public function badgeBooleanDto(?bool $value = false): BadgeView
    {
        return $this->badgePresenter->fromBoolean($value);
    }

    public function etatRemplissage(array $onglets, int $step, string $prefix = ''): string
    {
        if (array_key_exists($step, $onglets)) {
            return '<span class="state state-' . $onglets[$step]->badge() . '" id="' . $prefix . '_onglet' . $step . '"></span>';
        }

        return '';
    }

    /** @deprecated */
    public function badgeDroits(array $droits): string
    {
        $html = '';
        foreach ($droits as $droit) {
            if ($droit !== 'ROLE_LECTEUR') {
                $html .= '<span class="badge bg-success me-1">' . $droit . '</span>';
            }
        }

        return $html;
    }

    /** @deprecated */
    public function badgeTypeCentre(UserProfil $userProfil): string
    {
        @trigger_error(__METHOD__ . '() is deprecated, use the badgeTypeCentreDto filter and the Twig Badge component instead.', E_USER_DEPRECATED);

        return $this->renderLegacyBadge($this->badgeTypeCentreDto($userProfil));
    }

    public function badgeTypeCentreDto(UserProfil $userProfil): BadgeView
    {
        return $this->badgePresenter->fromEnum($userProfil->getProfil()?->getCentre(), 'Inconnu', 'danger');
    }

    public function centre(UserProfil $userProfil): ?string
    {
        return match ($userProfil->getProfil()?->getCentre()) {
            CentreGestionEnum::CENTRE_GESTION_COMPOSANTE => $userProfil->getComposante()?->getLibelle(),
            CentreGestionEnum::CENTRE_GESTION_ETABLISSEMENT => $userProfil->getEtablissement()?->getLibelle(),
            CentreGestionEnum::CENTRE_GESTION_FORMATION => $userProfil->getFormation()?->getDisplayLong(),
            CentreGestionEnum::CENTRE_GESTION_PARCOURS => $userProfil->getParcours()->getFormation()?->getDisplayLong() . '. Parcours : ' . $userProfil->getParcours()?->getDisplay(),
            default => '<span class="badge bg-danger me-1 text-wrap">Inconnu</span>',
        };
    }

    public function dateFr(?DateTimeInterface $value): string
    {
        return $value !== null ? $value->format('d/m/Y') : 'Erreur';
    }

    public function dateTimeFr(?DateTimeInterface $value): string
    {
        return $value !== null ? $value->format('d/m/Y H:i') : 'Erreur';
    }

    public function rncpLink(?string $code): string
    {
        if (str_starts_with($code, 'rncp')) {
            $code = mb_substr($code, 4, mb_strlen($code));
        }

        return '<a href="https://www.francecompetences.fr/recherche/rncp/' . $code . '" target="_blank">' . $code . ' <i class="fal
                            fa-arrow-up-right-from-square"></i></a>&nbsp;';
    }

    public function mailto(?string $email): string
    {
        if (null === $email) {
            return '';
        }

        return '<a href="mailto:' . $email . '" target="_blank">' . $email . ' <i class="fal
                            fa-arrow-up-right-from-square"></i></a>&nbsp;';
    }

    public function openUrl(?string $url): string
    {
        if (null === $url) {
            return '';
        }

        return '<a href="' . $url . '" target="_blank">' . $url . ' <i class="fal
                            fa-arrow-up-right-from-square"></i></a>&nbsp;';
    }

    public function telFormat(?string $number): ?string
    {
        return Tools::telFormat($number);
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
            'secondary' => 'border border-secondary-300 bg-secondary-100 text-secondary-700',
        ];

        $classes = trim('inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ' . ($colors[$badge->variant] ?? $colors['secondary']));

        return sprintf('<span class="%s">%s</span>', $classes, $label);
    }

    public function getPageHelp(string $route)
    {
        return $this->helpRepository->findOneBy(['routeSlug' => $route, 'isActive' => true]);
    }
}
