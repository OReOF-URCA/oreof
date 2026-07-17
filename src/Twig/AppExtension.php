<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Twig/AppExtension.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Twig;

use App\Entity\UeMutualisable;
use App\Entity\UserProfil;
use App\Enums\BadgeEnumInterface;
use App\Enums\CentreGestionEnum;
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
    public function __construct(private readonly ParameterBagInterface $parameterBag)
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
            new TwigFilter('badgeBoolean', $this->badgeBoolean(...), ['is_safe' => ['html']]),
            new TwigFilter('badgeDroits', $this->badgeDroits(...), ['is_safe' => ['html']]),
            new TwigFilter('badgeTypeCentre', $this->badgeTypeCentre(...), ['is_safe' => ['html']]),
            new TwigFilter('centre', $this->centre(...), ['is_safe' => ['html']]),
            new TwigFilter('displayOrBadge', $this->displayOrBadge(...), ['is_safe' => ['html']]),
            new TwigFilter('etatRemplissage', $this->etatRemplissage(...), ['is_safe' => ['html']]),
            new TwigFilter('printTexte', $this->printTexte(...), ['is_safe' => ['html']]),
            new TwigFilter('filtreHeures', $this->filtreHeures(...), ['is_safe' => ['html']]),
            new TwigFilter('badgeEnum', $this->badgeEnum(...), ['is_safe' => ['html']]),
            new TwigFilter('badgeStatus', $this->badgeStatus(...), ['is_safe' => ['html']]),
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

    public function badgeEnum(?BadgeEnumInterface $value): string
    {
        return ($value !== null) ? '<span class="badge '.$value->getBadge().'">' . $value->getLibelle() . '</span>' : '<span class="badge bg-danger">Non renseigné</span>';
    }

    public function badgeStatus(?string $value): string
    {
        //si finished => vert, si in_progress => orange, si error => rouge, sinon gris
        return match ($value) {
            'finished' => '<span class="badge bg-success">Terminé</span>',
            'running' => '<span class="badge bg-warning">En cours</span>',
            'error' => '<span class="badge bg-danger">Erreur</span>',
            default => '<span class="badge bg-secondary">Inconnu</span>',
        };
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

        ];
    }

    public function startWith(string $haystack, string $needle): bool
    {
        return str_starts_with($haystack, $needle);
    }

    public function displaySort(string $field, ?string $sort, ?string $direction): ?string
    {
        if ($field === $sort) {
            return '<i class="fal fa-caret-' . ($direction === 'asc' ? 'up' : 'down') . ' fa-lg"></i>';
        }

        return '<i class="fal fa-sort fa-lg"></i>';
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

    public function badgeBoolean(?bool $value = false): string
    {
        return $value ? '<span class="badge bg-success">Oui</span>' : '<span class="badge bg-danger">Non</span>';
    }

    public function etatRemplissage(array $onglets, int $step, string $prefix = ''): string
    {
        if (array_key_exists($step, $onglets)) {
            return '<span class="state state-' . $onglets[$step]->badge() . '" id="' . $prefix . '_onglet' . $step . '"></span>';
        }

        return '';
    }

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

    public function badgeTypeCentre(UserProfil $userProfil): string
    {
        return match ($userProfil->getProfil()?->getCentre()) {
            CentreGestionEnum::CENTRE_GESTION_COMPOSANTE => '<span class="badge bg-quaternary me-1 text-wrap">Composante</span>',
            CentreGestionEnum::CENTRE_GESTION_ETABLISSEMENT => '<span class="badge bg-info me-1 text-wrap">Etablissement</span>',
            CentreGestionEnum::CENTRE_GESTION_FORMATION => '<span class="badge bg-success me-1 text-wrap">Formation</span>',
            CentreGestionEnum::CENTRE_GESTION_PARCOURS => '<span class="badge bg-secondary me-1 text-wrap">Parcours</span>',
            default => '<span class="badge bg-danger me-1 text-wrap">Inconnu</span>',
        };
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
}
