<?php

namespace App\Service\Assistance;

use DateTimeInterface;

/**
 * Résume en une ligne l'ouverture des modules d'une campagne (statuts de CampagneAccessibilityService::getModuleStatus)
 * pour le panneau de contexte d'une demande d'assistance : seuls les modules qui font exception sont détaillés.
 */
class ResumePeriodesSaisie
{
    /**
     * @param list<array<string, mixed>> $statuts
     *
     * @return array{etat: 'ouvertes'|'fermees'|'mixte', resume: string, details: list<array<string, mixed>>, active: bool}
     */
    public function resumer(array $statuts): array
    {
        $ouverts = array_values(array_filter($statuts, static fn (array $s) => $s['isOpen'] === true));
        $fermes = array_values(array_filter($statuts, static fn (array $s) => $s['isOpen'] !== true));
        $prochaineOuverture = $this->plusTot(array_map(static fn (array $s) => $s['nextOpeningDate'] ?? null, $fermes));
        $active = $ouverts !== [] || $prochaineOuverture !== null;

        if ($ouverts === []) {
            return [
                'etat' => 'fermees',
                'resume' => $prochaineOuverture !== null
                    ? sprintf('Toutes les saisies sont fermées (prochaine ouverture le %s).', $prochaineOuverture->format('d/m/Y'))
                    : 'Toutes les saisies sont fermées.',
                'details' => [],
                'active' => $active,
            ];
        }

        $fermetures = array_map(static fn (array $s) => $s['closingDate'] ?? null, $ouverts);
        $dates = array_unique(array_map(static fn (?DateTimeInterface $d) => $d?->format('d/m/Y'), $fermetures));
        $echeance = match (true) {
            count($dates) === 1 && $dates[array_key_first($dates)] !== null => sprintf(' jusqu\'au %s', $dates[array_key_first($dates)]),
            ($premiere = $this->plusTot($fermetures)) !== null => sprintf(' (première fermeture le %s)', $premiere->format('d/m/Y')),
            default => '',
        };

        if ($fermes === []) {
            return ['etat' => 'ouvertes', 'resume' => 'Toutes les saisies sont ouvertes' . $echeance . '.', 'details' => [], 'active' => $active];
        }

        return ['etat' => 'mixte', 'resume' => 'Saisies ouvertes' . $echeance . ', sauf :', 'details' => $fermes, 'active' => $active];
    }

    /**
     * @param list<mixed> $dates
     */
    private function plusTot(array $dates): ?DateTimeInterface
    {
        $dates = array_filter($dates, static fn (mixed $d) => $d instanceof DateTimeInterface);
        if ($dates === []) {
            return null;
        }
        usort($dates, static fn (DateTimeInterface $a, DateTimeInterface $b) => $a <=> $b);

        return $dates[0];
    }
}
