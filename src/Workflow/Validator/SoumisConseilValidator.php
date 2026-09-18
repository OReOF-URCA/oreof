<?php

declare(strict_types=1);

namespace App\Workflow\Validator;

use App\Entity\DpeParcours;
use App\TypeDiplome\TypeDiplomeResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Validator pour l'étape "soumis_conseil".
 *
 * Valide les prérequis pour soumettre un parcours au conseil de composante.
 * Cette étape intervient après la validation par le DPE de composante.
 */
#[AutoconfigureTag('workflow.validator')]
final class SoumisConseilValidator extends AbstractStepValidator
{
    private const STEP_CODE = 'soumis_conseil';

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

        // Vérification que le parcours a bien été validé par le RF
        $rfValidation = $this->validatePreviousSteps($dpeParcours);
        $checks[] = ValidationCheck::fromResult('previous_steps', 'Validation des étapes précédentes', $rfValidation);
        $errors = array_merge($errors, $rfValidation['errors']);
        $warnings = array_merge($warnings, $rfValidation['warnings']);

        // Validation de la complétude documentaire
        $docValidation = $this->validateDocumentation($dpeParcours);
        $checks[] = ValidationCheck::fromResult('documentation', 'Complétude documentaire', $docValidation);
        $errors = array_merge($errors, $docValidation['errors']);
        $warnings = array_merge($warnings, $docValidation['warnings']);

        // Validation des MCCC
        $mcccValidation = $this->validateMccc($dpeParcours);
        $checks[] = ValidationCheck::fromResult('mccc', 'Présence et complétude des MCCC', $mcccValidation);
        $errors = array_merge($errors, $mcccValidation['errors']);
        $warnings = array_merge($warnings, $mcccValidation['warnings']);

        // Validation des fiches matières
        $fichesValidation = $this->validateFicheMatieres($dpeParcours);
        $checks[] = ValidationCheck::fromResult('fiche_matieres', 'Validation et complétude des fiches matières', $fichesValidation);
        $errors = array_merge($errors, $fichesValidation['errors']);
        $warnings = array_merge($warnings, $fichesValidation['warnings']);

        if (count($errors) > 0) {
            return ValidationResult::failure($errors, $warnings, $checks);
        }

        return ValidationResult::success($warnings, $checks);
    }

    /**
     * Vérifie que les étapes précédentes sont validées.
     *
     * @return array{errors: ValidationError[], warnings: ValidationWarning[]}
     */
    private function validatePreviousSteps(DpeParcours $dpeParcours): array
    {
        $errors = [];
        $warnings = [];

        $etatValidation = $dpeParcours->getEtatValidation();

        // Le parcours doit avoir été validé par le RF (soumis_dpe_composante)
        if (!isset($etatValidation['soumis_dpe_composante'])) {
            // On ne bloque pas car le workflow gère déjà les transitions
            $warnings[] = $this->createWarning(
                'conseil.previous_step.not_completed',
                'Assurez-vous que la validation DPE composante a bien été effectuée.'
            );
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Valide la documentation nécessaire pour le conseil.
     *
     * @return array{errors: ValidationError[], warnings: ValidationWarning[]}
     */
    private function validateDocumentation(DpeParcours $dpeParcours): array
    {
        $errors = [];
        $warnings = [];

        $parcours = $this->getParcours($dpeParcours);
        $formation = $dpeParcours->getFormation();

        if ($parcours === null || $formation === null) {
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        // Vérification du contenu de formation
        if ($this->isEmpty($parcours->getContenuFormation()) && $this->isEmpty($formation->getContenuFormation())) {
            $errors[] = $this->createError(
                'conseil.contenu_formation.empty',
                'Le contenu de la formation doit être renseigné avant le passage en conseil.'
            );
        }

        // Vérification des résultats attendus
        if ($this->isEmpty($parcours->getResultatsAttendus()) && $this->isEmpty($formation->getResultatsAttendus())) {
            $warnings[] = $this->createWarning(
                'conseil.resultats_attendus.empty',
                'Les résultats attendus ne sont pas renseignés.'
            );
        }

        // Vérification des modalités d'admission
        if ($this->isEmpty($parcours->getModalitesAdmission())) {
            $warnings[] = $this->createWarning(
                'conseil.modalites_admission.empty',
                'Les modalités d\'admission ne sont pas renseignées.'
            );
        }

        // Vérification de la description pour le site web
        if ($this->isEmpty($parcours->getDescriptifHautPageAffichage())) {
            $warnings[] = $this->createWarning(
                'conseil.descriptif.empty',
                'Le descriptif de présentation (haut de page) n\'est pas renseigné.'
            );
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Valide les MCCC (Modalités de Contrôle des Connaissances et Compétences).
     *
     * @return array{errors: ValidationError[], warnings: ValidationWarning[]}
     */
    private function validateMccc(DpeParcours $dpeParcours): array
    {
        $errors = [];
        $warnings = [];

        $parcours = $this->getParcours($dpeParcours);

        if ($parcours === null) {
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        // Compter les EC sans MCCC définies
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

                    // Vérifier si l'EC a des MCCC définies
                    // Note: La logique exacte dépend de votre structure de données MCCC
                    $mccc = $ec->getMcccs();

                    if ($mccc === null || $mccc->isEmpty()) {
                        $ecSansMccc++;
                    }
                }
            }
        }

        if ($totalEc > 0 && $ecSansMccc > 0) {
            $percentage = round(($ecSansMccc / $totalEc) * 100, 1);

            if ($percentage > 20) {
                $errors[] = $this->createError(
                    'conseil.mccc.incomplete',
                    '%count% EC sur %total% n\'ont pas de MCCC définies (%percentage%%). Les MCCC doivent être complètes avant le conseil.',
                    [
                        '%count%' => $ecSansMccc,
                        '%total%' => $totalEc,
                        '%percentage%' => $percentage,
                    ]
                );
            } else {
                $warnings[] = $this->createWarning(
                    'conseil.mccc.partial',
                    '%count% EC sur %total% n\'ont pas de MCCC définies.',
                    [
                        '%count%' => $ecSansMccc,
                        '%total%' => $totalEc,
                    ]
                );
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Valide les fiches matières du parcours.
     *
     * @return array{errors: ValidationError[], warnings: ValidationWarning[]}
     */
    private function validateFicheMatieres(DpeParcours $dpeParcours): array
    {
        $errors = [];
        $warnings = [];

        $parcours = $this->getParcours($dpeParcours);

        if ($parcours === null) {
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        $stats = $parcours->getEtatsFichesMatieres();

        // Vérifier le nombre de fiches
        if ($stats->nbFiches === 0) {
            $warnings[] = $this->createWarning(
                'conseil.fiches.empty',
                'Aucune fiche matière n\'est associée à ce parcours.'
            );
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        // Vérifier les fiches non validées
        $nonValidees = $stats->nbFichesNonValidees;

        if ($nonValidees > 0) {
            $percentage = round(($nonValidees / $stats->nbFiches) * 100, 1);

            if ($percentage > 30) {
                $errors[] = $this->createError(
                    'conseil.fiches.not_validated',
                    '%count% fiches matières sur %total% ne sont pas validées (%percentage%%).',
                    [
                        '%count%' => $nonValidees,
                        '%total%' => $stats->nbFiches,
                        '%percentage%' => $percentage,
                    ]
                );
            } else {
                $warnings[] = $this->createWarning(
                    'conseil.fiches.partially_validated',
                    '%count% fiches matières sur %total% ne sont pas encore validées.',
                    [
                        '%count%' => $nonValidees,
                        '%total%' => $stats->nbFiches,
                    ]
                );
            }
        }

        // Vérifier les fiches incomplètes
        $incomplete = $stats->nbFiches - $stats->nbFichesCompletes;

        if ($incomplete > 0) {
            $warnings[] = $this->createWarning(
                'conseil.fiches.incomplete',
                '%count% fiches matières ne sont pas complètes.',
                ['%count%' => $incomplete]
            );
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }
}
