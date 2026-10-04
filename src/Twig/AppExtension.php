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
use App\Entity\Help;
use App\Enums\BadgeEnumInterface;
use App\Enums\CentreGestionEnum;
use App\Presenter\BadgePresenter;
use App\Repository\HelpRepository;
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
        private readonly HelpRepository        $helpRepository,
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
            new TwigFilter('badgeBooleanDto', $this->badgeBooleanDto(...)),
            new TwigFilter('badgeTypeCentreDto', $this->badgeTypeCentreDto(...)),
            new TwigFilter('centre', $this->centre(...), ['is_safe' => ['html']]),
            new TwigFilter('displayOrBadge', $this->displayOrBadge(...), ['is_safe' => ['html']]),
            new TwigFilter('etatRemplissage', $this->etatRemplissage(...), ['is_safe' => ['html']]),
            new TwigFilter('printTexte', $this->printTexte(...), ['is_safe' => ['html']]),
            new TwigFilter('filtreHeures', $this->filtreHeures(...), ['is_safe' => ['html']]),
            new TwigFilter('badgeEnumDto', $this->badgeEnumDto(...)),
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

    public function badgeEnumDto(?BadgeEnumInterface $value): BadgeView
    {
        return $this->badgePresenter->fromEnum($value);
    }

    public function badgeStatusDto(?string $value): BadgeView
    {
        return $this->badgePresenter->fromStatus($value);
    }

    public function displayOrBadge(?string $value): string
    {
        return ($value !== null && trim($value) !== '') ? $value : '<span class="badge bg-danger">Non renseigné</span>';
    }

    public function filtreHeures(float|string|null $heures): string
    {
        if (null === $heures) {
            return '';
        }

        if (is_string($heures)) {
            return $heures;
        }

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

    public function getPageHelp(string $route): ?Help
    {
        return $this->helpRepository->findOneBy(['routeSlug' => $route, 'isActive' => true]);
    }
}
