<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Twig/HistoriqueExtension.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 19/07/2023 16:56
 */

namespace App\Twig;

use App\Classes\ValidationProcess;
use App\Classes\ValidationProcessChangeRf;
use App\Classes\ValidationProcessFicheMatiere;
use App\Entity\Historique;
use App\Entity\HistoriqueFicheMatiere;
use App\Entity\HistoriqueFormation;
use App\Entity\HistoriqueParcours;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class HistoriqueExtension extends AbstractExtension
{
    public const TRADUCTIONS = [
        'conseil' => 'soumis_conseil',
        'publication' => 'valide_cfvu',
        'cfvu' => 'soumis_cfvu',
        'ses' => 'soumis_central',
        'dpe' => 'soumis_dpe_composante',
        'parcours' => 'en_cours_redaction',
        'parcours_rf' => 'soumis_parcours',
        'publie' => 'publie',
    ];

    public function __construct(
        private readonly ValidationProcess             $validationProcess,
        private readonly ValidationProcessChangeRf     $validationProcessChangeRf,
        private readonly ValidationProcessFicheMatiere $validationProcessFicheMatiere,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('etapeLabel', $this->etapeLabel(...)),
            new TwigFilter('etapeParams', $this->etapeParams(...)),
            new TwigFilter('etapeIcone', $this->etapeIcone(...)),
            new TwigFilter('etapeVariant', $this->etapeVariant(...)),
            new TwigFilter('isParcours', $this->isParcours(...)),
        ];
    }

    public function isParcours(HistoriqueParcours|HistoriqueFormation|HistoriqueFicheMatiere $historique): bool
    {
        return $historique instanceof HistoriqueParcours;
    }

    public function etapeLabel(string $etape, string $process = 'formation'): string
    {
        if (str_starts_with($etape, 'changeRf.')) {
            $etape = str_replace('changeRf.', '', $etape);
            return 'changeRf.'.$this->validationProcessChangeRf->getEtapeCle($etape, 'label');
        }

        if ($etape === 'cloture') {
            return 'cloture';
        }

        if (str_starts_with($etape, 'publie')) {
            return 'publie';
        }

        if ($process === 'formation' || $process === 'parcours') {
            if (array_key_exists($etape, self::TRADUCTIONS)) {
                $etape = self::TRADUCTIONS[$etape];
            }
            return $this->validationProcess->getEtapeCle($etape, 'label');
        }



        return $this->validationProcessFicheMatiere->getEtapeCle($etape, 'label');
    }

    public function etapeParams(HistoriqueParcours|HistoriqueFormation|HistoriqueFicheMatiere $historique): array
    {
        if ($historique instanceof HistoriqueParcours) {
            return
                ['%parcours%' => $historique->getParcours()?->getDisplay(),
                    '%date%' => $historique->getDate()?->format('d/m/Y'),
                    '%formation%' => $historique->getParcours()?->getFormation()?->getDisplayLong()];
        }

        if ($historique instanceof HistoriqueFormation) {
            return [
                '%formation%' => $historique->getFormation()?->getDisplayLong(),
                '%date%' => $historique->getDate()?->format('d/m/Y'),
            ];
        }

        if ($historique instanceof HistoriqueFicheMatiere) {
            return [
                '%formation%' => $historique->getFicheMatiere()?->getLibelle(),
                '%date%' => $historique->getDate()?->format('d/m/Y'),
            ];
        }
    }

    public function etapeVariant(Historique $historique): string
    {
        return match ($historique->getEtat()) {
            'valide' => 'success',
            'refuse' => 'danger',
            'info' => 'info',
            'default' => 'warning', // dont laisser passer
        };
    }

    public function etapeIcone(string $etape, string $process = 'formation'): string
    {
        if (str_starts_with($etape, 'changeRf.')) {
            return 'flowbite:arrows-repeat-outline';
        }

        if ($etape === 'change_rf_co' || $etape === 'change_rf') {
            return 'flowbite:arrows-repeat-outline';
        }

        if ($etape === 'cloture') {
            return 'flowbite:close-outline';
        }

        if (str_starts_with($etape, 'publie')) {
            return 'flowbite:bullhorn-outline';
        }

        if ($process === 'formation' || $process === 'parcours') {
            if (array_key_exists($etape, self::TRADUCTIONS)) {
                $etape = self::TRADUCTIONS[$etape];
            }
            return $this->validationProcess->getEtapeCle($etape, 'icon');
        }
        return $this->validationProcessFicheMatiere->getEtapeCle($etape, 'icon');
    }
}
