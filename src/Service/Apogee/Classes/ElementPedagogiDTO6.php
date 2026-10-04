<?php

namespace App\Service\Apogee\Classes;

use App\Command\ExportElpApogeeCommand;
use App\DTO\StructureEc;
use App\DTO\StructureParcours;
use App\DTO\StructureSemestre;
use App\DTO\StructureUe;
use App\Enums\Apogee\CodeNatuElpEnum;
use App\Enums\Apogee\TypeVolumeElpEnum;
use Transliterator;

class ElementPedagogiDTO6
{
    public string $codElp;
    public string $libCourtElp;
    public string $libElp;
    public string $codNatureElp;
    public string $codComposante;
    public string $temSuspension;
    public string $motifSuspension;
    public string $temModaliteControle;
    public string $temEnsADistance;
    public string $temHorsEtab;
    public string $temStage;
    public string $lieu;
    public float $nbrCredits;
    public string $dateDebutIP;
    public string $dateFinIP;
    public string $descriptionElp;
    public string $volume;
    public string $uniteVolume;
    public string $codPeriode;
    public string $numOrdrePresentationElp;
    public string $seuilOuverture;
    public string $capaciteMaxElp;
    public string $capaciteIntElp;
    public TableauComposanteDTO2 $listComposantesAssociees;
    public TableauCentreInsPedagogiDTO $listCentreInsPedagogi;
    public TableauPersonnelResponsableDTO $listResponsables;
    public TableauLibellesAnnexeDTO $listLibAnnexe;
    public TableauParametrageChargeEnseignementDTO2 $listParamChargEns;
    public TableauElementPrerequisDTO2 $listElementPrerequis;
    public TableauTypePopulationDTO2 $listTypePopulation;

    /**
     * @param StructureEc|StructureUe|StructureSemestre $elementPedagogique Élément à instancier en ELP
     * @param StructureParcours $dto DTO complet du parcours en question
     * @param ?CodeNatuElpEnum $nature Nature de l'élément : Semestre (SEM), UE (UE) ou EC (MATI, MATM, ...)
     * @param bool $withChecks Si la valeur est à true, les données incorrectes lanceront des exceptions
     */
    public function __construct(
        StructureEc|StructureUe|StructureSemestre $elementPedagogique,
        StructureParcours $dto,
        ?CodeNatuElpEnum $nature = null,
        bool $withChecks = false,
        array $paramArrayCE = []
    ) {
        if ($elementPedagogique instanceof StructureEc){
            // Création du code ELP
            $ecCodeApogee = $elementPedagogique->elementConstitutif->displayCodeApogee();
            if($ecCodeApogee === "Aucun code Apogée"){
                $ecCodeApogee = null;
            }
            $this->codElp = $this->checkCodeApogee(
                $ecCodeApogee,
                "Type : EC - ID : {$elementPedagogique->elementConstitutif->getId()} - {$elementPedagogique->elementConstitutif->display()}",
                $withChecks,
                $dto->parcours->getId()
            );
            // Libellé court
            $this->libCourtElp = $this->checkLibelleEC($elementPedagogique, 25, $dto->parcours->getId(), $withChecks);
            // Libellé long
            $this->libElp = $this->checkLibelleEC($elementPedagogique, 60, $dto->parcours->getId(), $withChecks);
            // Nature
            $this->codNatureElp = $this->checkNatureElp(
                $nature,
                "Type : EC - ID : {$elementPedagogique->elementConstitutif->getId()}",
                $withChecks,
                $dto->parcours->getId()
            );
            $this->codComposante = $this->checkCodeComposante(
                $dto,
                "Type : EC - Parcours ID : {$dto->parcours->getId()}",
                $withChecks
            );
            // $this->temSuspension;
            // $this->motifSuspension;
            $this->temModaliteControle = "O";
            // $this->temEnsADistance;
            // $this->temHorsEtab;
            // $this->temStage;
            // $this->lieu;
            if($elementPedagogique->heuresEctsEc->ects > 0){
                $this->nbrCredits = $elementPedagogique->heuresEctsEc->ects;
            }
            // $this->dateDebutIP;
            // $this->dateFinIP;
            // $this->descriptionElp;
            $this->volume = (string)$elementPedagogique->heuresEctsEc->sommeEcTotalPresDist();
            $this->uniteVolume = TypeVolumeElpEnum::HE->value;
            $this->codPeriode = $elementPedagogique->elementConstitutif->getUe()->getSemestre()->display();
            // $this->numOrdrePresentationElp;
            // $this->seuilOuverture;
            // $this->capaciteMaxElp;
            // $this->capaciteIntElp;
            // $this->listComposantesAssociees;
            $this->listCentreInsPedagogi = new TableauCentreInsPedagogiDTO(
                // array_map(
                //     fn($composante) => $composante->getCodeApogee(),
                //     $dto->parcours->getFormation()?->getComposantesInscription()?->toArray() ?? []
                // )

                // Code CIP de la composante porteuse uniquement
                // [$dto->parcours->getFormation()->getComposantePorteuse()->getCodeApogee()]
                // CODE CIP DE LA COMPOSANTE D'INSCRIPTION
                [$dto->parcours->getComposanteInscription()->getCodeApogee()]
            );
            // $this->listResponsables;
            // $this->listLibAnnexe;
            if(count($paramArrayCE) > 0){
                $this->listParamChargEns = new TableauParametrageChargeEnseignementDTO2($paramArrayCE);
            }
            // $this->listElementPrerequis;
            // $this->listTypePopulation;
        }
        elseif ($elementPedagogique instanceof StructureUe){
            $this->codElp = $this->checkCodeApogee(
                $elementPedagogique->getCodeApogee(),
                "Type UE - ID : {$elementPedagogique->ue->getId()}",
                $withChecks,
                $dto->parcours->getId()
            );
            $this->libCourtElp = $this->checkLibelleUE($elementPedagogique, $dto, 25, $withChecks);
            $this->libElp = $this->checkLibelleUE(
                $elementPedagogique, $dto, 60, $withChecks);
            $this->codNatureElp = $this->checkNatureElp(
                $nature,
                "Type : UE - ID : {$elementPedagogique->ue->getId()}",
                true,
                $dto->parcours->getId()
            );
            $this->codComposante = $this->checkCodeComposante($dto, "Type : UE - Parcourd ID : {$dto->parcours->getId()}", $withChecks);
            // $this->temSuspension
            // $this->motifSuspension
            $this->temModaliteControle = "O"; // valeur par défaut
            // $this->temEnsADistance = "N"; // valeur par défaut
            // $this->temHorsEtab = "N"; // valeur par défaut
            // $this->temStage = "N"; // valeur par défaut
            // $this->lieu = ""; //
            if($elementPedagogique->heuresEctsUe->sommeUeEcts > 0){
                $this->nbrCredits = $elementPedagogique->heuresEctsUe->sommeUeEcts;
            }
            // $this->dateDebutIP
            // $this->dateFinIP
            // $this->descriptionElp
            $this->volume = (string)$elementPedagogique->heuresEctsUe->sommeUeTotalPresDist();
            $this->uniteVolume = TypeVolumeElpEnum::HE->value; // Avoir si l'on met en semestre ou en heure
            $this->codPeriode = $elementPedagogique->ue->getSemestre()->display();
            // $this->numOrdrePresentationElp
            // $this->seuilOuverture
            // $this->capaciteMaxElp
            // $this->capaciteIntElp
            // $this->listComposantesAssociees = new TableauComposanteDTO2([]);
            $this->listCentreInsPedagogi = new TableauCentreInsPedagogiDTO(
                // array_map(
                //     fn($composante) => $composante->getCodeApogee(),
                //     $dto->parcours->getFormation()?->getComposantesInscription()?->toArray() ?? []
                // )

                // Code CIP de la composante porteuse uniquement
                // [$dto->parcours->getFormation()->getComposantePorteuse()->getCodeApogee()]
                // CODE CIP DE LA COMPOSANTE D'INSCRIPTION
                [$dto->parcours->getComposanteInscription()->getCodeApogee()]
            );
            // $this->listResponsables;
            // $this->listLibAnnexe;
            // $this->listParamChargEns;
            // $this->listElementPrerequis;
            // $this->listTypePopulation;

        }
        elseif ($elementPedagogique instanceof StructureSemestre){
            $this->codElp = $elementPedagogique->semestre->getCodeApogee() ?? "ERROR";
            // Libellés
            $this->libCourtElp = $this->prepareLibelle($this->checkLibelleSemestre($elementPedagogique, $dto, $withChecks, "libelleCourt"));
            $this->libElp = $this->prepareLibelle($this->checkLibelleSemestre($elementPedagogique, $dto, $withChecks, "libelleLong"), 60);
            $this->codNatureElp = $this->checkNatureElp($nature, "Type Semestre - ID : {$elementPedagogique->semestre->getId()}", $withChecks, $dto->parcours->getId());
            $this->codComposante = $this->checkCodeComposante($dto, "Type Semestre - ID : {$elementPedagogique->semestre->getId()}", $withChecks);
            // $this->temSuspension;
            // $this->motifSuspension;
            $this->temModaliteControle = "O";
            // $this->temEnsADistance;
            // $this->temHorsEtab;
            // $this->temStage;
            // $this->lieu;
            if($elementPedagogique->heuresEctsSemestre->sommeSemestreEcts > 0){
                $this->nbrCredits = $elementPedagogique->heuresEctsSemestre->sommeSemestreEcts;
            }
            // $this->dateDebutIP;
            // $this->dateFinIP;
            // $this->descriptionElp;
            $this->volume = (string)$elementPedagogique->heuresEctsSemestre->sommeSemestreTotalPresDist();
            $this->uniteVolume = TypeVolumeElpEnum::HE->value;
            $this->codPeriode = $elementPedagogique->semestre->display();
            // $this->numOrdrePresentationElp;
            // $this->seuilOuverture;
            // $this->capaciteMaxElp;
            // $this->capaciteIntElp;
            // $this->listComposantesAssociees;
            $this->listCentreInsPedagogi = new TableauCentreInsPedagogiDTO(
                // array_map(
                //     fn($composante) => $composante->getCodeApogee(),
                //     $dto->parcours->getFormation()?->getComposantesInscription()?->toArray() ?? []
                // )

                // Code CIP de la composante porteuse uniquement
                // [$dto->parcours->getFormation()->getComposantePorteuse()->getCodeApogee()]
                // CODE CIP DE LA COMPOSANTE D'INSCRIPTION
                [$dto->parcours->getComposanteInscription()->getCodeApogee()]
            );
            // $this->listResponsables;
            // $this->listLibAnnexe;
            // $this->listParamChargEns;
            // $this->listElementPrerequis;
            // $this->listTypePopulation;
        }
    }

    private function prepareLibelle(?string $txt, int $length = 25) : string {
        if($txt){
            $rules = "À > A; Ç > C; É > E; È > E; :: NFC;";
            $transliterator = Transliterator::createFromRules($rules, Transliterator::FORWARD);
            return mb_substr($transliterator->transliterate($txt), 0, $length);
        }else {
            return 'AUCUN LIBELLE';
        }
    }

    /**
     * Vérifie que le code Apogee pour l'ELP n'est pas null.
     * @param ?string $codeApogee Code à tester
     * @param string $identifier Identifiant de l'élément si une erreur survient
     * @param bool $withChecks Vrai si des messages d'erreurs doivent êtres générés
     * @param int $parcoursID Identifiant du parcours
     * @return string Code APOGEE de l'élément
     */
    private function checkCodeApogee(?string $codeApogee, string $identifier, bool $withChecks, int $parcoursID) : string {
        if($codeApogee === null && $withChecks){
            ExportElpApogeeCommand::$errorMessagesArray[$parcoursID][] = "Code APOGEE introuvable. {$identifier}";
        }
        if(mb_strlen($codeApogee ?? "") > 8){
            ExportElpApogeeCommand::$errorMessagesArray[$parcoursID][] = "Le Code APOGEE fait plus de 8 caractères. {$identifier}";
        }
        return $codeApogee ?? "ERROR";
    }

    /**
     * @param StructureEc $elementPedagogique Élément constitutif (matière)
     * @param int $length Longueur max de la chaîne à renvoyer
     * @param bool $withChecks Si la valeur est à vrai, des messages d'erreurs sont générés
     * @return string Libellé de l'EC raccourci à la longueur max $length
     */
    private function checkLibelleEC(
        StructureEc $elementPedagogique,
        int $length,
        int  $parcoursID,
        bool $withChecks = false
    ) : string {
        $libelle = $elementPedagogique->elementConstitutif->getFicheMatiere()?->getLibelle()
        ?? $elementPedagogique->elementConstitutif->getLibelle() ?? $elementPedagogique->elementConstitutif->getCode();
        if(count($elementPedagogique->elementsConstitutifsEnfants) > 0){
            $libelle = "EC A CHOIX";
        }
        if($libelle === null && $withChecks){
            ExportElpApogeeCommand::$errorMessagesArray[$parcoursID][] = "Le libellé de l'EC est 'null'. ID : {$elementPedagogique->elementConstitutif->getId()}";
        }

        return $this->prepareLibelle($libelle, $length);
    }

    /**
     * Vérification sur la nature de l'élément pédagogique
     * @param ?CodeNatuElpEnum $nature Nature de l'élément à tester
     * @param string $errorIdentifier Identifiant pour le message d'erreur
     * @param bool $withChecks Si des messages d'erreurs doivent être générés
     * @return string Nature de l'ELP
     */
    private function checkNatureElp(?CodeNatuElpEnum $nature, string $errorIdentifier, bool $withChecks, int $parcoursID) : string {
        if(($nature === null) && $withChecks){
            ExportElpApogeeCommand::$errorMessagesArray[$parcoursID][] = "La nature de l'ELP est incorrecte. {$errorIdentifier}";
        }
        return $nature !== null ? $nature->value : '';
    }

    /**
     * Vérifie le code composante fourni par le DTO
     * @param StructureParcours $dto DTO du parcours
     * @param string $errorIdentifier Indication pour le message d'erreur
     * @param bool $withChecks Vrai si des messages d'erreurs doivent être générés
     * @return string Code composante du parcours
     */
    private function checkCodeComposante(StructureParcours $dto, string $errorIdentifier, bool $withChecks) : string {
        if($dto->parcours->getComposanteInscription()?->getCodeComposante() === null && $withChecks){
            ExportElpApogeeCommand::$errorMessagesArray[$dto->parcours->getId()][] = "Le code de la composante porteuse est 'null'. {$errorIdentifier}";
        }

        return $dto->parcours->getComposanteInscription()?->getCodeComposante() ?? "ERROR";
    }

    /**
     * Vérifie le libellé de l'UE
     * @param StructureUe $elementPedagogique
     * @param StructureParcours $dto
     * @param int $length Longueur max de la chaîne à renvoyer
     * @param bool $withChecks Si des messages d'erreurs doivent être générés
     * @return string Le libellé réduit à la taille max
     */
    private function checkLibelleUE (
        StructureUe $elementPedagogique,
        StructureParcours $dto,
        int $length,
        bool $withChecks,
    ): string
    {
        $retour = // "UE " . $elementPedagogique->ue->getSemestre()->getOrdre() . "." . $elementPedagogique->ue->getOrdre()
            $elementPedagogique->display
        . " " . $dto->parcours->getFormation()->getTypeDiplome()->getLibelleCourt() . " ";
        $retour .= $dto->parcours->getFormation()?->getSigle() . " ";
        $retour .= $dto->parcours->isParcoursDefaut() ? "" : $dto->parcours->getSigle();
        if($withChecks){
            if($elementPedagogique->ue->getSemestre()?->getOrdre() === null){
                ExportElpApogeeCommand::$errorMessagesArray[$dto->parcours->getId()][] = "Le semestre de l'UE n'a pas d'ordre. UE {$elementPedagogique->ue->getId()}";
            }
            if($elementPedagogique->ue->getOrdre() === null){
                ExportElpApogeeCommand::$errorMessagesArray[$dto->parcours->getId()][] = "L'UE n'a pas d'ordre. Ue {$elementPedagogique->ue->getId()}";
            }
            if($dto->parcours->getFormation()->getTypeDiplome()->getLibelleCourt() === null){
                ExportElpApogeeCommand::$errorMessagesArray[$dto->parcours->getId()][] = "Pas de libellé court de type diplôme. UE {$elementPedagogique->ue->getId()}";
            }
            if($dto->parcours->getSigle() === null && $dto->parcours->isParcoursDefaut() === false){
                ExportElpApogeeCommand::$errorMessagesArray[$dto->parcours->getId()][] = "Pas de sigle sur le parcours. UE {$elementPedagogique->ue->getId()}";
            }
        }

        return $this->prepareLibelle($retour, $length);
    }

    /**
     * Vérifie le libellé d'un semestre en tant qu'élément pédagogique.
     * Permet de construire le futur libellé de l'ELP, en renvoyant le court ou le long
     * @param StructureSemestre $elementPedagogique Semestre du DTO à instancier
     * @param StructureParcours $dto
     * @param bool $withChecks Si des messages d'erreurs doivent être générés
     * @param string $type libelleCourt ou libelleLong
     */
    private function checkLibelleSemestre(
        StructureSemestre $elementPedagogique,
        StructureParcours $dto,
        bool $withChecks,
        string $type
    ): ?string {
        if( $elementPedagogique->semestre->getOrdre()
            && ($dto->parcours->getSigle() || ($dto->parcours->isParcoursDefaut() === true && $dto->parcours->getFormation()?->getSigle()))
            && $dto->parcours->getFormation()?->getSigle()
            && $dto->parcours->getFormation()->getTypeDiplome()->getLibelleCourt()
        ){
            if($type === "libelleLong"){
                return 'SEMESTRE ' . $elementPedagogique->ordre
                . ' '
                . $dto->parcours->getFormation()->getTypeDiplome()->getLibelleCourt()
                . ' '
                . $dto->parcours->getFormation()->getSigle()
                . ($dto->parcours->isParcoursDefaut() ? "" : ' ' . $dto->parcours->getSigle());

            }
            elseif($type === "libelleCourt"){
                return 'S' . $elementPedagogique->ordre
                . ' '
                . $dto->parcours->getFormation()->getTypeDiplome()->getLibelleCourt()
                . ' '
                . ($dto->parcours->isParcoursDefaut() ? $dto->parcours->getFormation()->getSigle() : $dto->parcours->getSigle());
            }
        } else {
            if($withChecks){
                // Ordre du semestre
                if($elementPedagogique->semestre->getOrdre() === null){
                    ExportElpApogeeCommand::$errorMessagesArray[$dto->parcours->getId()][] = "Le semestre n'a pas d'ordre - ID : {$elementPedagogique->semestre->getId()}";
                }
                // Sigle du parcours
                if($dto->parcours->getSigle() === null && $dto->parcours->isParcoursDefaut() === false){
                    ExportElpApogeeCommand::$errorMessagesArray[$dto->parcours->getId()][] = "Le Parcours n'a pas de sigle - ID : {$dto->parcours->getId()}";
                }
                // Sigle de la formation
                if($dto->parcours->getFormation()?->getSigle() === null){
                    ExportElpApogeeCommand::$errorMessagesArray[$dto->parcours->getId()][] = "La formation n'a pas de sigle - Parcours ID : {$dto->parcours->getId()} - Formation ID : {$dto->parcours->getFormation()->getId()}";
                }
                // Libellé court du type diplôme
                if($dto->parcours->getFormation()->getTypeDiplome()->getLibelleCourt() === null){
                    ExportElpApogeeCommand::$errorMessagesArray[$dto->parcours->getId()][] = "Le Type Diplôme n'a pas de libellé court - Type Diplôme ID : {$dto->parcours->getFormation()->getTypeDiplome()->getId()} - Formation ID : {$dto->parcours->getFormation()->getId()}";
                }
            }
            return "AUCUN LIBELLE";
        }

        return null;
    }
}
