<?php

namespace App\Service;

use App\DTO\StructureEc;
use App\DTO\StructureParcours;
use App\DTO\StructureUe;
use App\Entity\FicheMatiere;
use App\Entity\Parcours;
use App\Entity\TypeDiplome;
use App\TypeDiplome\Exceptions\TypeDiplomeNotFoundException;
use App\TypeDiplome\TypeDiplomeResolver;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Workflow\WorkflowInterface;

class ParcoursExport {

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
        ?int $formation_id = null
    ): array
    {
        $typeDiplome = $parcours->getFormation()?->getTypeDiplome();
        if (null === $typeDiplome) {
            throw new TypeDiplomeNotFoundException();
        }

        return $this->getMaquetteJson($dto, $parcours, $typeDiplome, true, $parcours_id, $formation_id);
    }

    public function exportMaquetteJson(Parcours $parcours): array
    {

        $typeDiplome = $parcours->getFormation()?->getTypeDiplome();

        if (null === $typeDiplome) {
            throw new Exception('Type de diplôme non trouvé');
        }

        $typeD = $this->typeDiplomeResolver->fromTypeDiplome($typeDiplome);
        $dto = $typeD->calculStructureParcours($parcours);

        return $this->getMaquetteJson($dto, $parcours, $typeDiplome)        ;
    }

    private function getMaquetteJson(
        StructureParcours $dto,
        Parcours $parcours,
        TypeDiplome $typeDiplome,
        bool $isVersioning = false,
        ?int $parcours_id = null,
        ?int $formation_id = null
    ): array
    {
        $data = [
            'id' => $parcours->getId(),
            'formationId' => $parcours->getFormation()?->getId(),
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
            'niveau1' => []
        ];

        if(!$isVersioning){
            $data['path'] = $this->router->generate(
                'app_parcours_export_maquette_json',
                ['parcours' => $parcours->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
            $data['id'] = $parcours->getId();
            $data['formationId'] = $parcours->getFormation()?->getId();
        }

        if($isVersioning){
            $data['path'] = $this->router->generate(
                'app_parcours_export_maquette_json_validee_cfvu',
                ['parcours' => $parcours_id],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
            $data['id'] = $parcours_id;
            $data['formationId'] = $formation_id;
        }

        if($parcours->getId() !== null){
           $data['path'] = $this->router->generate(
                'app_parcours_export_maquette_json',
                ['parcours' => $parcours->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
           );
        }

        foreach ($dto->semestres as $ordre => $sem) {
            if ($sem->semestre->isNonDispense() === false) {
                $semestre = [
                    'typeNiveau' => 'semestre',
                    'libelleNiveau' => 'Semestre ' . ($ordre + 1),
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
                    'niveau2' => []
                ];
                foreach ($sem->ues as $ue) {
                    $tUe = [
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
                        $tUe['niveau3'] = [];
                        $nb = 0;
                        foreach ($ue->uesEnfants() as $ueEnfant) {
                            $tUeEnfant = [
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
                            $tUeEnfant['niveau4'] = $this->getEcFromUe($ueEnfant, 3, $isVersioning);

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
                                $tUeEnfant['niveau4'] = [];
                                foreach($ueEnfant->uesEnfants() as $ueEnfantDeuxiemeNiveau){
                                    $tUeEnfantDeuxiemeNiveau = [
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
                                    $tUeEnfantDeuxiemeNiveau['niveau5'] = $this->getEcFromUe($ueEnfantDeuxiemeNiveau, 4, $isVersioning);
                                    $tUeEnfant['niveau4'][] = $tUeEnfantDeuxiemeNiveau;
                                }
                            }

                            $tUe['niveau2'][] = $tUeEnfant;
                        }
                    } else {
                        $tUe['ects'] = $ue->heuresEctsUe->sommeUeEcts;
                        $tUe['niveau3'] = $this->getEcFromUe($ue, 2, $isVersioning);
                    }
                    $semestre['niveau2'][] = $tUe;
                }
                if($isVersioning){
                    usort($semestre['niveau2'], fn($ueA, $ueB) => $ueA['ordre'] <=> $ueB['ordre']);
                }
                $data['niveau1'][] = $semestre;
            }
        }

        return $data;
    }

    private function getEcFromUe(StructureUe $ue, int $parentDepth, bool $isVersioning = false): array
    {
        $tEcs = [];
        $depth = $parentDepth + 1;
        foreach ($ue->elementConstitutifs as $ec) {
            if ($ec->elementConstitutif->getNatureUeEc()?->isLibre()) {
                $tEcs['description_libre_choix'] =  $ec->elementConstitutif->getTexteEcLibre();
            } elseif ($ec->elementConstitutif->getNatureUeEc()?->isChoix() || count($ec->elementsConstitutifsEnfants) > 0) {
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
                    $tEc[$childKey][] = $this->getEc($ecEnfant, $isVersioning);
                    $nb++;
                }
                $tEc['nbChoix'] =  $nb;

                $tEcs[] = $tEc;
            } else {
                $tEcs[] = $this->getEc($ec, $isVersioning);
            }
        }
        return $tEcs;
    }

    private function getEc(StructureEc $ec, bool $isVersioning = false): array
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
            $libelle = $ficheMatiere?->getLibelle() ?? $ec->elementConstitutif->getLibelle() ?? $ec->elementConstitutif->display() ?? '-';
            $ecLibre = false;
        }

        return [
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
            'modalite_enseignement'=> $ficheMatiere?->getModaliteEnseignement()->value ?? '-',
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
        ];
    }
}
