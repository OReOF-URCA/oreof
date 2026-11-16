<?php

namespace App\Controller\Offre;

use App\Controller\BaseController;
use App\Entity\Composante;
use App\Entity\DpeFormation;
use App\Entity\Formation;
use App\Repository\AnneeRepository;
use App\Repository\ComposanteRepository;
use App\Repository\DpeParcoursRepository;
use App\Repository\FormationRepository;
use App\Repository\PlateformeAdmissionParametreRepository;
use App\Repository\PlateformeAdmissionRepository;
use App\Repository\TypeDiplomePlateformeAdmissionRepository;
use App\Repository\TypeDiplomeRepository;
use App\Service\Validation\OffreValidationService;
use Doctrine\ORM\EntityManagerInterface;
use App\Navigation\Breadcrumb\Attribute\Breadcrumb as BreadcrumbAttribute;
use App\Navigation\Breadcrumb\Breadcrumb as BreadcrumbService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Workflow\WorkflowInterface;

final class OffreEtablissementController extends BaseController
{
    use OffreAccessTrait;

    #[Route('/offre/etablissement', name: 'app_offre_etablissement_index')]
    #[Route('/offre/etablissement/composante/{composante}', name: 'app_offre_etablissement_composante_index')]
    #[BreadcrumbAttribute(menuKey: 'pilotage.gestion_offre_etablissement')]
    public function index(
        Request                                  $request,
        FormationRepository                      $formationRepository,
        DpeParcoursRepository                    $dpeParcoursRepository,
        ComposanteRepository                     $composanteRepository,
        TypeDiplomeRepository                    $typeDiplomeRepository,
        PlateformeAdmissionRepository            $plateformeAdmissionRepository,
        PlateformeAdmissionParametreRepository   $plateformeParamRepository,
        TypeDiplomePlateformeAdmissionRepository $typeDiplomePlateformeAdmissionRepository,
        AnneeRepository                          $anneeRepository,
        OffreValidationService                   $offreValidationService,
        EntityManagerInterface                   $em,
        WorkflowInterface                        $dpeFormationWorkflow,
        ?Composante                              $composante = null,
        ?BreadcrumbService                       $breadcrumb = null,
    ): Response
    {
        if ($composante !== null && $breadcrumb !== null) {
            $breadcrumb->add($composante->getLibelle() ?? 'Composante');
        }
        if ($composante !== null) {
            if (!$this->isGranted('ROLE_ADMIN')) {
                $this->denyAccessUnlessGranted('MANAGE', [
                    'route' => 'app_composante',
                    'subject' => $composante,
                ]);
            }
        } else {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');
        }

        $tabStatistiques = [
            'nbFormations' => 0,
            'nbParcours' => 0,
            'nbParcoursOuvert' => 0,
            'nbParcoursNonOuvert' => 0,
            'nbNonOuverture' => 0,
            'nbFermetureDefinitive' => 0,
            'capacite' => 0,
            'capacitePlateformes' => 0,
            'nbAConfirmer' => 0,
            'nbAnomalies' => 0,
            'allAnomalies' => [],
        ];

        $campagne = $this->getCampagneCollecte();

        // 1. Récupérer les formations d'établissement (non classiques)
        $formations = $formationRepository->findByCampagneWithRelations($campagne, $composante, false);

        $tFormations = [];
        foreach ($formations as $formation) {
            $idFormation = $formation->getId();
            $tFormations[$idFormation] = [
                'formation' => $formation,
                'dpeParcours' => [],
                'parcoursData' => [],
                'anomalies' => [],
                'parcoursAnomalies' => [],
                'capacite' => 0,
                'capacitePlateformes' => 0,
                'isOuverte' => false,
                'plateformesStatuts' => [],
                'portails' => [],
            ];
        }

        // Récupérer les DpeParcours d'établissement de la campagne
        $allParcours = $dpeParcoursRepository->findByCampagneCollecte($campagne, $composante, false);

        // 2. Batch loading des années
        $anneesByParcours = $anneeRepository->findByCampagneIndexedByParcours($campagne, $composante);

        // 3. Batch loading des paramètres plateformes
        $paramsByAnnee = $plateformeParamRepository->findByCampagneIndexedByAnnee($campagne, $composante);
        $tpaByTypeDiplome = $typeDiplomePlateformeAdmissionRepository->findByCampagneIndexedByTypeDiplome($campagne);

        foreach ($allParcours as $dpePar) {
            $parcours = $dpePar->getParcours();
            if ($parcours === null) {
                continue;
            }
            $formation = $parcours->getFormation();
            $idFormation = $formation?->getId();
            if ($idFormation === null) {
                continue;
            }
            if (!isset($tFormations[$idFormation])) {
                $tFormations[$idFormation] = [
                    'formation' => $formation,
                    'dpeParcours' => [],
                    'parcoursData' => [],
                    'anomalies' => [],
                    'parcoursAnomalies' => [],
                    'capacite' => 0,
                    'capacitePlateformes' => 0,
                    'isOuverte' => false,
                    'plateformesStatuts' => [],
                    'portails' => [],
                ];
            }

            $isParcoursOuvert = $dpePar->isOuvert();
            if ($isParcoursOuvert) {
                $tFormations[$idFormation]['isOuverte'] = true;
            }

            $parcoursAnnees = $anneesByParcours[$parcours->getId()] ?? [];

            // Anomalies
            $parcAnoms = $offreValidationService->getAnomaliesParcours($parcours, $campagne, $dpePar, $paramsByAnnee, $parcoursAnnees, $tpaByTypeDiplome);
            if (count($parcAnoms) > 0) {
                $tFormations[$idFormation]['parcoursAnomalies'][$parcours->getId()] = $parcAnoms;
                $tFormations[$idFormation]['anomalies'] = array_merge($tFormations[$idFormation]['anomalies'], $parcAnoms);
            }

            // Capacités parcours
            $parcoursCapacite = 0;
            $parcoursCapacitePlateformes = 0;
            foreach ($parcoursAnnees as $annee) {
                $isAnneeOuverte = $annee->isOuvert();
                if ($isAnneeOuverte) {
                    $parcoursCapacite += $annee->getCapaciteAccueil();
                }

                $portail = $annee->getPortail();
                if ($portail !== null && $portail->getId() !== null) {
                    $portailId = $portail->getId();
                    if (!isset($tFormations[$idFormation]['portails'][$portailId])) {
                        $tFormations[$idFormation]['portails'][$portailId] = [
                            'id' => $portailId,
                            'portail' => $portail,
                            'libelle' => $portail->getLibelle(),
                            'annees' => [],
                            'parcoursIds' => [],
                        ];
                    }
                    $anneeOrdre = $annee->getOrdre();
                    if ($anneeOrdre !== null && !in_array($anneeOrdre, $tFormations[$idFormation]['portails'][$portailId]['annees'], true)) {
                        $tFormations[$idFormation]['portails'][$portailId]['annees'][] = $anneeOrdre;
                        sort($tFormations[$idFormation]['portails'][$portailId]['annees']);
                    }
                    if (!in_array($parcours->getId(), $tFormations[$idFormation]['portails'][$portailId]['parcoursIds'], true)) {
                        $tFormations[$idFormation]['portails'][$portailId]['parcoursIds'][] = $parcours->getId();
                    }
                }

                $anneeParams = $paramsByAnnee[$annee->getId()] ?? [];
                foreach ($anneeParams as $param) {
                    $plat = $param->getPlateforme();
                    if ($plat === null || !$plat->getActive()) {
                        continue;
                    }
                    $platId = $plat->getId();
                    if ($platId === null) {
                        continue;
                    }
                    if (!isset($tFormations[$idFormation]['plateformesStatuts'][$platId])) {
                        $tFormations[$idFormation]['plateformesStatuts'][$platId] = [
                            'plateforme' => $plat,
                            'active' => false,
                            'hasCapaciteInactive' => false,
                            'capaciteInactive' => 0,
                        ];
                    }

                    $pCap = $param->getCapaciteGlobale();
                    if ($pCap === null || $pCap <= 0) {
                        $fi = $param->getCapaciteFi() ?? 0;
                        $alt = $param->getCapaciteAlternance() ?? 0;
                        $spe = $param->getCapaciteSpecifique() ?? 0;
                        $pCap = $fi + $alt + $spe;
                    }
                    $pCap = max(0, (int)$pCap);

                    if ($param->isActive()) {
                        $tFormations[$idFormation]['plateformesStatuts'][$platId]['active'] = true;
                        if ($isAnneeOuverte) {
                            $parcoursCapacitePlateformes += $pCap;
                        }
                    } elseif ($pCap > 0) {
                        $tFormations[$idFormation]['plateformesStatuts'][$platId]['hasCapaciteInactive'] = true;
                        $tFormations[$idFormation]['plateformesStatuts'][$platId]['capaciteInactive'] += $pCap;
                    }
                }
            }

            $compInscLibelle = $parcours->getComposanteInscription()?->getLibelle()
                ?? $formation->getComposantePorteuse()?->getLibelle()
                ?? '';

            $tFormations[$idFormation]['dpeParcours'][] = $dpePar;
            $tFormations[$idFormation]['parcoursData'][$parcours->getId()] = [
                'parcours' => $parcours,
                'dpeParcours' => $dpePar,
                'isOuvert' => $isParcoursOuvert,
                'capacite' => $parcoursCapacite,
                'capacitePlateformes' => $parcoursCapacitePlateformes,
                'annees' => $parcoursAnnees,
                'composanteLibelle' => $compInscLibelle,
            ];
        }

        // Batch loading des DpeFormation
        $dpeFormationsQb = $em->getRepository(DpeFormation::class)->createQueryBuilder('df')
            ->join('df.formation', 'f')
            ->addSelect('f')
            ->where('df.campagneCollecte = :campagne')
            ->setParameter('campagne', $campagne);
        if ($composante !== null) {
            $dpeFormationsQb->andWhere('f.composantePorteuse = :composante')
                ->setParameter('composante', $composante);
        }
        /** @var list<DpeFormation> $dpeFormations */
        $dpeFormations = $dpeFormationsQb->getQuery()->getResult();
        $dpeFormationMap = [];
        foreach ($dpeFormations as $df) {
            if ($df->getFormation() !== null) {
                $dpeFormationMap[$df->getFormation()->getId()] = $df;
            }
        }

        // Calcul capacité de chaque formation
        foreach ($tFormations as $fId => &$row) {
            $tcAnneesCounted = [];
            $portailsCounted = [];
            $tcPlateformesCounted = [];
            $portailPlateformesCounted = [];
            $formationCapacite = 0;
            $formationCapacitePlateformes = 0;
            $formation = $row['formation'];
            foreach ($row['parcoursData'] as $pd) {
                if (!$pd['isOuvert']) {
                    continue;
                }
                $parcours = $pd['parcours'];
                foreach ($pd['annees'] as $annee) {
                    if (!$annee->isOuvert()) {
                        continue;
                    }
                    $ordre = $annee->getOrdre();
                    if ($ordre === null) {
                        continue;
                    }
                    $portail = $annee->getPortail();
                    if ($portail !== null && $portail->getId() !== null) {
                        if (!isset($portailsCounted[$portail->getId()])) {
                            $formationCapacite += $annee->getCapaciteAccueil();
                            $portailsCounted[$portail->getId()] = true;
                        }
                    } else {
                        $tc = $formation->getTroncCommunForParcours($parcours, $ordre);
                        if ($tc !== null) {
                            $tcKey = $tc['id'] . '_' . $ordre;
                            if (!isset($tcAnneesCounted[$tcKey])) {
                                $formationCapacite += $annee->getCapaciteAccueil();
                                $tcAnneesCounted[$tcKey] = true;
                            }
                        } elseif ($formation->isAnneeTroncCommun($ordre, $parcours)) {
                            if (!isset($tcAnneesCounted[$ordre])) {
                                $formationCapacite += $annee->getCapaciteAccueil();
                                $tcAnneesCounted[$ordre] = true;
                            }
                        } else {
                            $formationCapacite += $annee->getCapaciteAccueil();
                        }
                    }

                    $tc = $formation->getTroncCommunForParcours($parcours, $ordre);
                    $tcKey = $tc !== null ? $tc['id'] . '_' . $ordre : ($formation->isAnneeTroncCommun($ordre, $parcours) ? (string)$ordre : null);
                    $anneeParams = $paramsByAnnee[$annee->getId()] ?? [];
                    foreach ($anneeParams as $param) {
                        $plat = $param->getPlateforme();
                        if ($plat === null || !$plat->getActive() || !$param->isActive()) {
                            continue;
                        }
                        $platId = $plat->getId();
                        if ($portail !== null && $portail->getId() !== null) {
                            $portailPlatKey = $portail->getId() . '_' . $ordre . '_' . $platId;
                            if (isset($portailPlateformesCounted[$portailPlatKey])) {
                                continue;
                            }
                            $portailPlateformesCounted[$portailPlatKey] = true;
                        } elseif ($tcKey !== null) {
                            $tcPlatKey = $tcKey . '_' . $platId;
                            if (isset($tcPlateformesCounted[$tcPlatKey])) {
                                continue;
                            }
                            $tcPlateformesCounted[$tcPlatKey] = true;
                        }

                        $pCap = $param->getCapaciteGlobale();
                        if ($pCap === null || $pCap <= 0) {
                            $fi = $param->getCapaciteFi() ?? 0;
                            $alt = $param->getCapaciteAlternance() ?? 0;
                            $spe = $param->getCapaciteSpecifique() ?? 0;
                            $pCap = $fi + $alt + $spe;
                        }
                        $formationCapacitePlateformes += max(0, (int)$pCap);
                    }
                }
            }
            $row['capacite'] = $formationCapacite;
            $row['capacitePlateformes'] = $formationCapacitePlateformes;

            $dpeF = $dpeFormationMap[$fId] ?? null;
            $row['dpeFormation'] = $dpeF;
            $row['etatValidation'] = $dpeF?->getEtatValidation() ?? ['brouillon' => 1];

            if (!empty($row['plateformesStatuts'])) {
                uasort($row['plateformesStatuts'], static fn($a, $b) => strcmp($a['plateforme']->getLibelle() ?? '', $b['plateforme']->getLibelle() ?? ''));
            }
        }
        unset($row);

        // Types de diplôme
        $typeDiplomeMap = [];
        foreach ($tFormations as $row) {
            $typeDipl = $row['formation']->getTypeDiplome();
            if ($typeDipl) {
                $typeDiplomeMap[$typeDipl->getId()] = $typeDipl;
            }
        }

        if ($composante !== null) {
            $typesDiplomeList = array_values($typeDiplomeMap);
            usort($typesDiplomeList, static fn($a, $b) => strcmp($a->getLibelle() ?? '', $b->getLibelle() ?? ''));
            $composantesList = [$composante];
        } else {
            $typesDiplomeList = $typeDiplomeRepository->findBy([], ['libelle' => 'ASC']);
            $composantesList = $composanteRepository->findBy([], ['libelle' => 'ASC']);
        }

        // Filtres (session + query)
        $session = $request->getSession();
        $campagneId = $campagne->getId() ?? 0;
        $sessionKey = sprintf('offre_etab_filters_%d_%s', $campagneId, $composante !== null ? 'comp_' . $composante->getId() : 'all');

        if ($request->query->getBoolean('reset')) {
            $session->remove($sessionKey);
            $q = '';
            $comp = $composante !== null ? (string)$composante->getId() : '';
            $type = '';
            $plateforme = '';
            $statut = '';
        } elseif ($request->query->has('q') || $request->query->has('comp') || $request->query->has('type') || $request->query->has('plateforme') || $request->query->has('statut')) {
            $q = trim((string)$request->query->get('q', ''));
            $comp = $composante !== null ? (string)$composante->getId() : (string)$request->query->get('comp', '');
            $type = (string)$request->query->get('type', '');
            $plateforme = (string)$request->query->get('plateforme', '');
            $statut = (string)$request->query->get('statut', '');

            $hasActiveFilters = $q !== ''
                || ($composante === null && $comp !== '')
                || $type !== ''
                || $plateforme !== ''
                || $statut !== '';

            if ($hasActiveFilters) {
                $session->set($sessionKey, [
                    'q' => $q,
                    'comp' => $comp,
                    'type' => $type,
                    'plateforme' => $plateforme,
                    'statut' => $statut,
                ]);
            } else {
                $session->remove($sessionKey);
            }
        } else {
            /** @var array{q?: string, comp?: string, type?: string, plateforme?: string, statut?: string} $savedFilters */
            $savedFilters = $session->get($sessionKey, []);
            $q = trim((string)($savedFilters['q'] ?? ''));
            $comp = $composante !== null ? (string)$composante->getId() : (string)($savedFilters['comp'] ?? '');
            $type = (string)($savedFilters['type'] ?? '');
            $plateforme = (string)($savedFilters['plateforme'] ?? '');
            $statut = (string)($savedFilters['statut'] ?? '');
        }

        // Application des filtres
        if ($q || $comp || $type || $plateforme || $statut) {
            $tFormations = array_filter($tFormations, function (array $row) use ($q, $comp, $type, $plateforme, $statut): bool {
                $f = $row['formation'];

                if ($q !== '') {
                    $lib = (string)($f->getDisplayLong() ?? '');
                    $match = (mb_stripos($lib, $q) !== false);
                    if (!$match) {
                        foreach ($row['dpeParcours'] as $dpePar) {
                            if (mb_stripos($dpePar->getParcours()?->getLibelle() ?? '', $q) !== false) {
                                $match = true;
                                break;
                            }
                        }
                    }
                    if (!$match) {
                        return false;
                    }
                }

                if ($comp !== '') {
                    if ($f->getComposantePorteuse()?->getId() !== (int)$comp) {
                        return false;
                    }
                }

                if ($type !== '') {
                    if ($f->getTypeDiplome()?->getId() !== (int)$type) {
                        return false;
                    }
                }

                if ($plateforme !== '') {
                    $platId = (int)$plateforme;
                    $pStatut = $row['plateformesStatuts'][$platId] ?? null;
                    if ($pStatut === null || (!$pStatut['active'] && !$pStatut['hasCapaciteInactive'])) {
                        return false;
                    }
                }

                if ($statut !== '') {
                    $match = $statut === 'Anomalie'
                        ? count($row['anomalies']) > 0
                        : array_key_first($row['etatValidation']) === $statut;
                    if (!$match) {
                        return false;
                    }
                }

                return true;
            });
        }

        // Statistiques et regroupement par composante
        $allAnomalies = [];
        $nbAConfirmer = 0;
        $nbParcoursOuvert = 0;
        $nbParcoursNonOuvert = 0;
        $nbNonOuverture = 0;
        $nbFermetureDefinitive = 0;
        $capacite = 0;
        $capacitePlateformes = 0;
        $groupedFormations = [];
        $compPortailsCounted = [];
        $globalPortailsCounted = [];
        $compPlatPortailsCounted = [];
        $globalPlatPortailsCounted = [];

        foreach ($tFormations as $row) {
            $tabStatistiques['nbFormations']++;
            $tabStatistiques['nbParcours'] += count($row['dpeParcours']);
            $allAnomalies = array_merge($allAnomalies, $row['anomalies']);

            foreach ($row['dpeParcours'] as $dpePar) {
                $isOuvert = $dpePar->isOuvert();
                if ($isOuvert) {
                    $nbParcoursOuvert++;
                    $state = array_key_first($dpePar->getEtatValidation()) ?? 'initialisation_dpe';
                    if (!in_array($state, ['publie', 'valide_a_publier'], true)) {
                        $nbAConfirmer++;
                    }
                } else {
                    $nbParcoursNonOuvert++;
                    $etatRecond = $dpePar->getEtatReconduction();
                    if ($etatRecond === \App\Enums\TypeModificationDpeEnum::FERMETURE_DEFINITIVE) {
                        $nbFermetureDefinitive++;
                    } elseif ($etatRecond === \App\Enums\TypeModificationDpeEnum::NON_OUVERTURE_CFVU || $etatRecond === \App\Enums\TypeModificationDpeEnum::NON_OUVERTURE_SES || $etatRecond === \App\Enums\TypeModificationDpeEnum::NON_OUVERTURE) {
                        $nbNonOuverture++;
                    }
                }
            }

            $composanteObj = $row['formation']->getComposantePorteuse();
            $compLibelle = $composanteObj?->getLibelle() ?? 'Sans composante';
            if (!isset($groupedFormations[$compLibelle])) {
                $groupedFormations[$compLibelle] = [
                    'composante' => $composanteObj,
                    'libelle' => $compLibelle,
                    'nbFormations' => 0,
                    'nbParcours' => 0,
                    'nbParcoursOuvert' => 0,
                    'capacite' => 0,
                    'capacitePlateformes' => 0,
                    'formations' => [],
                ];
            }
            $groupedFormations[$compLibelle]['nbFormations']++;
            $groupedFormations[$compLibelle]['nbParcours'] += count($row['dpeParcours']);

            $formationObj = $row['formation'];
            $fTcAnneesCounted = [];
            $fTcPlatCounted = [];
            $compCapToAdd = 0;
            $globalCapToAdd = 0;
            $compPlatCapToAdd = 0;
            $globalPlatCapToAdd = 0;

            foreach ($row['parcoursData'] as $pd) {
                if ($pd['isOuvert']) {
                    $groupedFormations[$compLibelle]['nbParcoursOuvert']++;
                } else {
                    continue;
                }
                $parcours = $pd['parcours'];
                foreach ($pd['annees'] as $annee) {
                    if (!$annee->isOuvert()) {
                        continue;
                    }
                    $ordre = $annee->getOrdre();
                    if ($ordre === null) {
                        continue;
                    }

                    $anneeCap = $annee->getCapaciteAccueil();
                    $portail = $annee->getPortail();
                    $portailKey = ($portail !== null && $portail->getId() !== null) ? (string)$portail->getId() . '_' . $ordre : null;

                    $tc = $formationObj->getTroncCommunForParcours($parcours, $ordre);
                    $tcKey = $tc !== null ? $tc['id'] . '_' . $ordre : ($formationObj->isAnneeTroncCommun($ordre, $parcours) ? (string)$ordre : null);

                    $isTcDuplicate = false;
                    if ($tcKey !== null) {
                        if (isset($fTcAnneesCounted[$tcKey])) {
                            $isTcDuplicate = true;
                        } else {
                            $fTcAnneesCounted[$tcKey] = true;
                        }
                    }
                    if (!$isTcDuplicate) {
                        if ($portailKey !== null) {
                            if (!isset($compPortailsCounted[$compLibelle][$portailKey])) {
                                $compCapToAdd += $anneeCap;
                                $compPortailsCounted[$compLibelle][$portailKey] = true;
                            }
                        } else {
                            $compCapToAdd += $anneeCap;
                        }

                        if ($portailKey !== null) {
                            if (!isset($globalPortailsCounted[$portailKey])) {
                                $globalCapToAdd += $anneeCap;
                                $globalPortailsCounted[$portailKey] = true;
                            }
                        } else {
                            $globalCapToAdd += $anneeCap;
                        }
                    }

                    $anneeParams = $paramsByAnnee[$annee->getId()] ?? [];
                    foreach ($anneeParams as $param) {
                        $plat = $param->getPlateforme();
                        if ($plat === null || !$plat->getActive() || !$param->isActive()) {
                            continue;
                        }
                        $platId = $plat->getId();
                        $isTcPlatDuplicate = false;
                        if ($tcKey !== null) {
                            $tcPlatKey = $tcKey . '_' . $platId;
                            if (isset($fTcPlatCounted[$tcPlatKey])) {
                                $isTcPlatDuplicate = true;
                            } else {
                                $fTcPlatCounted[$tcPlatKey] = true;
                            }
                        }
                        if ($isTcPlatDuplicate) {
                            continue;
                        }

                        $pCap = $param->getCapaciteGlobale();
                        if ($pCap === null || $pCap <= 0) {
                            $fi = $param->getCapaciteFi() ?? 0;
                            $alt = $param->getCapaciteAlternance() ?? 0;
                            $spe = $param->getCapaciteSpecifique() ?? 0;
                            $pCap = $fi + $alt + $spe;
                        }
                        $pCap = max(0, (int)$pCap);

                        $portailPlatKey = $portailKey !== null ? $portailKey . '_' . $platId : null;
                        if ($portailPlatKey !== null) {
                            if (!isset($compPlatPortailsCounted[$compLibelle][$portailPlatKey])) {
                                $compPlatCapToAdd += $pCap;
                                $compPlatPortailsCounted[$compLibelle][$portailPlatKey] = true;
                            }
                            if (!isset($globalPlatPortailsCounted[$portailPlatKey])) {
                                $globalPlatCapToAdd += $pCap;
                                $globalPlatPortailsCounted[$portailPlatKey] = true;
                            }
                        } else {
                            $compPlatCapToAdd += $pCap;
                            $globalPlatCapToAdd += $pCap;
                        }
                    }
                }
            }

            $groupedFormations[$compLibelle]['capacite'] += $compCapToAdd;
            $groupedFormations[$compLibelle]['capacitePlateformes'] += $compPlatCapToAdd;
            $capacite += $globalCapToAdd;
            $capacitePlateformes += $globalPlatCapToAdd;

            $groupedFormations[$compLibelle]['formations'][] = $row;
        }

        $collator = class_exists(\Collator::class) ? new \Collator('fr_FR') : null;
        $compareStr = static function (string $a, string $b) use ($collator): int {
            if ($collator !== null) {
                $res = $collator->compare($a, $b);
                return $res !== false ? $res : strcasecmp($a, $b);
            }
            return strcasecmp($a, $b);
        };

        uksort($groupedFormations, static fn(string $a, string $b) => $compareStr($a, $b));

        $isSesOrAdmin = $this->isGranted('ROLE_SES') || $this->isGranted('ROLE_ADMIN');

        foreach ($groupedFormations as &$compGroup) {
            usort($compGroup['formations'], static function (array $a, array $b) use ($compareStr): int {
                $libA = (string)($a['formation']->getDisplay() ?? $a['formation']->getDisplayLong() ?? '');
                $libB = (string)($b['formation']->getDisplay() ?? $b['formation']->getDisplayLong() ?? '');
                return $compareStr($libA, $libB);
            });

            foreach ($compGroup['formations'] as &$formaRow) {
                uasort($formaRow['parcoursData'], static function (array $a, array $b) use ($compareStr): int {
                    $libA = (string)$a['parcours']->getLibelle();
                    $libB = (string)$b['parcours']->getLibelle();
                    return $compareStr($libA, $libB);
                });
            }
            unset($formaRow);

            $enabledTransitionsMap = [];

            foreach ($compGroup['formations'] as $formaRow) {
                $fId = $formaRow['formation']->getId();
                $dpeF = $dpeFormationMap[$fId] ?? null;
                if ($dpeF === null) {
                    $dpeF = new DpeFormation();
                    $dpeF->setFormation($formaRow['formation']);
                    $dpeF->setCampagneCollecte($campagne);
                    $dpeF->setEtatValidation(['brouillon' => 1]);
                }

                $fActiveState = array_key_first($dpeF->getEtatValidation()) ?? 'brouillon';

                foreach ($dpeFormationWorkflow->getEnabledTransitions($dpeF) as $trans) {
                    $tName = $trans->getName();
                    // Modifications à chaque instant : transmission toujours active
                    $canTrigger = $isSesOrAdmin || ($tName === 'transmettre_etablissement');
                    if ($canTrigger && !isset($enabledTransitionsMap[$tName])) {
                        $enabledTransitionsMap[$tName] = [
                            'name' => $tName,
                            'metadata' => $dpeFormationWorkflow->getMetadataStore()->getTransitionMetadata($trans),
                            'etape' => $fActiveState,
                        ];
                    }
                }
            }

            $compGroup['transitions'] = array_values($enabledTransitionsMap);
        }
        unset($compGroup);

        $tabStatistiques['nbParcoursOuvert'] = $nbParcoursOuvert;
        $tabStatistiques['nbParcoursNonOuvert'] = $nbParcoursNonOuvert;
        $tabStatistiques['nbNonOuverture'] = $nbNonOuverture;
        $tabStatistiques['nbFermetureDefinitive'] = $nbFermetureDefinitive;
        $tabStatistiques['capacite'] = $capacite;
        $tabStatistiques['capacitePlateformes'] = $capacitePlateformes;
        $tabStatistiques['nbAConfirmer'] = $nbAConfirmer;
        $tabStatistiques['nbAnomalies'] = count($allAnomalies);
        $tabStatistiques['allAnomalies'] = $allAnomalies;

        $params = [
            'composante' => $composante,
            'groupedFormations' => $groupedFormations,
            'tpaByTypeDiplome' => $tpaByTypeDiplome,
            'filters' => [
                'q' => $q,
                'comp' => $comp,
                'type' => $type,
                'plateforme' => $plateforme,
                'statut' => $statut,
            ],
            'tabStatistiques' => $tabStatistiques,
            'campagne' => $campagne,
            'composantes' => $composantesList,
            'typesDiplome' => $typesDiplomeList,
            'plateformes' => $plateformeAdmissionRepository->findBy([], ['libelle' => 'ASC']),
            'isEtablissement' => true,
        ];

        return $this->render('offre_v2/etablissement/index.html.twig', $params);
    }
}
