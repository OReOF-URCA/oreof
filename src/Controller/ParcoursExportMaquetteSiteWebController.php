<?php

namespace App\Controller;

use App\DTO\StructureEc;
use App\DTO\StructureUe;
use App\Entity\Parcours;
use App\Service\TypeDiplomeResolver;
use App\Utils\CleanTexte;
use DateTime;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ParcoursExportMaquetteSiteWebController extends AbstractController
{
    #[Route('/parcours/{parcours}/maquette/v2/export-json-urca', name: 'app_parcours_export_maquette_json_urca_v2_niveau')]
    public function exportMaquetteJson(
        Parcours                $parcours,
        TypeDiplomeResolver $typeDiplomeResolver,
    ): Response {
        $typeDiplome = $parcours->getFormation()?->getTypeDiplome();

        if (null === $typeDiplome) {
            throw new Exception('Type de diplôme non trouvé');
        }

        $typeD = $typeDiplomeResolver->get($typeDiplome);
        $dto = $typeD->calculStructureParcours($parcours);

        $data = [
            'path' => $this->generateUrl('app_parcours_export_maquette_json_urca_v2_niveau', ['parcours' => $parcours->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            'id' => $parcours->getId(),
            'formationId' => $parcours->getFormation()?->getId(),
            'formation' => $parcours->getFormation()?->getDisplay() ?? '',
            'parcours' => $parcours->isParcoursDefaut() ? '' : $parcours->getLibelle() ?? '',
            'typeDiplome' => $typeDiplome->getLibelle(),
            'composante' => $parcours->getFormation()?->getComposantePorteuse()?->getLibelle() ?? '',
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

        foreach ($dto->semestres as $ordre => $sem) {
            if ($sem->semestre->isNonDispense() === false && $sem->semestreParcours->isOuvert() === true) {
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
                        'ects' => $ue->heuresEctsUe->sommeUeEcts,
                    ];

                    if ($ue->ue->getNatureUeEc()?->isLibre()) {
                        $tUe['ects'] = $ue->ue->getEcts() ?? 0.0;
                        $tUe['description_libre_choix'] = $ue->ue->getDescriptionUeLibre();
                    } elseif ($ue->ue->getNatureUeEc()?->isChoix()) {
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
                            $tUeEnfant['niveau4'] = $this->getEcFromUe($ueEnfant, 3);

                            $nbChoixDeuxiemeNiveau = 0;
                            if(count($ueEnfant->uesEnfants()) > 0){
                                $tUeEnfant['niveau4'] = [];
                            }
                            foreach($ueEnfant->uesEnfants() as $ueEnfantDeuxieme){
                                $tUeEnfantDeuxieme = [
                                    'typeNiveau' => 'ue',
                                    'libelleNiveau' => $ueEnfantDeuxieme->display,
                                    'ordre' => $ueEnfantDeuxieme->ordre(),
                                    'libelleOrdre' => $ueEnfantDeuxieme->display,
                                    'libelle' => $ueEnfantDeuxieme->ue->getLibelle() ?? $ueEnfantDeuxieme->display,
                                    'volumes' => [
                                        'CM' => [
                                            'presentiel' => $ueEnfantDeuxieme->heuresEctsUe->sommeUeCmPres,
                                            'distanciel' => $ueEnfantDeuxieme->heuresEctsUe->sommeUeCmDist
                                        ],
                                        'TD' => [
                                            'presentiel' => $ueEnfantDeuxieme->heuresEctsUe->sommeUeTdPres,
                                            'distanciel' => $ueEnfantDeuxieme->heuresEctsUe->sommeUeTdDist
                                        ],
                                        'TP' => [
                                            'presentiel' => $ueEnfantDeuxieme->heuresEctsUe->sommeUeTpPres,
                                            'distanciel' => $ueEnfantDeuxieme->heuresEctsUe->sommeUeTpDist
                                        ],
                                        'autonomie' => $ueEnfantDeuxieme->heuresEctsUe->sommeUeTePres
                                    ],
                                    'ects' => $ueEnfantDeuxieme->heuresEctsUe->sommeUeEcts,

                                ];
                                if ($ueEnfantDeuxieme->ue->getNatureUeEc()?->isLibre()) {
                                    $tUeEnfantDeuxieme['description_libre_choix'] = $ueEnfantDeuxieme->ue->getDescriptionUeLibre();
                                }

                                ++$nbChoixDeuxiemeNiveau;
                                $tUeEnfant['nbChoix'] = $nbChoixDeuxiemeNiveau;
                                $tUeEnfantDeuxieme['niveau5'] = $this->getEcFromUe($ueEnfantDeuxieme, 4);
                                $tUeEnfant['niveau4'][] = $tUeEnfantDeuxieme;
                            }

                            $tUe['niveau3'][] = $tUeEnfant;
                        }
                    } else {
                        $tUe['ects'] = $ue->heuresEctsUe->sommeUeEcts;
                        $tUe['niveau3'] = $this->getEcFromUe($ue, 2);
                    }
                    $semestre['niveau2'][] = $tUe;
                }

                $data['niveau1'][] = $semestre;
            }
        }

        return $this->json($data);
    }

    private function getEcFromUe(StructureUe $ue, int $parentDepth): array
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
                $childKey = 'niveau' . ($depth + 1);
                $tEc[$childKey] =  [];
                $tEc['description_libre_choix'] =  $ec->elementConstitutif->getTexteEcLibre();
                $nb = 0;
                foreach ($ec->elementsConstitutifsEnfants as $ecEnfant) {
                    $tEc[$childKey][] = $this->getEc($ecEnfant);
                    $nb++;
                }
                $tEc['nbChoix'] =  $nb;

                $tEcs[] = $tEc;
            } else {
                $tEcs[] = $this->getEc($ec);
            }
        }
        return $tEcs;
    }

    private function getEc(StructureEc $ec): array
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
            $description =  $ec->elementConstitutif->getTexteEcLibre();
            $ecLibre = true;
        } else {
            $libelle = $ec->elementConstitutif->getFicheMatiere()?->getLibelle() ?? $ec->elementConstitutif->getLibelle() ?? '-';
            $description =  $ec->elementConstitutif->getFicheMatiere()?->getDescription() ?? '-';
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
            'numero'=> $ec->elementConstitutif->getCode(),
            'libelle'=> $libelle,
            'libelle_anglais' => $ec->elementConstitutif->getFicheMatiere()?->getLibelleAnglais() ?? '-',
            'sigle'=> $ec->elementConstitutif->getFicheMatiere()?->getSigle() ?? '-',
            'enseignant_referent' => [
                'nom'=> $ec->elementConstitutif->getFicheMatiere()?->getResponsableFicheMatiere()?->getDisplay() ?? '-',
                'email'=> $ec->elementConstitutif->getFicheMatiere()?->getResponsableFicheMatiere()?->getEmail() ?? '-'
            ],
            'description' => CleanTexte::cleanTextArea($description, false) ?? '-',
            'objectifs' => CleanTexte::cleanTextArea($ec->elementConstitutif->getFicheMatiere()?->getObjectifs(), false) ?? '-',
            'modalite_enseignement'=> $ec->elementConstitutif->getFicheMatiere()?->getModaliteEnseignement()->value ?? '-',
            'langues_supports' => $ec->elementConstitutif->getFicheMatiere()?->getLanguesSupportsArray() ?? [],
            'langues_dispense_cours' => $ec->elementConstitutif->getFicheMatiere()?->getLanguesDispenseArray() ?? [],
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
