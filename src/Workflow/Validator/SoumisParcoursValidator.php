<?php

declare(strict_types=1);

namespace App\Workflow\Validator;

use App\Entity\DpeParcours;
use App\TypeDiplome\TypeDiplomeResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Validator pour l'étape "soumis_parcours".
 *
 * Valide les prérequis pour soumettre un parcours au responsable de formation.
 * Cette étape est la première du processus de validation après la rédaction.
 */
#[AutoconfigureTag('workflow.validator')]
final class SoumisParcoursValidator extends AbstractStepValidator
{
    private const STEP_CODE = 'soumis_parcours';
    private const MIN_REMPLISSAGE_PERCENT = 80;

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

        // Validation du taux de remplissage
        $remplissageValidation = $this->validateRemplissage($dpeParcours);
        $checks[] = ValidationCheck::fromResult('completion', 'Taux de remplissage minimal (80 %)', $remplissageValidation);
        $errors = array_merge($errors, $remplissageValidation['errors']);
        $warnings = array_merge($warnings, $remplissageValidation['warnings']);

        // Validation des informations du parcours
        $parcoursValidation = $this->validateParcoursInfo($dpeParcours);
        $checks[] = ValidationCheck::fromResult('parcours', 'Informations obligatoires du parcours', $parcoursValidation);
        $errors = array_merge($errors, $parcoursValidation['errors']);
        $warnings = array_merge($warnings, $parcoursValidation['warnings']);

        // Validation spécifique au type de diplôme
        // Note: Les règles détaillées seront appelées via le TypeDiplomeRegistry
        $typeValidation = $this->validateByTypeDiplome($dpeParcours);
        $checks[] = ValidationCheck::fromResult('diploma_type', 'Règles génériques du type de diplôme', $typeValidation);
        $errors = array_merge($errors, $typeValidation['errors']);
        $warnings = array_merge($warnings, $typeValidation['warnings']);

        // Validation de la structure des semestres
        $structureValidation = $this->validateStructure($dpeParcours);
        $checks[] = ValidationCheck::fromResult('structure', 'Structure des semestres et des UE', $structureValidation);
        $errors = array_merge($errors, $structureValidation['errors']);
        $warnings = array_merge($warnings, $structureValidation['warnings']);

        if (count($errors) > 0) {
            return ValidationResult::failure($errors, $warnings, $checks);
        }

        return ValidationResult::success($warnings, $checks);
    }

    /**
     * Valide le taux de remplissage minimum.
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
                'remplissage.insufficient',
                'Le taux de remplissage du parcours est insuffisant. Minimum requis : %min%%, actuel : %current%%.',
                [
                    '%min%' => self::MIN_REMPLISSAGE_PERCENT,
                    '%current%' => round($percentage, 1),
                ]
            );
        } elseif ($percentage < 100) {
            $warnings[] = $this->createWarning(
                'remplissage.incomplete',
                'Le parcours n\'est pas complètement rempli (%current%%). Il est recommandé de compléter toutes les informations.',
                ['%current%' => round($percentage, 1)]
            );
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Valide les informations de base du parcours.
     *
     * @return array{errors: ValidationError[], warnings: ValidationWarning[]}
     */
    private function validateParcoursInfo(DpeParcours $dpeParcours): array
    {
        $errors = [];
        $warnings = [];

        $parcours = $this->getParcours($dpeParcours);

        if ($parcours === null) {
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        // Vérification des objectifs du parcours
        if ($this->isEmpty($parcours->getObjectifsParcours())) {
            $errors[] = $this->createFieldError(
                'objectifsParcours',
                'parcours.objectifs.empty',
                'Les objectifs du parcours doivent être renseignés.'
            );
        }

        // Vérification du contenu de formation
        if ($this->isEmpty($parcours->getContenuFormation())) {
            $warnings[] = $this->createWarning(
                'parcours.contenu.empty',
                'Le contenu de la formation n\'est pas renseigné.'
            );
        }

        // Vérification des débouchés
        if ($this->isEmpty($parcours->getDebouches())) {
            $warnings[] = $this->createWarning(
                'parcours.debouches.empty',
                'Les débouchés du parcours ne sont pas renseignés.'
            );
        }

        // Vérification des prérequis
        if ($this->isEmpty($parcours->getPrerequis())) {
            $warnings[] = $this->createWarning(
                'parcours.prerequis.empty',
                'Les prérequis du parcours ne sont pas renseignés.'
            );
        }

        // Vérification du responsable de parcours
        if ($parcours->getRespParcours() === null && !$parcours->isParcoursDefaut()) {
            $errors[] = $this->createError(
                'parcours.responsable.missing',
                'Le responsable du parcours doit être défini.'
            );
        }

        // Vérification des modalités d'enseignement
        if ($parcours->getModalitesEnseignement() === null) {
            $warnings[] = $this->createWarning(
                'parcours.modalites_enseignement.empty',
                'Les modalités d\'enseignement ne sont pas définies.'
            );
        }

        // Vérification de la localisation
        if ($parcours->getLocalisation() === null) {
            $warnings[] = $this->createWarning(
                'parcours.localisation.empty',
                'La localisation du parcours n\'est pas définie.'
            );
        }

        // Vérification du régime d'inscription
        if ($this->isEmptyArray($parcours->getRegimeInscription())) {
            $errors[] = $this->createError(
                'parcours.regime_inscription.empty',
                'Le régime d\'inscription doit être défini.'
            );
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Valide selon le type de diplôme.
     *
     * Note: Cette méthode prépare les points d'appel vers le TypeDiplomeRegistry.
     * Les règles détaillées (volume horaire BUT, stage LP, etc.) seront implémentées
     * dans les handlers spécifiques du TypeDiplomeRegistry.
     *
     * @return array{errors: ValidationError[], warnings: ValidationWarning[]}
     */
    private function validateByTypeDiplome(DpeParcours $dpeParcours): array
    {
        $errors = [];
        $warnings = [];

        $typeDiplomeCode = $this->getTypeDiplomeCode($dpeParcours);

        if ($typeDiplomeCode === null) {
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        // Validation selon le type - les règles détaillées sont dans TypeDiplomeRegistry
        // Ici on ajoute des vérifications génériques par type
        match ($typeDiplomeCode) {
            'BUT' => $this->addButValidations($dpeParcours, $errors, $warnings),
            'LP' => $this->addLicenceProValidations($dpeParcours, $errors, $warnings),
            'Master', 'M' => $this->addMasterValidations($dpeParcours, $errors, $warnings),
            'Licence', 'L' => $this->addLicenceValidations($dpeParcours, $errors, $warnings),
            default => null,
        };

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Ajoute les validations spécifiques BUT.
     * Note: Les règles détaillées (1800h, référentiel compétences, SAÉ) sont dans ButHandler.
     */
    private function addButValidations(DpeParcours $dpeParcours, array &$errors, array &$warnings): void
    {
        $parcours = $this->getParcours($dpeParcours);

        if ($parcours === null) {
            return;
        }

        // Vérification des blocs de compétences (obligatoires pour BUT)
        if ($parcours->getBlocCompetences()->isEmpty()) {
            $errors[] = $this->createError(
                'but.competences.missing',
                'Les blocs de compétences sont obligatoires pour un BUT.'
            );
        }

        // Note: La validation du volume horaire (1800h) et des SAÉ
        // sera effectuée via le TypeDiplomeResolver->getFromParcours()->validate()
    }

    /**
     * Ajoute les validations spécifiques Licence Professionnelle.
     * Note: Les règles détaillées (stage 420h, volume 450h) sont dans LicenceProHandler.
     */
    private function addLicenceProValidations(DpeParcours $dpeParcours, array &$errors, array &$warnings): void
    {
        $parcours = $this->getParcours($dpeParcours);

        if ($parcours === null) {
            return;
        }

        // Vérification du stage (obligatoire pour LP)
        if ($parcours->isHasStage() !== true) {
            $errors[] = $this->createError(
                'lp.stage.required',
                'Le stage est obligatoire pour une Licence Professionnelle.'
            );
        } elseif ($parcours->getNbHeuresStages() === null || $parcours->getNbHeuresStages() < 420) {
            $errors[] = $this->createError(
                'lp.stage.duration.insufficient',
                'Le stage doit être d\'au moins 420 heures (12 semaines) pour une LP. Actuel : %hours%h.',
                ['%hours%' => $parcours->getNbHeuresStages() ?? 0]
            );
        }

        // Vérification alternance recommandée
        if (!$parcours->isAlternance()) {
            $warnings[] = $this->createWarningWithSuggestion(
                'lp.alternance.recommended',
                'L\'alternance est recommandée pour une Licence Professionnelle.',
                'Envisagez d\'activer le régime d\'inscription en alternance ou apprentissage.'
            );
        }
    }

    /**
     * Ajoute les validations spécifiques Master.
     * Note: Les règles détaillées (60 ECTS, fiche RNCP) sont dans MasterHandler.
     */
    private function addMasterValidations(DpeParcours $dpeParcours, array &$errors, array &$warnings): void
    {
        $parcours = $this->getParcours($dpeParcours);
        $formation = $dpeParcours->getFormation();

        if ($parcours === null || $formation === null) {
            return;
        }

        // Vérification du code RNCP (obligatoire pour Master)
        if ($this->isEmpty($parcours->getCodeRNCP()) && $this->isEmpty($formation->getCodeRNCP())) {
            $warnings[] = $this->createWarning(
                'master.rncp.missing',
                'Le code RNCP devrait être renseigné pour un Master.'
            );
        }

        // Vérification des poursuites d'études (recommandé sauf M2)
        if ($this->isEmpty($parcours->getPoursuitesEtudes())) {
            $warnings[] = $this->createWarning(
                'master.poursuites_etudes.empty',
                'Les poursuites d\'études ne sont pas renseignées.'
            );
        }
    }

    /**
     * Ajoute les validations spécifiques Licence.
     */
    private function addLicenceValidations(DpeParcours $dpeParcours, array &$errors, array &$warnings): void
    {
        $parcours = $this->getParcours($dpeParcours);

        if ($parcours === null) {
            return;
        }

        // Vérification des blocs de compétences
        if ($parcours->getBlocCompetences()->isEmpty()) {
            $warnings[] = $this->createWarning(
                'licence.competences.empty',
                'Les blocs de compétences ne sont pas définis pour cette Licence.'
            );
        }
    }

    /**
     * Valide la structure des semestres du parcours.
     *
     * @return array{errors: ValidationError[], warnings: ValidationWarning[]}
     */
    private function validateStructure(DpeParcours $dpeParcours): array
    {
        $errors = [];
        $warnings = [];

        $parcours = $this->getParcours($dpeParcours);

        if ($parcours === null) {
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        // Vérification qu'il y a des semestres
        if ($parcours->getSemestreParcours()->isEmpty()) {
            $errors[] = $this->createError(
                'structure.semestres.empty',
                'Aucun semestre n\'est défini pour ce parcours.'
            );
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        // Vérification que chaque semestre a des UE
        foreach ($parcours->getSemestreParcours() as $semestreParcours) {
            $semestre = $semestreParcours->getSemestre();

            if ($semestre === null) {
                continue;
            }

            if ($semestre->isNonDispense()) {
                continue; // Ignorer les semestres non dispensés
            }

            $ues = $semestre->getUes();

            if ($ues->isEmpty()) {
                $warnings[] = $this->createWarning(
                    'structure.semestre.ues.empty',
                    'Le semestre %semestre% ne contient aucune UE.',
                    ['%semestre%' => $semestreParcours->getOrdre()]
                );
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }
}
