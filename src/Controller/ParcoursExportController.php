<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/FormationExportController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller;

use App\Classes\MyGotenbergPdf;
use App\DTO\StructureEc;
use App\DTO\StructureUe;
use App\Entity\Parcours;
use App\Entity\ParcoursVersioning;
use App\Service\ParcoursExport;
use App\Service\TypeDiplomeResolver;
use App\Service\VersioningParcours;
use App\TypeDiplome\Exceptions\TypeDiplomeNotFoundException;
use App\Utils\CleanTexte;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class ParcoursExportController extends AbstractController
{
    public function __construct(
        private readonly MyGotenbergPdf $myPdf
    ) {
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     * @throws TypeDiplomeNotFoundException
     * @throws Exception
     */
    #[Route('/parcours/{parcours}/export-pdf', name: 'app_parcours_export')]
    public function export(
        TypeDiplomeResolver $typeDiplomeResolver,
        Parcours                $parcours,
    ): Response {
        $typeDiplome = $parcours->getFormation()?->getTypeDiplome();

        if (null === $typeDiplome) {
            throw new Exception('Type de diplôme non trouvé');
        }

        $typeD = $typeDiplomeResolver->get($typeDiplome);


        return $this->myPdf->render('pdf/parcours.html.twig', [
            'formation' => $parcours->getFormation(),
            'typeDiplome' => $typeDiplome,
            'parcours' => $parcours,
            'hasParcours' => $parcours->getFormation()?->isHasParcours(),
            'titre' => 'Détails du parcours ' . $parcours->getDisplay(),
            'dto' => $typeD->calculStructureParcours($parcours),
        ], 'Parcours_' . $parcours->getDisplay());
    }

    #[Route('/parcours/{parcours}/versioning/export-pdf', name: 'app_parcours_export_pdf_versioning')]
    public function exportPdfVersioning(
        Parcours $parcours,
        EntityManagerInterface $entityManager,
        VersioningParcours $versioningParcours
    ){
        $lastCfvuVersion = $entityManager->getRepository(ParcoursVersioning::class)
            ->findLastCfvuVersion($parcours);
        $lastCfvuVersion = count($lastCfvuVersion) > 0 ? $lastCfvuVersion[0] : null;

        if($lastCfvuVersion){
            $versionData = $versioningParcours->loadParcoursFromVersion($lastCfvuVersion);
            $parcoursVersionData = $versionData['parcours'];
            $dtoVersionData = $versionData['dto'];

            return $this->myPdf->render('pdf/parcours.html.twig', [
                'formation' => $parcoursVersionData->getFormation(),
                'typeDiplome' => $parcoursVersionData->getTypeDiplome(),
                'parcours' => $parcoursVersionData,
                'hasParcours' => $parcoursVersionData->getFormation()->isHasParcours(),
                'titre' => 'Détails du parcours ' . $parcoursVersionData->getLibelle(),
                'dto' => $dtoVersionData,
                'isVersioning' => true
            ], 'Parcours_' . $parcours->getLibelle());
        }
    }

    #[Route('/parcours/{parcours}/maquette/validee_cfvu/export-json', name: 'app_parcours_export_maquette_json_validee_cfvu')]
    public function exportMaquetteValideeJson(
        Parcours $parcours,
        ParcoursExport $parcoursExport,
        VersioningParcours $versioningParcours,
        EntityManagerInterface $entityManager
    ) : Response {
        $lastCfvuVersion = $entityManager
            ->getRepository(ParcoursVersioning::class)
            ->findLastCfvuVersion($parcours);

        if(count($lastCfvuVersion) === 0){
            return $this->json(["error" => "no valid version available"]);
        }

        $versionData = $versioningParcours->loadParcoursFromVersion($lastCfvuVersion[0]);
        $parcours_id = $lastCfvuVersion[0]->getParcours()->getId();
        $formation_id = $lastCfvuVersion[0]->getParcours()->getFormation()->getId();

        $json = $parcoursExport->exportLastValidVersionMaquetteJson(
            $versionData['dto'],
            $versionData['parcours'],
            $parcours_id,
            $formation_id
        );

        return $this->json($json);
    }

    #[Route('/parcours/{parcours}/maquette-minimum/export-json', name: 'app_parcours_export_maquette_json_minimum')]
    public function exportMaquetteJsonMinimum(Parcours $parcours) : Response {
        $volumesVide = ['presentiel' => 0, 'distanciel' => 0];

        $jsonData = [
            'path' => $this->generateUrl(
                'app_parcours_export_maquette_json_minimum', 
                ['parcours' => $parcours->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'id' => $parcours->getId(),
            'formationId' => $parcours->getFormation()?->getId(),
            'formation' => $parcours->getFormation()?->getDisplay() ?? '',
            'parcours' => $parcours->isParcoursDefaut() ? '' : $parcours->getLibelle() ?? '',
            'typeDiplome' => $parcours->getFormation()?->getTypeDiplome()?->getLibelle() ?? '',
            'composante' => $parcours->getFormation()?->getComposantePorteuse()?->getLibelle() ?? '',
            'volumes' => [
                'CM' => $volumesVide,
                'TD' => $volumesVide,
                'TP' => $volumesVide,
                'autonomie' => 0
            ],
            'ects' => 0,
            'semestres' => []
        ];

        return $this->json($jsonData);
    }

    #[Route('/parcours/{parcours}/maquette/export-json', name: 'app_parcours_export_maquette_json')]
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
            'path' => $this->generateUrl('app_parcours_export_maquette_json', ['parcours' => $parcours->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
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
            'semestres' => []
        ];

        foreach ($dto->semestres as $ordre => $sem) {
            if ($sem->semestre->isNonDispense() === false && $sem->semestreParcours->isOuvert() === true) {
                $semestre = [
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
                    'ues' => []
                ];
                foreach ($sem->ues as $ue) {
                    $tUe = [
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
                        $tUe['UesEnfants'] = [];
                        $nb = 0;
                        foreach ($ue->uesEnfants() as $ueEnfant) {
                            $tUeEnfant = [
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
                            $tUeEnfant['ec'] = $this->getEcFromUe($ueEnfant);

                            $nbChoixDeuxiemeNiveau = 0;
                            if(count($ueEnfant->uesEnfants()) > 0){
                                $tUeEnfant['UesEnfants'] = [];
                            }
                            foreach($ueEnfant->uesEnfants() as $ueEnfantDeuxieme){
                                $tUeEnfantDeuxieme = [
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
                                $tUeEnfantDeuxieme['ec'] = $this->getEcFromUe($ueEnfantDeuxieme);
                                $tUeEnfant['UesEnfants'][] = $tUeEnfantDeuxieme;
                            }

                            $tUe['UesEnfants'][] = $tUeEnfant;
                        }
                    } else {
                        $tUe['ects'] = $ue->heuresEctsUe->sommeUeEcts;
                        $tUe['ec'] = $this->getEcFromUe($ue);
                    }
                    $semestre['ues'][] = $tUe;
                }

                $data['semestres'][] = $semestre;
            }
        }

        return $this->json($data);
    }

    private function getEcFromUe(StructureUe $ue): array
    {
        $tEcs = [];
        foreach ($ue->elementConstitutifs as $ec) {
//            if ($ec->elementConstitutif->getNatureUeEc()?->isLibre()) {
//                $tEc['ordre'] = $ec->elementConstitutif->getOrdre();
//                $tEc['numero'] = $ec->elementConstitutif->getCode();
//                $tEc['libelle'] = $ec->elementConstitutif?->getFicheMatiere()?->getLibelle() ?? '-';
//                $tEc['description_libre_choix'] =  $ec->elementConstitutif->getTexteEcLibre();
//                $tEc['ects'] = $ec->heuresEctsEc->ects;
//                $tEcs[] = $tEc;
            if ($ec->elementConstitutif->getNatureUeEc()?->isChoix()) {
                $tEc['ordre'] = $ec->elementConstitutif->getOrdre();
                $tEc['numero'] = $ec->elementConstitutif->getCode();
                $tEc['libelle'] = $ec->elementConstitutif?->getFicheMatiere()?->getLibelle() ?? '-';
                $tEc['ecsEnfants'] =  [];
                $tEc['description_libre_choix'] =  $ec->elementConstitutif->getTexteEcLibre();
                $nb = 0;
                foreach ($ec->elementsConstitutifsEnfants as $ecEnfant) {
                    $tEc['ecsEnfants'][] = $this->getEc($ecEnfant);
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
            'ordre' => $ec->elementConstitutif->getOrdre(),
            'valide' => $valide,
            'ec_libre' => $ecLibre,
            'nature_ec' => $ec->elementConstitutif->getTypeEc()?->getLibelle(),
            'valide_date' => new DateTime(),
            'numero'=> $ec->elementConstitutif->getCode(),
            'libelle'=> $libelle,
            'libelle_anglais' => $ec->elementConstitutif->getFicheMatiere()?->getLibelleAnglais() ?? '-',
            'sigle'=> $ec->elementConstitutif->getFicheMatiere()?->getSigle() ?? '-', "",
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
