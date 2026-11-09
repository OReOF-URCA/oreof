<?php

namespace App\Service;

use App\Classes\GetDpeParcours;
use App\DTO\StructureEc;
use App\DTO\StructureParcours;
use App\DTO\StructureUe;
use App\Entity\FicheMatiere;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\TypeDiplome;
use App\Enums\TypeModificationDpeEnum;
use App\TypeDiplome\Exceptions\TypeDiplomeNotFoundException;
use App\TypeDiplome\TypeDiplomeResolver;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Workflow\WorkflowInterface;

class ParcoursExportV2 {

    private EntityManagerInterface $entityManager;

    private UrlGeneratorInterface $router;

    private WorkflowInterface $ficheWorkflow;

    public function __construct(
        EntityManagerInterface $entityManager,
        protected TypeDiplomeResolver $typeDiplomeResolver,
        UrlGeneratorInterface $router,
        WorkflowInterface $ficheWorkflow
    ){
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->ficheWorkflow = $ficheWorkflow;
    }

    public function exportLastValidVersionMaquetteJson(
        StructureParcours $dto,
        Parcours $parcours,
        ?int $parcours_id = null,
        ?int $formation_id = null,
        bool $fermetureEmpty = false
    ): array
    {
        $typeDiplome = $parcours->getFormation()?->getTypeDiplome();
        if (null === $typeDiplome) {
            throw new TypeDiplomeNotFoundException();
        }

        return $this->getMaquetteJson($dto, $parcours, $typeDiplome, true, $parcours_id, $formation_id, $fermetureEmpty);
    }

    public function exportMaquetteJson(Parcours $parcours): array
    {

        $typeDiplome = $parcours->getFormation()?->getTypeDiplome();

        if (null === $typeDiplome) {
            throw new Exception('Type de diplôme non trouvé');
        }

        $typeD = $this->typeDiplomeResolver->fromTypeDiplome($typeDiplome);
        $dto = $typeD->calcul($parcours);

        return $this->getMaquetteJson($dto, $parcours, $typeDiplome)        ;
    }

    /**
     * @param bool $fermetureEmpty Ne pas récupérer les semestres d'un parcours fermés (ou non_ouverture)
     */
    private function getMaquetteJson(
        StructureParcours $dto,
        Parcours $parcours,
        TypeDiplome $typeDiplome,
        bool $isVersioning = false,
        ?int $parcours_id = null,
        ?int $formation_id = null,
        bool $fermetureEmpty = false
    ): array
    {
        
        $targetParcours = $parcours_id !== null
            ? $this->entityManager->getRepository(Parcours::class)->find($parcours_id)
            : $parcours;
        $dpe = GetDpeParcours::getFromParcours($targetParcours);
        $isFerme = in_array($dpe?->getEtatReconduction() ?? "empty", [
            TypeModificationDpeEnum::NON_OUVERTURE,
            TypeModificationDpeEnum::NON_OUVERTURE_CFVU,
            TypeModificationDpeEnum::NON_OUVERTURE_SES,
            TypeModificationDpeEnum::FERMETURE_DEFINITIVE
        ], true);

        $hasDisplaySemestre = true;
        if($fermetureEmpty && $isFerme){
            $hasDisplaySemestre = false;
        }

        $actualParcours = $targetParcours ?? $parcours;
        $actualFormation = $actualParcours->getFormation();

        $uuidParcours = $this->getUuidForParcours($actualParcours);
        $uuidFormation = $this->getUuidForFormation($actualFormation);
        $idOldParcours = $actualParcours->getParcoursOrigineCopie()?->getId();
        $idOldFormation = $actualFormation?->getFormationOrigineCopie()?->getId();

        $data = [
            'id' => $parcours->getId(),
            'uuid' => $uuidParcours,
            'uuid_parcours' => $uuidParcours,
            'id_precedent' => $idOldParcours,
            'id_old' => $idOldParcours,
            'formationId' => $parcours->getFormation()?->getId(),
            'formationUuid' => $uuidFormation,
            'uuid_formation' => $uuidFormation,
            'formation_id_precedent' => $idOldFormation,
            'formation_id_old' => $idOldFormation,
            'formation' => $parcours->getFormation()?->getDisplay(),
            'parcours' => $parcours->isParcoursDefaut() ? '' : $parcours->getLibelle(),
            'typeDiplome' => $typeDiplome->getLibelle(),
            'composante' => $parcours->getFormation()?->getComposantePorteuse()?->getLibelle(),
            'volumes'=> [
                'CM'=> [
                    'presentiel'=> $dto->heuresEctsFormation->sommeFormationCmPres,
                    'distanciel'=> $dto->heuresEctsFormation->sommeFormationCmDist
                ],
                'TD'=> [
                    'presentiel'=> $dto->heuresEctsFormation->sommeFormationTdPres,
                    'distanciel'=> $dto->heuresEctsFormation->sommeFormationTdDist
                ],
                'TP'=> [
                    'presentiel'=> $dto->heuresEctsFormation->sommeFormationTpPres,
                    'distanciel'=> $dto->heuresEctsFormation->sommeFormationTpDist
                ],
                'autonomie'=> $dto->heuresEctsFormation->sommeFormationTePres
            ],
            'ects' => $dto->heuresEctsFormation->sommeFormationEcts,
            'elements' => []
        ];

        if(!$isVersioning){
            $data['path'] = $this->router->generate(
                'api_site_web_v2_parcours_maquette',
                ['parcours' => $parcours->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
            $data['id'] = $parcours->getId();
            $data['formationId'] = $parcours->getFormation()?->getId();
        }

        if($isVersioning){
            $data['path'] = $this->router->generate(
                'api_site_web_v2_parcours_maquette_validee_cfvu',
                ['parcours' => $parcours_id],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
            $data['id'] = $parcours_id;
            $data['formationId'] = $formation_id;
        }

        if($hasDisplaySemestre){
            foreach ($dto->semestres as $ordre => $sem) {
                if ($sem->semestre->isNonDispense() === false) {
                    $semestre = [
                        'id' => $sem->semestre->getId(),
                        'typeNiveau' => 'semestre',
                        'libelleNiveau' => 'Semestre ' . ($ordre),
                        'ordre' => $ordre,
                        'volumes' => [
                            'CM' => [
                                'presentiel' => $sem->heuresEctsSemestre->sommeSemestreCmPres,
                                'distanciel' => $sem->heuresEctsSemestre->sommeSemestreCmDist
                            ],
                            'TD' => [
                                'presentiel' => $sem->heuresEctsSemestre->sommeSemestreTdPres,
                                'distanciel' => $sem->heuresEctsSemestre->sommeSemestreTdDist
                            ],
                            'TP' => [
                                'presentiel' => $sem->heuresEctsSemestre->sommeSemestreTpPres,
                                'distanciel' => $sem->heuresEctsSemestre->sommeSemestreTpDist
                            ],
                            'autonomie' => $sem->heuresEctsSemestre->sommeSemestreTePres
                        ],
                        'ects' => $sem->heuresEctsSemestre->sommeSemestreEcts,
                        'elements' => []
                    ];
                    foreach ($sem->ues as $ue) {
                        $tUe = [
                            'id' => $ue->ue?->getId(),
                            'typeNiveau' => 'ue',
                            'libelleNiveau' => $ue->display,
                            'ordre' => $ue->ordre(),
                            'libelleOrdre' => $ue->display,
                            'libelle' => $ue->ue->getLibelle() ?? $ue->display,
                            'volumes' => [
                                'CM' => [
                                    'presentiel' => $ue->heuresEctsUe->sommeUeCmPres,
                                    'distanciel' => $ue->heuresEctsUe->sommeUeCmDist
                                ],
                                'TD' => [
                                    'presentiel' => $ue->heuresEctsUe->sommeUeTdPres,
                                    'distanciel' => $ue->heuresEctsUe->sommeUeTdDist
                                ],
                                'TP' => [
                                    'presentiel' => $ue->heuresEctsUe->sommeUeTpPres,
                                    'distanciel' => $ue->heuresEctsUe->sommeUeTpDist
                                ],
                                'autonomie' => $ue->heuresEctsUe->sommeUeTePres
                            ],
                            'ects' => $ue->heuresEctsUe->sommeUeEcts
                        ];

                        if ($ue->ue->getNatureUeEc()?->isLibre()) {
                            if(!$isVersioning){
                                $tUe['ects'] = $ue->ue->getEcts() ?? 0.0;
                            }
                            $tUe['description_libre_choix'] = $ue->ue->getDescriptionUeLibre();
                        } elseif ($ue->ue->getNatureUeEc()?->isChoix() || count($ue->uesEnfants()) > 0) {
                            $tUe['description_libre_choix'] = $ue->ue->getDescriptionUeLibre();
                            $tUe['elements'] = [];
                            $nb = 0;
                            foreach ($ue->uesEnfants() as $ueEnfant) {
                                $tUeEnfant = [
                                    'id' => $ueEnfant->ue?->getId(),
                                    'typeNiveau' => 'ue',
                                    'libelleNiveau' => $ueEnfant->display,
                                    'ordre' => $ueEnfant->ordre(),
                                    'libelleOrdre' => $ueEnfant->display,
                                    'libelle' => $ueEnfant->ue->getLibelle() ?? $ueEnfant->display,
                                    'volumes' => [
                                        'CM' => [
                                            'presentiel' => $ueEnfant->heuresEctsUe->sommeUeCmPres,
                                            'distanciel' => $ueEnfant->heuresEctsUe->sommeUeCmDist
                                        ],
                                        'TD' => [
                                            'presentiel' => $ueEnfant->heuresEctsUe->sommeUeTdPres,
                                            'distanciel' => $ueEnfant->heuresEctsUe->sommeUeTdDist
                                        ],
                                        'TP' => [
                                            'presentiel' => $ueEnfant->heuresEctsUe->sommeUeTpPres,
                                            'distanciel' => $ueEnfant->heuresEctsUe->sommeUeTpDist
                                        ],
                                        'autonomie' => $ueEnfant->heuresEctsUe->sommeUeTePres
                                    ],
                                    'ects' => $ueEnfant->heuresEctsUe->sommeUeEcts,

                                ];
                                if ($ueEnfant->ue->getNatureUeEc()?->isLibre()) {
                                    $tUeEnfant['description_libre_choix'] = $ueEnfant->ue->getDescriptionUeLibre();
                                }

                                $nb++;
                                $tUe['nbChoix'] = $nb;
                                $tUeEnfant['elements'] = $this->getEcFromUe($ueEnfant, 3, $isVersioning, $typeDiplome);

                                /**
                                 * UE enfant dans une UE enfant
                                 */
                                if($ueEnfant->ue->getNatureUeEc()?->isLibre()){
                                    if(!$isVersioning){
                                        $tUeEnfant['ects'] = $ueEnfant->ue->getEcts() ?? 0.0;
                                    }
                                    $tUeEnfant['description_libre_choix'] = $ueEnfant->ue->getDescriptionUeLibre();
                                }
                                elseif($ueEnfant->ue->getNatureUeEc()?->isChoix() || count($ueEnfant->uesEnfants()) > 0){
                                    $nbDeuxiemeNiveau = 0;
                                    $tUeEnfant['description_libre_choix'] = $ueEnfant->ue->getDescriptionUeLibre();
                                    $tUeEnfant['elements'] = [];
                                    foreach($ueEnfant->uesEnfants() as $ueEnfantDeuxiemeNiveau){
                                        $tUeEnfantDeuxiemeNiveau = [
                                            'id' => $ueEnfantDeuxiemeNiveau->ue?->getId(),
                                            'typeNiveau' => 'ue',
                                            'libelleNiveau' => $ueEnfantDeuxiemeNiveau->display,
                                            'ordre' => $ueEnfantDeuxiemeNiveau->ordre(),
                                            'libelleOrdre' => $ueEnfantDeuxiemeNiveau->display,
                                            'libelle' => $ueEnfantDeuxiemeNiveau->ue->getLibelle() ?? $ueEnfantDeuxiemeNiveau->display,
                                            'volumes' => [
                                                'CM' => [
                                                    'presentiel' => $ueEnfantDeuxiemeNiveau->heuresEctsUe->sommeUeCmPres,
                                                    'distanciel' => $ueEnfantDeuxiemeNiveau->heuresEctsUe->sommeUeCmDist
                                                ],
                                                'TD' => [
                                                    'presentiel' => $ueEnfantDeuxiemeNiveau->heuresEctsUe->sommeUeTdPres,
                                                    'distanciel' => $ueEnfantDeuxiemeNiveau->heuresEctsUe->sommeUeTdDist
                                                ],
                                                'TP' => [
                                                    'presentiel' => $ueEnfantDeuxiemeNiveau->heuresEctsUe->sommeUeTpPres,
                                                    'distanciel' => $ueEnfantDeuxiemeNiveau->heuresEctsUe->sommeUeTpDist
                                                ],
                                                'autonomie' => $ueEnfantDeuxiemeNiveau->heuresEctsUe->sommeUeTePres
                                            ],
                                            'ects' => $ueEnfantDeuxiemeNiveau->heuresEctsUe->sommeUeEcts,

                                        ];
                                        if ($ueEnfantDeuxiemeNiveau->ue->getNatureUeEc()?->isLibre()) {
                                            $tUeEnfantDeuxiemeNiveau['description_libre_choix'] = $ueEnfantDeuxiemeNiveau->ue->getDescriptionUeLibre();
                                        }

                                        $nbDeuxiemeNiveau++;
                                        $tUeEnfant['nbChoix'] = $nbDeuxiemeNiveau;
                                        $tUeEnfantDeuxiemeNiveau['niveau5'] = $this->getEcFromUe($ueEnfantDeuxiemeNiveau, 4, $isVersioning, $typeDiplome);
                                        $tUeEnfant['elements'][] = $tUeEnfantDeuxiemeNiveau;
                                    }
                                }

                                $tUe['elements'][] = $tUeEnfant;
                            }
                        } else {
                            $tUe['ects'] = $ue->heuresEctsUe->sommeUeEcts;
                            $tUe['elements'] = $this->getEcFromUe($ue, 2, $isVersioning, $typeDiplome);
                        }
                        $semestre['elements'][] = $tUe;
                    }
                    if($isVersioning){
                        usort($semestre['elements'], fn($ueA, $ueB) => $ueA['ordre'] <=> $ueB['ordre']);
                    }
                    $data['elements'][] = $semestre;
                }
            }
        }
        return $data;
    }

    public function getMaquetteJsonMinimum(Parcours $p, TypeDiplome $td) {
        $volumesVides = ['presentiel' => 0, 'distanciel' => 0];

        $data = [
            'id' => $p->getId(),
            'uuid' => $this->getUuidForParcours($p),
            'uuid_parcours' => $this->getUuidForParcours($p),
            'id_precedent' => $p->getParcoursOrigineCopie()?->getId(),
            'id_old' => $p->getParcoursOrigineCopie()?->getId(),
            'formationId' => $p->getFormation()?->getId(),
            'formationUuid' => $this->getUuidForFormation($p->getFormation()),
            'uuid_formation' => $this->getUuidForFormation($p->getFormation()),
            'formation_id_precedent' => $p->getFormation()?->getFormationOrigineCopie()?->getId(),
            'formation_id_old' => $p->getFormation()?->getFormationOrigineCopie()?->getId(),
            'formation' => $p->getFormation()?->getDisplay(),
            'parcours' => $p->isParcoursDefaut() ? '' : $p->getLibelle(),
            'typeDiplome' => $td->getLibelle(),
            'composante' => $p->getFormation()?->getComposantePorteuse()?->getLibelle(),
            'volumes' => [
                'CM' => $volumesVides,
                'TD' => $volumesVides,
                'TP' => $volumesVides,
                'autonomie' => 0
            ],
            'ects' => 0,
            'elements' => [],
            'path' => $this->router->generate(
                'app_parcours_export_maquette_json_minimum_v2',
                ['parcours' => $p->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            )
        ];

        return $data;
    }

    private function getEcFromUe(StructureUe $ue, int $parentDepth = 0, bool $isVersioning = false, TypeDiplome $td): array
    {
        $tEcs = [];
        $depth = $parentDepth + 1;
        foreach ($ue->elementConstitutifs as $ec) {
            if ($ec->elementConstitutif->getNatureUeEc()?->isLibre()) {
                $tEcs['description_libre_choix'] =  $ec->elementConstitutif->getTexteEcLibre();
            } elseif ($ec->elementConstitutif->getNatureUeEc()?->isChoix() || count($ec->elementsConstitutifsEnfants) > 0) {
                $tEc['id'] = $ec->elementConstitutif->getId();
                $tEc['typeNiveau'] = 'ec';
                $tEc['ordre'] = $ec->elementConstitutif->getOrdre();
                $tEc['numero'] = $ec->elementConstitutif->getCode();
                $tEc['libelle'] = $ec->elementConstitutif?->getFicheMatiere()?->getLibelle() ?? '-';
                $tEc['libelleNiveau'] = $tEc['numero'] . ' - ' . $tEc['libelle'];
                $childKey = 'niveau' . ($depth + 1);
                $tEc[$childKey] =  [];
                $tEc['description_libre_choix'] =  $ec->elementConstitutif->getTexteEcLibre();
                $nb = 0;
                foreach ($ec->elementsConstitutifsEnfants as $ecEnfant) {
                    $tEc[$childKey][] = $this->getEc($ecEnfant, $isVersioning, $td);
                    $nb++;
                }
                $tEc['nbChoix'] =  $nb;

                $tEcs[] = $tEc;
            } else {
                $tEcs[] = $this->getEc($ec, $isVersioning, $td);
            }
        }
        return $tEcs;
    }

    private function getEc(StructureEc $ec, bool $isVersioning = false, TypeDiplome $td): array
    {

        $ficheMatiere = $ec->elementConstitutif->getFicheMatiere();
        $elementConstitutif = $ec;
        $isEcFromBD = false;

        if($isVersioning){
            $ficheMatiere = $this->entityManager
                ->getRepository(FicheMatiere::class)
                ->findOneBy([
                    'slug' => $ec->elementConstitutif->getFicheMatiere()?->getSlug()
                ]);
            if($ficheMatiere){
                $etatWorkflow = $this->ficheWorkflow
                    ->getMarking($ficheMatiere)
                    ->getPlaces();
                $ficheMatiere = $etatWorkflow === ["publie" => 1]
                || $etatWorkflow === ['valide_pour_publication' => 1]
                ? $ficheMatiere
                :null;

                if($ficheMatiere){
                    foreach($ficheMatiere->getElementConstitutifs() as $ecFM){
                        if($ecFM->getParcours()?->getId() === $ficheMatiere->getParcours()?->getId()){
                            $elementConstitutif = $ecFM;
                            $isEcFromBD = true;
                        }
                    }
                }
            }
        }

        if ($ficheMatiere !== null &&
            (array_key_exists('publie', $ficheMatiere->getEtatFiche()) ||
            array_key_exists('valide_pour_publication', $ficheMatiere->getEtatFiche()))
        ) {
            $valide = true;
        } else {
            $valide = false;
        }

        if ($ec->elementConstitutif->getNatureUeEc()?->isLibre()) {
            $libelle = $ec->elementConstitutif->getTexteEcLibre();
            $ecLibre = true;
        } else {
            $libelle = $ficheMatiere?->getLibelle() ?? $ec->elementConstitutif->getLibelle() ?? $ec->elementConstitutif->display();
            $ecLibre = false;
        }

        $competencesFM = [];
        if($ficheMatiere !== null) {
            if($td->getLibelleCourt() === 'BUT') {
                $competencesFM = array_map(fn($app) => ['code' => $app->getCode(), 'libelle' => $app->getLibelle()],
                    $ficheMatiere->getApprentissagesCritiques()?->toArray() ?? []
                );
            }
            else {
                $competencesFM = array_map(fn($a) => ['code' => $a->getCode(), 'libelle' => $a->getLibelle()],
                    $ficheMatiere->getCompetences()?->toArray() ?? []
                );
            }
        }

        return [
            'id' => $isEcFromBD ? $elementConstitutif->getId() : $ec->elementConstitutif->getId(),
            'typeNiveau' => 'ec',
            'libelleNiveau' => $ec->elementConstitutif->getCode() . ' - ' . $libelle,
            'ordre' => $ec->elementConstitutif->getOrdre(),
            'valide' => $valide,
            'ec_libre' => $ecLibre,
            'ec_choix' => $ec->elementConstitutif->getNatureUeEc()?->isChoix(),
            'nature_ec' => $isEcFromBD ? $elementConstitutif->getTypeEc()?->getLibelle() : $ec->elementConstitutif->getTypeEc()?->getLibelle(),
            'valide_date' => new DateTime(),
            'numero'=> $ec->elementConstitutif->getCode(),
            'libelle'=> $libelle,
            'libelle_anglais' => $ficheMatiere?->getLibelleAnglais() ?? '-',
            'sigle'=> $ficheMatiere?->getSigle() ?? '-',
            'fiche_matiere_slug' => $ficheMatiere?->getSlug(),
            'enseignant_referent' => [
                'nom'=> $ficheMatiere?->getResponsableFicheMatiere()?->getDisplay() ?? '-',
                'email'=> $ficheMatiere?->getResponsableFicheMatiere()?->getEmail() ?? '-'
            ],
            // 'description' => CleanTexte::cleanTextArea($ficheMatiere?->getDescription()) ?? '-',
            // 'objectifs' => CleanTexte::cleanTextArea($ficheMatiere?->getObjectifs()) ?? '-',
            'description' => $ficheMatiere?->getDescription() ?? '-',
            'objectifs' => $ficheMatiere?->getObjectifs() ?? '-',
            'modalite_enseignement' => ($modaliteVal = $ficheMatiere?->getModaliteEnseignementValue()) !== null
                ? (($legacy = \App\Referentiel\ModaliteEnseignement::legacyCase($modaliteVal)) !== null ? $legacy->value : $modaliteVal)
                : '-',
            'langues_supports' => $ficheMatiere?->getLanguesSupportsArray() ?? [],
            'langues_dispense_cours' => $ficheMatiere?->getLanguesDispenseArray() ?? [],
            'ects'=> $ec->heuresEctsEc->ects,
            'volumes'=> [
                'CM'=> [
                    'presentiel'=> $ec->heuresEctsEc->cmPres,
                    'distanciel'=> $ec->heuresEctsEc->cmDist
                ],
                'TD'=> [
                    'presentiel'=> $ec->heuresEctsEc->tdPres,
                    'distanciel'=> $ec->heuresEctsEc->tdDist
                ],
                'TP'=> [
                    'presentiel'=> $ec->heuresEctsEc->tpPres,
                    'distanciel'=> $ec->heuresEctsEc->tpDist
                ],
                'autonomie'=> $ec->heuresEctsEc->tePres
            ],
            'competences' => $competencesFM
        ];
    }

    /**
     * Identifiant stable de la version d'origine racine (toute première version sans copie en amont)
     */
    private function getUuidForParcours(Parcours $parcours): ?int
    {
        $oldest = $parcours;
        $visited = [];
        while ($oldest->getParcoursOrigineCopie() !== null && !isset($visited[$oldest->getId()])) {
            $visited[$oldest->getId()] = true;
            $oldest = $oldest->getParcoursOrigineCopie();
        }

        return $oldest->getId();
    }

    /**
     * Identifiant stable de la formation d'origine racine (toute première version sans copie en amont)
     */
    private function getUuidForFormation(?Formation $formation): ?int
    {
        if ($formation === null) {
            return null;
        }

        $oldest = $formation;
        $visited = [];
        while ($oldest->getFormationOrigineCopie() !== null && !isset($visited[$oldest->getId()])) {
            $visited[$oldest->getId()] = true;
            $oldest = $oldest->getFormationOrigineCopie();
        }

        return $oldest->getId();
    }
}
