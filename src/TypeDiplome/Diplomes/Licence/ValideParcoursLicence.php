<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/TypeDiplome/But/ValideParcoursBut.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 28/05/2025 15:30
 */

namespace App\TypeDiplome\Diplomes\Licence;

use App\DTO\StructureEc;
use App\DTO\StructureParcours;
use App\DTO\StructureSemestre;
use App\DTO\StructureUe;
use App\Entity\Parcours;
use App\Enums\ValidationStatusEnum;
use App\Service\Validation\Dto\ValidationIssueDto;
use App\Service\Validation\Dto\ValidationResult;
use App\TypeDiplome\ValideParcoursInterface;

class ValideParcoursLicence implements ValideParcoursInterface
{

    public function valideParcours(StructureParcours $structureParcours): ValidationResult
    {
        $result = new ValidationResult();

        // Exemple : si aucun semestre calculé (dans ton calcul, non-dispensé/fermé => pas ajouté)
        if (count($structureParcours->semestres) === 0) {
            $result->setSemestreStatus(ValidationStatusEnum::INVALID);
            $result->addIssue(new ValidationIssueDto(
                scopeType: 'parcours',
                scopeId: $structureParcours->parcours->getId() ?? 0,
                ruleCode: 'NO_SEMESTER',
                severity: 'error',
                message: 'Aucun semestre renseigné/calculé.',
            ));
            $result->autoComputeGlobalStatus();
            return $result;
        }

        // Si tu veux : agréger aussi les erreurs des semestres (souvent utile)
        foreach ($structureParcours->semestres as $ordre => $dtoSem) {
            $semRes = $this->valideSemestre($dtoSem);
            foreach ($semRes->getIssues() as $issue) {
                $result->addIssue($issue);
            }
        }

        $result->autoComputeGlobalStatus();
        return $result;
    }


    public function valideSemestre(StructureSemestre $structureSemestre): ValidationResult
    {
        $result = new ValidationResult();

        $sem = $structureSemestre->semestre;
        $semId = $sem->getId() ?? 0;

        // Dans ton calcul DTO, un semestre n'est construit que si (nonDispense=false && ouvert=true)
        // Donc le cas NA est souvent filtré en amont. Mais on peut sécuriser :
        if ($sem->isNonDispense() === true || $structureSemestre->semestreParcours?->isOuvert() === false) {
            $result->setSemestreStatus(ValidationStatusEnum::NA);
            return $result;
        }

        // 1) UE manquantes
        if (count($structureSemestre->ues) === 0) {
            $result->setSemestreStatus(ValidationStatusEnum::INCOMPLETE);
            $result->addIssue(new ValidationIssueDto(
                scopeType: 'semestre',
                scopeId: $semId,
                ruleCode: 'UE_MISSING',
                severity: 'error',
                message: 'Aucune UE renseignée pour ce semestre.',
                payload: ['ordre' => $structureSemestre->ordre],
                status: ValidationStatusEnum::INCOMPLETE
            ));
        }

        // 2) UE / EC rules
        foreach ($structureSemestre->ues as $dtoUe) {
            $this->valideUe($result, $dtoUe, $structureSemestre);
        }

        // 3) ECTS semestre = 30 (ta règle licence)
        // Idéalement on utilise ton agrégat DTO (HeuresEctsSemestre) au lieu de recalculer GetUeEcts
        $ects = $structureSemestre->heuresEctsSemestre->sommeSemestreEcts ?? null; // adapte au vrai champ
        if ($ects !== null && \abs($ects - 30.0) > 0.0001) {
            $result->setSemestreStatus(ValidationStatusEnum::INVALID);
            $result->addIssue(new ValidationIssueDto(
                scopeType: 'semestre',
                scopeId: $semId,
                ruleCode: 'ECTS_SEM_30',
                severity: 'error',
                message: 'Le semestre doit faire 30 ECTS.',
                payload: ['ects' => $ects, 'expected' => 30.0, 'ordre' => $structureSemestre->ordre],
                status: ValidationStatusEnum::INVALID
            ));
        }

        $result->autoComputeGlobalStatus();
        return $result;
    }

    private function valideUe(
        ValidationResult  $result,
        StructureUe       $ueDto,
        StructureSemestre $structureSemestre
    ): void
    {
        $ueEntity = $ueDto->ue;
        if (!$ueEntity) {
            return;
        }

        $ueId = $ueEntity->getId() ?? 0;

        // UE libre : ECTS à renseigner éventuellement
        if ($ueEntity->getNatureUeEc()?->isLibre()) {
            $ectsUe = $ueEntity->getEcts() ?? 0.0;
            if ($ectsUe <= 0.0) {
                $result->setUeStatus($ueId, ValidationStatusEnum::INCOMPLETE);
                $result->addIssue(new ValidationIssueDto(
                    scopeType: 'ue',
                    scopeId: $ueId,
                    ruleCode: 'UE_ECTS_MISSING',
                    severity: 'warning',
                    message: 'UE libre : ECTS à renseigner.',
                    payload: ['ue' => $ueDto->display, 'semestre' => $structureSemestre->ordre],
                    status: ValidationStatusEnum::INCOMPLETE
                ));
            } else {
                $result->setUeStatus($ueId, ValidationStatusEnum::VALID);
            }
            return;
        }

        // UE choix : on valide enfants + leurs EC (tu as déjà uesEnfants dans DTO)
        if ($ueEntity->getNatureUeEc()?->isChoix() === true && count($ueDto->uesEnfants) > 0) {
            // statut UE parent par défaut
            $result->setUeStatus($ueId, ValidationStatusEnum::VALID);

            foreach ($ueDto->uesEnfants as $childUeDto) {
                foreach ($childUeDto->elementConstitutifs as $ecDto) {
                    $this->valideEc($result, $ecDto, $childUeDto, $structureSemestre);
                }
            }
            return;
        }

        // UE classique : EC
        if (count($ueDto->elementConstitutifs) === 0) {
            $result->setUeStatus($ueId, ValidationStatusEnum::INCOMPLETE);
            $result->addIssue(new ValidationIssueDto(
                scopeType: 'ue',
                scopeId: $ueId,
                ruleCode: 'EC_MISSING',
                severity: 'error',
                message: 'Aucun EC renseigné dans cette UE.',
                payload: ['ue' => $ueDto->display, 'semestre' => $structureSemestre->ordre],
                status: ValidationStatusEnum::INCOMPLETE
            ));
            return;
        }

        foreach ($ueDto->elementConstitutifs as $ecDto) {
            $this->valideEc($result, $ecDto, $ueDto, $structureSemestre);
        }
    }

    private function valideEc(
        ValidationResult  $result,
        StructureEc       $ecDto,
        StructureUe       $ueDto,
        StructureSemestre $semDto
    ): void
    {
        $ec = $ecDto->elementConstitutif;
        $ecId = $ec->getId() ?? 0;
        $ueId = $ueDto->ue?->getId();

        $nature = $ec->getNatureUeEc();

        // Si EC a des enfants dans DTO => valider enfants (analogue à ton code)
        if (count($ecDto->elementsConstitutifsEnfants) > 0 || ($nature !== null && $nature->isChoix())) {
            foreach ($ecDto->elementsConstitutifsEnfants as $child) {
                $this->valideEc($result, $child, $ueDto, $semDto);
            }
            // (option : status parent = pire des enfants)
            return;
        }

        $status = ValidationStatusEnum::VALID;

        // Type EC obligatoire
        if ($ec->getTypeEc() === null && $ec->getEcParent() === null) {
            $status = ValidationStatusEnum::INCOMPLETE;
            $result->addIssue(new ValidationIssueDto(
                scopeType: 'ec',
                scopeId: $ecId,
                ruleCode: 'EC_TYPE_MISSING',
                severity: 'error',
                message: 'Type d’EC non renseigné (disciplinaire, ...).',
                payload: ['ec' => $ec->getCode(), 'ue' => $ueDto->display, 'semestre' => $semDto->ordre],
                status: ValidationStatusEnum::INCOMPLETE
            ));
        }

        // Fiche matière (si pas libre ou à choix)
        if ($ec->getFicheMatiere() === null && $nature !== null && !$nature->isLibre() && !$nature->isChoix()) {
            $status = $this->worst($status, ValidationStatusEnum::INCOMPLETE);
            $result->addIssue(new ValidationIssueDto(
                scopeType: 'ec',
                scopeId: $ecId,
                ruleCode: 'FICHE_MATIERE_MISSING',
                severity: 'error',
                message: 'Fiche matière non renseignée.',
                payload: ['ec' => $ec->getCode(), 'ue' => $ueDto->display],
                status: ValidationStatusEnum::INCOMPLETE
            ));
        } else {
            // MCCC via DTO (au lieu de GetElementConstitutif)
            // Ton StructureEc remplit $mcccs depuis fiche matière si option dataFromFicheMatiere
            $mcccs = $ecDto->mcccs ?? [];
            if (empty($mcccs) && $ec->isControleAssiduite() === false && ($nature === null || !$nature->isLibre())) {
                $status = $this->worst($status, ValidationStatusEnum::INCOMPLETE);
                $result->addIssue(new ValidationIssueDto(
                    scopeType: 'ec',
                    scopeId: $ecId,
                    ruleCode: 'MCCC_MISSING',
                    severity: 'error',
                    message: 'MCCC non renseignées.',
                    payload: ['ec' => $ec->getCode()],
                    status: ValidationStatusEnum::INCOMPLETE
                ));
            }

            // ECTS via DTO
            // StructureEc->heuresEctsEc contient addEcts(getFicheMatiereEcts())
            $ects = $ecDto->getHeuresEctsEc()->ects;
            // règle : ECTS obligatoire ou non selon type diplôme (à injecter si besoin)
            // ici, j’illustre ton ancien comportement :
            if ($ects <= 0.0 || $ects > 30.0) {
                $status = ValidationStatusEnum::INVALID;
                $result->addIssue(new ValidationIssueDto(
                    scopeType: 'ec',
                    scopeId: $ecId,
                    ruleCode: 'ECTS_INVALID',
                    severity: 'error',
                    message: 'ECTS non renseignés ou hors plage.',
                    payload: ['ects' => $ects, 'ec' => $ec->getCode()],
                    status: ValidationStatusEnum::INVALID
                ));
            }

            // Heures via Entity
            $heuresOk = $ec->isHeuresComplete();
            if (!$heuresOk && $nature !== null && !$nature->isChoix() && !$nature->isLibre()) {
                $status = $this->worst($status, ValidationStatusEnum::INCOMPLETE);
                $result->addIssue(new ValidationIssueDto(
                    scopeType: 'ec',
                    scopeId: $ecId,
                    ruleCode: 'HOURS_MISSING',
                    severity: 'error',
                    message: 'Volumes horaires non renseignés.',
                    payload: ['ec' => $ec->getCode()],
                    status: ValidationStatusEnum::INCOMPLETE
                ));
            }

            // BCC via DTO
            $bccs = $ecDto->bccs ?? [];
            if ($bccs === []) {
                $status = $this->worst($status, ValidationStatusEnum::INCOMPLETE);
                $result->addIssue(new ValidationIssueDto(
                    scopeType: 'ec',
                    scopeId: $ecId,
                    ruleCode: 'BCC_INCOMPLETE',
                    severity: 'error',
                    message: 'BCC incomplet ou non renseigné.',
                    payload: ['ec' => $ec->getCode()],
                    status: ValidationStatusEnum::INCOMPLETE
                ));
            }
        }

        $result->setEcStatus($ecId, $status);

        // Optionnel (mais utile) : poser UE status “au fil de l’eau”
        if ($ueId !== null && $status !== ValidationStatusEnum::VALID) {
            $prev = $result->getUeStatuses()[$ueId] ?? ValidationStatusEnum::VALID;
            $result->setUeStatus($ueId, $this->worst($prev, $status === ValidationStatusEnum::INVALID ? ValidationStatusEnum::INVALID : ValidationStatusEnum::INCOMPLETE));
        }
    }

    private function worst(ValidationStatusEnum $a, ValidationStatusEnum $b): ValidationStatusEnum
    {
        $rank = [
            ValidationStatusEnum::INVALID->value => 3,
            ValidationStatusEnum::INCOMPLETE->value => 2,
            ValidationStatusEnum::VALID->value => 1,
            ValidationStatusEnum::NA->value => 0,
        ];
        return ($rank[$b->value] > $rank[$a->value]) ? $b : $a;
    }
}
