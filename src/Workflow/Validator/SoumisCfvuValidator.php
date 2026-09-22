<?php

declare(strict_types=1);

namespace App\Workflow\Validator;

use App\Entity\DpeParcours;
use App\TypeDiplome\TypeDiplomeResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Validator pour l'étape "soumis_cfvu".
 *
 * Valide les prérequis pour soumettre un parcours à la CFVU
 * (Commission de la Formation et de la Vie Universitaire).
 * Cette étape intervient après la validation par le SES (Service central).
 */
#[AutoconfigureTag('workflow.validator')]
final class SoumisCfvuValidator extends AbstractStepValidator
{
    private const STEP_CODE = 'soumis_cfvu';
    private const MIN_REMPLISSAGE_PERCENT = 95;

    public function __construct(
        EntityManagerInterface $entityManager,
        TypeDiplomeResolver    $typeDiplomeResolver
    )
    {
        parent::__construct($entityManager, $typeDiplomeResolver);
    }

    /**
     * {@inheritdoc}
     */
    public function getStepCode(): string
    {
        return self::STEP_CODE;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(DpeParcours $dpeParcours): ValidationResult
    {
        $errors = [];
        $warnings = [];
        $checks = [];

        // Validation des données communes
        $commonValidation = $this->validateCommonRequirements($dpeParcours);
        $checks[] = ValidationCheck::fromResult('common', 'Données générales du parcours et de la formation', $commonValidation);
        $errors = array_merge($errors, $commonValidation['errors']);
        $warnings = array_merge($warnings, $commonValidation['warnings']);

        // Si erreurs critiques, arrêter la validation
        if (count($errors) > 0) {
            return ValidationResult::failure($errors, $warnings, $checks);
        }

        // Validation du taux de remplissage (plus strict pour CFVU)
        $remplissageValidation = $this->validateRemplissage($dpeParcours);
        $checks[] = ValidationCheck::fromResult('completion', 'Taux de remplissage minimal (95 %)', $remplissageValidation);
        $errors = array_merge($errors, $remplissageValidation['errors']);
        $warnings = array_merge($warnings, $remplissageValidation['warnings']);

        // Validation des informations réglementaires
        $reglementaireValidation = $this->validateReglementaire($dpeParcours);
        $checks[] = ValidationCheck::fromResult('regulatory', 'Informations réglementaires', $reglementaireValidation);
        $errors = array_merge($errors, $reglementaireValidation['errors']);
        $warnings = array_merge($warnings, $reglementaireValidation['warnings']);

        // Validation des MCCC complètes
        $mcccValidation = $this->validateMcccComplete($dpeParcours);
        $checks[] = ValidationCheck::fromResult('mccc', 'Complétude de toutes les MCCC', $mcccValidation);
        $errors = array_merge($errors, $mcccValidation['errors']);
        $warnings = array_merge($warnings, $mcccValidation['warnings']);

        // Validation des fiches matières (toutes validées)
        $fichesValidation = $this->validateFichesComplete($dpeParcours);
        $checks[] = ValidationCheck::fromResult('fiche_matieres', 'Validation de toutes les fiches matières', $fichesValidation);
        $errors = array_merge($errors, $fichesValidation['errors']);
        $warnings = array_merge($warnings, $fichesValidation['warnings']);

        // Validation de la cohérence ECTS
        $ectsValidation = $this->validateEcts($dpeParcours);
        $checks[] = ValidationCheck::fromResult('ects', 'Cohérence des ECTS', $ectsValidation);
        $errors = array_merge($errors, $ectsValidation['errors']);
        $warnings = array_merge($warnings, $ectsValidation['warnings']);

        // Validation du passage en conseil (PV requis)
        $conseilValidation = $this->validateConseilPassed($dpeParcours);
        $checks[] = ValidationCheck::fromResult('council', 'Passage en conseil et validation SES', $conseilValidation);
        $errors = array_merge($errors, $conseilValidation['errors']);
        $warnings = array_merge($warnings, $conseilValidation['warnings']);

        if (count($errors) > 0) {
            return ValidationResult::failure($errors, $warnings, $checks);
        }

        return ValidationResult::success($warnings, $checks);
    }

    /**
     * Valide le taux de remplissage minimum pour CFVU (plus strict).
     *
     * @return array{errors: ValidationError[], warnings: ValidationWarning[]}
     */
    private function validateRemplissage(DpeParcours $dpeParcours): array
    {
        $errors = [];
        $warnings = [];

        $percentage = $this->getRemplissagePercentage($dpeParcours);

        if ($percentage < self::MIN_REMPLISSAGE_PERCENT) {
            $errors[] = $this->createError(
                'cfvu.remplissage.insufficient',
                'Le taux de remplissage est insuffisant pour la CFVU. Minimum requis : %min%%, actuel : %current%%.',
                [
                    '%min%' => self::MIN_REMPLISSAGE_PERCENT,
                    '%current%' => round($percentage, 1),
                ]
            );
        }

        if ($percentage < 100) {
            $warnings[] = $this->createWarning(
                'cfvu.remplissage.not_complete',
                'Le parcours n\'est pas complètement rempli (%current%%). Complétez toutes les informations pour une présentation optimale en CFVU.',
                ['%current%' => round($percentage, 1)]
            );
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Valide les informations réglementaires requises pour la CFVU.
     *
     * @return array{errors: ValidationError[], warnings: ValidationWarning[]}
     */
    private function validateReglementaire(DpeParcours $dpeParcours): array
    {
        $errors = [];
        $warnings = [];

        $parcours = $this->getParcours($dpeParcours);
        $formation = $dpeParcours->getFormation();

        if ($parcours === null || $formation === null) {
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        // Vérification du code RNCP pour les diplômes qui le requièrent
        if ($formation->isInRncp() === true) {
            $codeRncp = $parcours->getCodeRNCP() ?: $formation->getCodeRNCP();

            if ($this->isEmpty($codeRncp)) {
                $warnings[] = $this->createWarning(
                    'cfvu.rncp.missing',
                    'Le code RNCP devrait être renseigné pour ce diplôme.'
                );
            }
        }

        // Vérification du niveau d'entrée/sortie
        if ($formation->getNiveauEntree() === null) {
            $errors[] = $this->createError(
                'cfvu.niveau_entree.missing',
                'Le niveau d\'entrée de la formation doit être défini.'
            );
        }

        if ($formation->getNiveauSortie() === null) {
            $errors[] = $this->createError(
                'cfvu.niveau_sortie.missing',
                'Le niveau de sortie de la formation doit être défini.'
            );
        }

        // Vérification des objectifs de formation
        if ($this->isEmpty($formation->getObjectifsFormation())) {
            $errors[] = $this->createError(
                'cfvu.objectifs_formation.missing',
                'Les objectifs de la formation doivent être renseignés.'
            );
        }

        // Vérification des débouchés
        if ($this->isEmpty($parcours->getDebouches())) {
            $errors[] = $this->createError(
                'cfvu.debouches.missing',
                'Les débouchés professionnels doivent être renseignés pour la CFVU.'
            );
        }

        // Vérification des codes ROME
        if ($this->isEmptyArray($parcours->getCodesRome())) {
            $warnings[] = $this->createWarning(
                'cfvu.codes_rome.missing',
                'Les codes ROME ne sont pas renseignés. Ils sont utiles pour l\'orientation des étudiants.'
            );
        }

        // Vérification du niveau de français requis
        if ($parcours->getNiveauFrancais() === null) {
            $warnings[] = $this->createWarning(
                'cfvu.niveau_francais.missing',
                'Le niveau de français requis n\'est pas défini.'
            );
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Valide que les MCCC sont complètes.
     *
     * @return array{errors: ValidationError[], warnings: ValidationWarning[]}
     */
    private function validateMcccComplete(DpeParcours $dpeParcours): array
    {
        $errors = [];
        $warnings = [];

        $parcours = $this->getParcours($dpeParcours);

        if ($parcours === null) {
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        $ecSansMccc = 0;
        $totalEc = 0;

        foreach ($parcours->getSemestreParcours() as $semestreParcours) {
            $semestre = $semestreParcours->getSemestre();

            if ($semestre === null || $semestre->isNonDispense()) {
                continue;
            }

            foreach ($semestre->getUes() as $ue) {
                foreach ($ue->getElementConstitutifs() as $ec) {
                    $totalEc++;

                    $mccc = $ec->getMcccs();

                    if ($mccc === null || $mccc->isEmpty()) {
                        $ecSansMccc++;
                    }
                }
            }
        }

        if ($totalEc > 0 && $ecSansMccc > 0) {
            // Pour la CFVU, les MCCC doivent être complètes
            $errors[] = $this->createError(
                'cfvu.mccc.incomplete',
                '%count% EC sur %total% n\'ont pas de MCCC définies. Toutes les MCCC doivent être renseignées pour la CFVU.',
                [
                    '%count%' => $ecSansMccc,
                    '%total%' => $totalEc,
                ]
            );
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Valide que toutes les fiches matières sont validées.
     *
     * @return array{errors: ValidationError[], warnings: ValidationWarning[]}
     */
    private function validateFichesComplete(DpeParcours $dpeParcours): array
    {
        $errors = [];
        $warnings = [];

        $parcours = $this->getParcours($dpeParcours);

        if ($parcours === null) {
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        $stats = $parcours->getEtatsFichesMatieres();

        if ($stats->nbFiches === 0) {
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        // Pour CFVU, toutes les fiches doivent être validées
        $nonValidees = $stats->nbFichesNonValidees;

        if ($nonValidees > 0) {
            $errors[] = $this->createError(
                'cfvu.fiches.not_validated',
                '%count% fiches matières ne sont pas validées. Toutes les fiches doivent être validées pour la CFVU.',
                ['%count%' => $nonValidees]
            );
        }

        // Vérifier les fiches en attente de validation SES
        $attenteSeS = $stats->nbFichesNonValideesSes;

        if ($attenteSeS > 0) {
            $errors[] = $this->createError(
                'cfvu.fiches.pending_ses',
                '%count% fiches matières sont en attente de validation par le SES.',
                ['%count%' => $attenteSeS]
            );
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Valide la cohérence des ECTS du parcours.
     *
     * @return array{errors: ValidationError[], warnings: ValidationWarning[]}
     */
    private function validateEcts(DpeParcours $dpeParcours): array
    {
        $errors = [];
        $warnings = [];

        $parcours = $this->getParcours($dpeParcours);
        $formation = $dpeParcours->getFormation();

        if ($parcours === null || $formation === null) {
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        $typeDiplome = $formation->getTypeDiplome();

        if ($typeDiplome === null) {
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        // Calculer le total ECTS attendu selon le type de diplôme
        $nbSemestres = $typeDiplome->getSemestreFin() - $typeDiplome->getSemestreDebut() + 1;
        $ectsAttendu = $nbSemestres * 30; // 30 ECTS par semestre

        // Calculer le total ECTS réel
        $totalEcts = 0;

        foreach ($parcours->getSemestreParcours() as $semestreParcours) {
            $semestre = $semestreParcours->getSemestre();

            if ($semestre === null || $semestre->isNonDispense()) {
                continue;
            }

            foreach ($semestre->getUes() as $ue) {
                $totalEcts += $ue->getEcts() ?? 0;
            }
        }

        if ($totalEcts !== $ectsAttendu && $totalEcts > 0) {
            if (abs($totalEcts - $ectsAttendu) > 5) {
                $errors[] = $this->createError(
                    'cfvu.ects.mismatch',
                    'Le total ECTS (%actual%) ne correspond pas au total attendu (%expected%).',
                    [
                        '%actual%' => $totalEcts,
                        '%expected%' => $ectsAttendu,
                    ]
                );
            } else {
                $warnings[] = $this->createWarning(
                    'cfvu.ects.slight_mismatch',
                    'Le total ECTS (%actual%) diffère légèrement du total attendu (%expected%).',
                    [
                        '%actual%' => $totalEcts,
                        '%expected%' => $ectsAttendu,
                    ]
                );
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Valide que le passage en conseil a bien eu lieu.
     *
     * @return array{errors: ValidationError[], warnings: ValidationWarning[]}
     */
    private function validateConseilPassed(DpeParcours $dpeParcours): array
    {
        $errors = [];
        $warnings = [];

        $etatValidation = $dpeParcours->getEtatValidation();

        // Vérifier que l'étape soumis_conseil est passée
        if (!isset($etatValidation['soumis_central'])) {
            $warnings[] = $this->createWarning(
                'cfvu.conseil.not_passed',
                'Vérifiez que le parcours a bien été validé par le service central (SES).'
            );
        }

        // Note: La vérification du PV de conseil sera gérée par les métadonnées du workflow
        // (hasUpload: true sur la transition valider_conseil)

        return ['errors' => $errors, 'warnings' => $warnings];
    }
}
