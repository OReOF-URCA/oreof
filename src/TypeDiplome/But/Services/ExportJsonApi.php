<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv1/src/TypeDiplome/Licence/Services/ExportJsonApi.php
 * @author davidannebicque
 * @project oreofv1
 * @lastUpdate 13/07/2026 10:15
 */

namespace App\TypeDiplome\But\Services;

use App\DTO\StructureEc;
use App\DTO\StructureParcours;
use App\DTO\StructureUe;
use App\Entity\Parcours;
use App\TypeDiplome\ExportJsonInterface;
use App\Utils\CleanTexte;
use DateTime;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class ExportJsonApi implements ExportJsonInterface
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function exportJson(StructureParcours $dto, Parcours $parcours): array
    {
        $typeDiplome = $parcours->getFormation()?->getTypeDiplome();

        $data = [
            'path' => $this->urlGenerator->generate('app_parcours_export_maquette_json_urca_v2_niveau', ['parcours' => $parcours->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            'id' => $parcours->getId(),
            'formationId' => $parcours->getFormation()?->getId(),
            'formation' => $parcours->getFormation()?->getDisplay() ?? '',
            'parcours' => $parcours->isParcoursDefaut() ? '' : $parcours->getLibelle() ?? '',
            'typeDiplome' => $typeDiplome->getLibelle(),
            'composante' => $parcours->getFormation()?->getComposantePorteuse()?->getLibelle() ?? '',
            'volumes' => [
                'CM' => [
                    'presentiel' => $dto->heuresEctsFormation->sommeFormationCmPres,
                    'distanciel' => $dto->heuresEctsFormation->sommeFormationCmDist
                ],
                'TD' => [
                    'presentiel' => $dto->heuresEctsFormation->sommeFormationTdPres,
                    'distanciel' => $dto->heuresEctsFormation->sommeFormationTdDist
                ],
                'TP' => [
                    'presentiel' => $dto->heuresEctsFormation->sommeFormationTpPres,
                    'distanciel' => $dto->heuresEctsFormation->sommeFormationTpDist
                ],
                'autonomie' => $dto->heuresEctsFormation->sommeFormationTePres
            ],
            'ects' => $dto->heuresEctsFormation->sommeFormationEcts,
            'elements' => []
        ];

        foreach ($dto->semestres as $ordre => $sem) {
            if ($sem->semestre->isNonDispense() === false && $sem->semestreParcours->isOuvert() === true) {
                $tEcs = [];
                $semestre = [
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
                    'elements' => [],
                    'competences' => []
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
                        'ects' => $ue->heuresEctsUe->sommeUeEcts,
                    ];
                    $semestre['competences'][] = $tUe;
                    $tUe['ects'] = $ue->heuresEctsUe->sommeUeEcts;
                    $ueLibelle = $ue->ue->getLibelle() ?? $ue->display;
                    $ec = $this->getEcFromUe($ue, 2, $ueLibelle); // les EC du semestre
                    //vérifier si l'EC est déjà dans $tEcs, si oui ne pas l'ajouter
                    foreach ($ec as $ecItem) {
                        $exists = false;
                        foreach ($tEcs as $index => $existingEc) {
                            if ($existingEc['numero'] === $ecItem['numero']) {
                                $exists = true;
                                $tEcs[$index]['ues'] = array_values(array_unique(array_merge(
                                    $existingEc['ues'] ?? [],
                                    $ecItem['ues'] ?? []
                                )));
                                break;
                            }
                        }
                        if (!$exists) {
                            $tEcs[] = $ecItem;
                        }
                    }
                }

                // trier les EC par ordre
                usort($tEcs, function ($a, $b) {
                    return $a['ordre'] <=> $b['ordre'];
                });

                $semestre['elements'] = $tEcs;

                $data['elements'][] = $semestre;
            }
        }

        return $data;
    }

    private function getEcFromUe(StructureUe $ue, int $parentDepth, string $ueLibelle): array
    {
        $tEcs = [];
        $depth = $parentDepth + 1;
        foreach ($ue->elementConstitutifs as $ec) {
            if ($ec->elementConstitutif->getNatureUeEc()?->isChoix()) {
                $tEc['typeNiveau'] = 'ec';
                $tEc['ordre'] = $ec->elementConstitutif->getOrdre();
                $tEc['numero'] = $ec->elementConstitutif->getCode();
                $tEc['libelle'] = $ec->elementConstitutif?->getFicheMatiere()?->getLibelle() ?? '-';
                $tEc['libelleNiveau'] = $tEc['numero'] . ' - ' . $tEc['libelle'];
                $tEc['ues'] = [$ueLibelle];
                $childKey = 'elements';
                $tEc[$childKey] = [];
                $tEc['description_libre_choix'] = $ec->elementConstitutif->getTexteEcLibre();
                $nb = 0;
                foreach ($ec->elementsConstitutifsEnfants as $ecEnfant) {
                    $tEc[$childKey][] = $this->getEc($ecEnfant, $ueLibelle);
                    $nb++;
                }
                $tEc['nbChoix'] = $nb;

                $tEcs[] = $tEc;
            } else {
                $tEcs[] = $this->getEc($ec, $ueLibelle);
            }
        }
        return $tEcs;
    }

    private function getEc(StructureEc $ec, string $ueLibelle): array
    {
        if ($ec->elementConstitutif->getFicheMatiere() !== null &&
            (array_key_exists('publie', $ec->elementConstitutif->getFicheMatiere()->getEtatFiche()) ||
                array_key_exists('valide_pour_publication', $ec->elementConstitutif->getFicheMatiere()->getEtatFiche()))
        ) {
            $valide = true;
        } else {
            $valide = false;
        }

        if ($ec->elementConstitutif->getNatureUeEc()?->isLibre()) {
            $libelle = $ec->elementConstitutif->getLibelle();
            $description = $ec->elementConstitutif->getTexteEcLibre();
            $ecLibre = true;
        } else {
            $libelle = $ec->elementConstitutif->getFicheMatiere()?->getLibelle() ?? $ec->elementConstitutif->getLibelle() ?? '-';
            $description = $ec->elementConstitutif->getFicheMatiere()?->getDescription() ?? '-';
            $ecLibre = false;
        }

        return [
            'typeNiveau' => 'ec',
            'libelleNiveau' => $ec->elementConstitutif->getCode() . ' - ' . $libelle,
            'ordre' => $ec->elementConstitutif->getOrdre(),
            'valide' => $valide,
            'ec_libre' => $ecLibre,
            'nature_ec' => $ec->elementConstitutif->getTypeEc()?->getLibelle(),
            'valide_date' => new DateTime(),
            'numero' => $ec->elementConstitutif->getCode(),
            'libelle' => $libelle,
            'ues' => [$ueLibelle],
            'libelle_anglais' => $ec->elementConstitutif->getFicheMatiere()?->getLibelleAnglais() ?? '-',
            'sigle' => $ec->elementConstitutif->getFicheMatiere()?->getSigle() ?? '-',
            'enseignant_referent' => [
                'nom' => $ec->elementConstitutif->getFicheMatiere()?->getResponsableFicheMatiere()?->getDisplay() ?? '-',
                'email' => $ec->elementConstitutif->getFicheMatiere()?->getResponsableFicheMatiere()?->getEmail() ?? '-'
            ],
            'description' => CleanTexte::cleanTextArea($description, false) ?? '-',
            'objectifs' => CleanTexte::cleanTextArea($ec->elementConstitutif->getFicheMatiere()?->getObjectifs(), false) ?? '-',
            'modalite_enseignement' => $ec->elementConstitutif->getFicheMatiere()?->getModaliteEnseignement()->value ?? '-',
            'langues_supports' => $ec->elementConstitutif->getFicheMatiere()?->getLanguesSupportsArray() ?? [],
            'langues_dispense_cours' => $ec->elementConstitutif->getFicheMatiere()?->getLanguesDispenseArray() ?? [],
            'ects' => $ec->heuresEctsEc->ects,
            'volumes' => [
                'CM' => [
                    'presentiel' => $ec->heuresEctsEc->cmPres,
                    'distanciel' => $ec->heuresEctsEc->cmDist
                ],
                'TD' => [
                    'presentiel' => $ec->heuresEctsEc->tdPres,
                    'distanciel' => $ec->heuresEctsEc->tdDist
                ],
                'TP' => [
                    'presentiel' => $ec->heuresEctsEc->tpPres,
                    'distanciel' => $ec->heuresEctsEc->tpDist
                ],
                'autonomie' => $ec->heuresEctsEc->tePres
            ],
        ];
    }
}
