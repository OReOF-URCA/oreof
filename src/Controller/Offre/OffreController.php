<?php

namespace App\Controller\Offre;

use App\Controller\BaseController;
use App\Entity\Composante;
use App\Entity\Constantes;
use App\Entity\CampagneCollecte;
use App\Entity\DpeFormation;
use App\Entity\DpeParcours;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\PlateformeAdmissionParametre;
use App\Enums\TypeModificationDpeEnum;
use App\Enums\TypeParcoursEnum;
use App\Entity\DocumentConseil;
use App\Entity\HistoriqueFormation;
use App\Exception\FileUploadException;
use App\Repository\AnneeRepository;
use App\Repository\ComposanteRepository;
use App\Repository\DocumentConseilRepository;
use App\Repository\DpeFormationRepository;
use App\Repository\DpeParcoursRepository;
use App\Repository\FormationRepository;
use App\Repository\PlateformeAdmissionParametreRepository;
use App\Repository\PlateformeAdmissionRepository;
use App\Repository\TypeDiplomePlateformeAdmissionRepository;
use App\Repository\TypeDiplomeRepository;
use App\Service\CampagneCollecteService;
use App\Service\ParcoursComparaisonService;
use App\Service\SecureUploadService;
use App\Service\Validation\OffreValidationService;
use App\Utils\TurboStreamResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Workflow\WorkflowInterface;

final class OffreController extends BaseController
{
    #[Route('/offrev2', name: 'app_offre_index')]
    #[Route('/offre/composante/{composante}', name: 'app_offre_composante_index')]
    public function index(
        Request               $request,
        DpeParcoursRepository $dpeParcoursRepository,
        ComposanteRepository $composanteRepository,
        TypeDiplomeRepository $typeDiplomeRepository,
        PlateformeAdmissionRepository $plateformeAdmissionRepository,
        PlateformeAdmissionParametreRepository $plateformeParamRepository,
        TypeDiplomePlateformeAdmissionRepository $typeDiplomePlateformeAdmissionRepository,
        DocumentConseilRepository $documentConseilRepository,
        AnneeRepository       $anneeRepository,
        OffreValidationService $offreValidationService,
        EntityManagerInterface $em,
        WorkflowInterface     $dpeFormationWorkflow,
        ?Composante           $composante = null,
    ): Response
    {
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
            'capacite' => 0,
            'capaciteTotale' => 0,
            'nbAConfirmer' => 0,
            'nbAnomalies' => 0,
            'allAnomalies' => []
        ];

        $campagne = $this->getCampagneCollecte();

        // 1. Récupérer l'ensemble des formations et des parcours associés en 1 seule requête fetch-join (filtré par composante si renseignée)
        $allParcours = $dpeParcoursRepository->findByCampagneCollecte($campagne, $composante);

        // 2. Batch loading des années (1 requête pour tous les parcours au lieu de 400+)
        $anneesByParcours = $anneeRepository->findByCampagneIndexedByParcours($campagne);

        // 3. Batch loading des configurations et paramètres de plateformes (1 requête chacune au lieu de 1000+)
        $paramsByAnnee = $plateformeParamRepository->findByCampagneIndexedByAnnee($campagne);
        $tpaByTypeDiplome = $typeDiplomePlateformeAdmissionRepository->findByCampagneIndexedByTypeDiplome($campagne);

        $tFormations = [];
        foreach ($allParcours as $dpePar) {
            $parcours = $dpePar->getParcours();
            $formation = $parcours?->getFormation();
            $idFormation = $formation?->getId();
            if ($idFormation === null || $parcours === null) {
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
                    'isOuverte' => false,
                ];
            }

            $isParcoursOuvert = ($dpePar->getEtatReconduction() === TypeModificationDpeEnum::OUVERT);
            if ($isParcoursOuvert) {
                $tFormations[$idFormation]['isOuverte'] = true;
            }

            $parcoursAnnees = $anneesByParcours[$parcours->getId()] ?? [];

            // Calcul anomalies pour ce parcours en mémoire (0 requête)
            $parcAnoms = $offreValidationService->getAnomaliesParcours($parcours, $campagne, $dpePar, $paramsByAnnee, $parcoursAnnees);
            if (count($parcAnoms) > 0) {
                $tFormations[$idFormation]['parcoursAnomalies'][$parcours->getId()] = $parcAnoms;
                $tFormations[$idFormation]['anomalies'] = array_merge($tFormations[$idFormation]['anomalies'], $parcAnoms);
            }

            // Calcul capacité parcours depuis les années préchargées (0 requête)
            $parcoursCapacite = 0;
            foreach ($parcoursAnnees as $annee) {
                $parcoursCapacite += $annee->getCapaciteAccueil();
            }

            // Libellé composante inscription sans lazy-load (0 requête)
            $compInscLibelle = $parcours->getComposanteInscription()?->getLibelle()
                ?? $formation->getComposantePorteuse()?->getLibelle()
                ?? '';

            $tFormations[$idFormation]['dpeParcours'][] = $dpePar;
            $tFormations[$idFormation]['parcoursData'][$parcours->getId()] = [
                'parcours' => $parcours,
                'dpeParcours' => $dpePar,
                'isOuvert' => $isParcoursOuvert,
                'capacite' => $parcoursCapacite,
                'annees' => $parcoursAnnees,
                'composanteLibelle' => $compInscLibelle,
            ];
        }

        // 4. Batch loading des DpeFormation et DocumentConseil pour toutes les formations
        $formationIds = array_keys($tFormations);
        $docsByFormation = $documentConseilRepository->findIndexedByFormationIds($formationIds);

        $dpeFormations = $em->getRepository(DpeFormation::class)->findBy(['campagneCollecte' => $campagne]);
        $dpeFormationMap = [];
        foreach ($dpeFormations as $df) {
            if ($df->getFormation() !== null) {
                $dpeFormationMap[$df->getFormation()->getId()] = $df;
            }
        }

        // Calcul capacité de chaque formation et association des statuts documents
        foreach ($tFormations as $fId => &$row) {
            $formationCapacite = $row['formation']->getCapaciteAccueil();
            if ($formationCapacite <= 0) {
                $formationCapacite = array_sum(array_map(fn($p) => $p['capacite'], $row['parcoursData']));
            }
            $row['capacite'] = $formationCapacite;

            $docInfo = $docsByFormation[$fId] ?? ['hasPv' => false, 'pv' => null, 'hasNote' => false, 'note' => null];
            $row['hasPv'] = $docInfo['hasPv'];
            $row['pvDoc'] = $docInfo['pv'];
            $row['hasNote'] = $docInfo['hasNote'];
            $row['noteDoc'] = $docInfo['note'];

            $dpeF = $dpeFormationMap[$fId] ?? null;
            $row['dpeFormation'] = $dpeF;
            $row['etatValidation'] = $dpeF?->getEtatValidation() ?? ['brouillon' => 1];
        }
        unset($row);

        // Construire les listes de filtres disponibles à partir des données en mémoire
        $types = [];
        $composantes = [];
        $typeDiplomeMap = [];
        foreach ($tFormations as $row) {
            $tabStatistiques['capaciteTotale'] += $row['capacite'];
            $f = $row['formation'];
            $typeDipl = $f->getTypeDiplome();
            if ($typeDipl) {
                $typeLib = $typeDipl->getLibelle();
                if ($typeLib) {
                    $types[$typeLib] = true;
                }
                $typeDiplomeMap[$typeDipl->getId()] = $typeDipl;
            }
            $compLib = $f->getComposantePorteuse()?->getLibelle();
            if ($compLib) {
                $composantes[$compLib] = true;
            }
        }
        $types = array_values(array_filter(array_keys($types)));
        sort($types);
        $composantes = array_values(array_filter(array_keys($composantes)));
        sort($composantes);

        if ($composante !== null) {
            $typesDiplomeList = array_values($typeDiplomeMap);
            usort($typesDiplomeList, static fn($a, $b) => strcmp($a->getLibelle() ?? '', $b->getLibelle() ?? ''));
            $composantesList = [$composante];
        } else {
            $typesDiplomeList = $typeDiplomeRepository->findBy([], ['libelle' => 'ASC']);
            $composantesList = $composanteRepository->findBy([], ['libelle' => 'ASC']);
        }

        // Lire les filtres de la requête
        $q = trim((string)$request->query->get('q', ''));
        $comp = $composante !== null ? (string)$composante->getId() : (string)$request->query->get('comp', '');
        $type = (string)$request->query->get('type', '');
        $plateforme = (string)$request->query->get('plateforme', '');
        $statut = (string)$request->query->get('statut', '');

        // Appliquer les filtres côté PHP sur le tableau tFormations
        if ($q || $comp || $type || $plateforme || $statut) {
            $tFormations = array_filter($tFormations, function (array $row) use ($q, $comp, $type, $plateforme, $statut, $tpaByTypeDiplome): bool {
                $f = $row['formation'];
                
                // 1. Libellé
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
                
                // 2. Composante ID
                if ($comp !== '') {
                    if ($f->getComposantePorteuse()?->getId() !== (int)$comp) {
                        return false;
                    }
                }
                
                // 3. Type diplôme ID
                if ($type !== '') {
                    if ($f->getTypeDiplome()?->getId() !== (int)$type) {
                        return false;
                    }
                }
                
                // 4. Plateforme ID
                if ($plateforme !== '') {
                    $hasPlatform = false;
                    $typeDiplId = $f->getTypeDiplome()?->getId();
                    if ($typeDiplId && isset($tpaByTypeDiplome[$typeDiplId])) {
                        foreach ($tpaByTypeDiplome[$typeDiplId] as $tpa) {
                            if ($tpa->getPlateforme()?->getId() === (int)$plateforme && $tpa->getPlateforme()->getActive()) {
                                $hasPlatform = true;
                                break;
                            }
                        }
                    }
                    if (!$hasPlatform) {
                        return false;
                    }
                }
                
                // 5. Statut
                if ($statut !== '') {
                    $match = false;
                    if ($statut === 'Anomalie') {
                        $match = (count($row['anomalies']) > 0);
                    } else {
                        foreach ($row['dpeParcours'] as $dpePar) {
                            $state = array_key_first($dpePar->getEtatValidation()) ?? 'initialisation_dpe';
                            if ($statut === 'Brouillon') {
                                if (in_array($state, ['initialisation_dpe', 'autorisation_saisie', 'en_cours_redaction', 'tacite_reconduction', 'soumis_parcours'], true)) {
                                    $match = true;
                                    break;
                                }
                            } elseif ($statut === 'Validé composante') {
                                if (in_array($state, ['soumis_dpe_composante', 'soumis_conseil', 'soumis_central'], true)) {
                                    $match = true;
                                    break;
                                }
                            } elseif ($statut === 'Validé central') {
                                if (in_array($state, ['soumis_cfvu', 'valide_cfvu', 'valide_a_publier', 'publie'], true)) {
                                    $match = true;
                                    break;
                                }
                            }
                        }
                    }
                    if (!$match) {
                        return false;
                    }
                }

                return true;
            });
        }

        // Calculer les statistiques et agréger les anomalies et les groupes par composante
        $allAnomalies = [];
        $nbAConfirmer = 0;
        $nbParcoursOuvert = 0;
        $capacite = 0;
        $groupedFormations = [];

        foreach ($tFormations as $row) {
            $tabStatistiques['nbFormations']++;
            $tabStatistiques['nbParcours'] += count($row['dpeParcours']);
            
            $allAnomalies = array_merge($allAnomalies, $row['anomalies']);
            
            foreach ($row['dpeParcours'] as $dpePar) {
                $isOuvert = ($dpePar->getEtatReconduction() === TypeModificationDpeEnum::OUVERT);
                if ($isOuvert) {
                    $nbParcoursOuvert++;
                    
                    $state = array_key_first($dpePar->getEtatValidation()) ?? 'initialisation_dpe';
                    if (!in_array($state, ['valide_cfvu', 'valide_a_publier', 'publie'], true)) {
                        $nbAConfirmer++;
                    }
                }
            }

            $capacite += $row['capacite'];

            // Groupement pré-calculé par composante pour Twig
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
                    'nbPv' => 0,
                    'nbNote' => 0,
                    'formations' => [],
                ];
            }
            $groupedFormations[$compLibelle]['nbFormations']++;
            $groupedFormations[$compLibelle]['nbParcours'] += count($row['dpeParcours']);
            $groupedFormations[$compLibelle]['capacite'] += $row['capacite'];
            if ($row['hasPv']) {
                $groupedFormations[$compLibelle]['nbPv']++;
            }
            if ($row['hasNote']) {
                $groupedFormations[$compLibelle]['nbNote']++;
            }
            foreach ($row['parcoursData'] as $pd) {
                if ($pd['isOuvert']) {
                    $groupedFormations[$compLibelle]['nbParcoursOuvert']++;
                }
            }
            $groupedFormations[$compLibelle]['formations'][] = $row;
        }

        ksort($groupedFormations);

        $isSesOrAdmin = $this->isGranted('ROLE_SES') || $this->isGranted('ROLE_ADMIN');
        $isPeriodActive = $campagne && $campagne->isPeriodActive();

        foreach ($groupedFormations as &$compGroup) {
            $enabledTransitionsMap = [];
            $stateCounts = [];

            foreach ($compGroup['formations'] as $formaRow) {
                $dpeF = $formaRow['dpeFormation'];
                if ($dpeF === null) {
                    $dpeF = new DpeFormation();
                    $dpeF->setFormation($formaRow['formation']);
                    $dpeF->setCampagneCollecte($campagne);
                    $dpeF->setEtatValidation(['brouillon' => 1]);
                }

                $fActiveState = array_key_first($dpeF->getEtatValidation()) ?? 'brouillon';
                $stateCounts[$fActiveState] = ($stateCounts[$fActiveState] ?? 0) + 1;

                foreach ($dpeFormationWorkflow->getEnabledTransitions($dpeF) as $trans) {
                    $tName = $trans->getName();
                    $canTrigger = $isSesOrAdmin || ($tName === 'transmettre' && $isPeriodActive);
                    if ($canTrigger && !isset($enabledTransitionsMap[$tName])) {
                        $enabledTransitionsMap[$tName] = [
                            'name' => $tName,
                            'metadata' => $dpeFormationWorkflow->getMetadataStore()->getTransitionMetadata($trans),
                            'etape' => $fActiveState,
                        ];
                    }
                }
            }

            arsort($stateCounts);
            $compGroup['activeState'] = array_key_first($stateCounts) ?? 'brouillon';
            $compGroup['transitions'] = array_values($enabledTransitionsMap);
            $compGroup['can_edit'] = $isSesOrAdmin || $isPeriodActive;
        }
        unset($compGroup);

        $tabStatistiques['nbParcoursOuvert'] = $nbParcoursOuvert;
        $tabStatistiques['capacite'] = $capacite;
        $tabStatistiques['nbAConfirmer'] = $nbAConfirmer;
        $tabStatistiques['nbAnomalies'] = count($allAnomalies);
        $tabStatistiques['allAnomalies'] = $allAnomalies;

        // Déterminer si on ne doit renvoyer que le fragment du frame
        $frameId = $request->headers->get('Turbo-Frame');
        $isFrame = $frameId === 'offre_table';

        $params = [
            'composante' => $composante,
            'tFormations' => $tFormations,
            'groupedFormations' => $groupedFormations,
            'tpaByTypeDiplome' => $tpaByTypeDiplome,
            'filters' => [
                'q' => $q,
                'comp' => $comp,
                'type' => $type,
                'plateforme' => $plateforme,
                'statut' => $statut,
            ],
            'choices' => [
                'types' => $types,
                'composantes' => $composantes,
                'villes' => [],
            ],
            'tabStatistiques' => $tabStatistiques,
            'campagne' => $campagne,
            'composantes' => $composantesList,
            'typesDiplome' => $typesDiplomeList,
            'plateformes' => $plateformeAdmissionRepository->findBy([], ['libelle' => 'ASC']),
        ];

        return $this->render('offre_v2/index.html.twig', $params);
    }

    #[Route('/offre/{slug}/configurer', name: 'offre_v2_configurer')]
    public function configurer(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Formation                  $formation,
        ParcoursComparaisonService $comparaisonService,
        OffreValidationService     $offreValidationService,
        EntityManagerInterface     $em,
        \Symfony\Component\Workflow\WorkflowInterface $dpeFormationWorkflow,
    ): Response
    {
        $campagne = $this->getCampagneCollecte();

        $dpeFormation = $em->getRepository(\App\Entity\DpeFormation::class)->findOneBy([
            'formation' => $formation,
            'campagneCollecte' => $campagne,
        ]);

        if ($dpeFormation === null) {
            $dpeFormation = new \App\Entity\DpeFormation();
            $dpeFormation->setFormation($formation);
            $dpeFormation->setCampagneCollecte($campagne);
            $dpeFormation->setEtatValidation(['brouillon' => 1]);
            $em->persist($dpeFormation);
            $em->flush();
        }

        $statsData = $this->calculerStatistiques($formation, $campagne, $comparaisonService, $offreValidationService);

        // Récupérer le type de diplôme
        $typeDiplome = $formation->getTypeDiplome();
        $plateformes = [];

        if ($typeDiplome) {
            foreach ($typeDiplome->getTypeDiplomePlateformeAdmissions() as $tpa) {
                if ($tpa->getCampagne() === $campagne && $tpa->getPlateforme()?->getActive()) {
                    $plateforme = $tpa->getPlateforme();
                    $plateformes[] = [
                        'id' => $plateforme->getId(),
                        'libelle' => $plateforme->getLibelle(),
                        'code' => $plateforme->getCode(),
                        'color' => $plateforme->getColor(),
                        'definitionChamps' => $plateforme->getDefinitionChamps(),
                        'annees' => array_values($tpa->getAnnees()),
                    ];
                }
            }
        }

        $otherFormations = $em->getRepository(Formation::class)->findBy([
            'composantePorteuse' => $formation->getComposantePorteuse(),
        ]);

        $transitionsData = [];
        foreach ($dpeFormationWorkflow->getEnabledTransitions($dpeFormation) as $transition) {
            $transitionsData[] = [
                'name' => $transition->getName(),
                'metadata' => $dpeFormationWorkflow->getMetadataStore()->getTransitionMetadata($transition),
            ];
        }

        return $this->render('offre_v2/configurer.html.twig', [
            'formation' => $formation,
            'plateformes' => $plateformes,
            'campagne' => $campagne,
            'tabStatistiques' => $statsData['tabStatistiques'],
            'anomalies' => $statsData['anomalies'],
            'comparaison' => $statsData['comparaison'],
            'dpeFormation' => $dpeFormation,
            'transitions' => $transitionsData,
            'otherFormations' => $otherFormations,
        ]);
    }

    #[Route('/offre/{slug}/configurer/sauvegarder', name: 'offre_v2_sauvegarder', methods: ['POST'])]
    public function sauvegarder(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Formation                              $formation,
        Request                                $request,
        EntityManagerInterface                 $em,
        PlateformeAdmissionParametreRepository $plateformeParamRepo,
        ParcoursComparaisonService             $comparaisonService,
        OffreValidationService                 $offreValidationService,
    ): Response {
        $csrfToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('offre_v2_configurer_' . $formation->getId(), $csrfToken)) {
            return new JsonResponse(['success' => false, 'message' => 'Token CSRF invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $campagne = $this->getCampagneCollecte();

        $isSesOrAdmin = $this->isGranted('ROLE_SES') || $this->isGranted('ROLE_ADMIN');

        if (!$isSesOrAdmin) {
            if (!$campagne->isPeriodActive()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'La campagne de collecte est fermée. Modification impossible.'
                ], Response::HTTP_FORBIDDEN);
            }

            $dpeFormation = $em->getRepository(\App\Entity\DpeFormation::class)->findOneBy([
                'formation' => $formation,
                'campagneCollecte' => $campagne,
            ]);

            $isBrouillon = $dpeFormation === null || array_key_exists('brouillon', $dpeFormation->getEtatValidation());

            if (!$isBrouillon) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'L\'offre a déjà été transmise pour validation. Modification impossible.'
                ], Response::HTTP_FORBIDDEN);
            }
        }

        $changedYears = [];

        foreach ($formation->getParcours() as $parcours) {
            $parcoursKey = 'parcours_' . $parcours->getId() . '_reconduction';
            $isParcoursClosed = false;
            $trackOpenClosedChanged = false;

            // Find current status in DB
            $oldEnumVal = null;
            foreach ($parcours->getDpeParcours() as $d) {
                if ($d->getCampagneCollecte() === $campagne) {
                    $oldEnumVal = $d->getEtatReconduction();
                    break;
                }
            }
            $oldIsClosed = $oldEnumVal ? in_array($oldEnumVal, [
                TypeModificationDpeEnum::NON_OUVERTURE,
                TypeModificationDpeEnum::NON_OUVERTURE_SES,
                TypeModificationDpeEnum::NON_OUVERTURE_CFVU,
                TypeModificationDpeEnum::FERMETURE_DEFINITIVE
            ], true) : false;

            if ($request->request->has($parcoursKey)) {
                $val = $request->request->get($parcoursKey);
                $enumVal = TypeModificationDpeEnum::from($val);
                if ($enumVal === TypeModificationDpeEnum::OUVERT && $oldEnumVal !== null) {
                    $isOpenState = in_array($oldEnumVal, [
                        TypeModificationDpeEnum::OUVERT,
                        TypeModificationDpeEnum::CREATION,
                        TypeModificationDpeEnum::MODIFICATION,
                        TypeModificationDpeEnum::MODIFICATION_INTITULE,
                        TypeModificationDpeEnum::MODIFICATION_PARCOURS,
                        TypeModificationDpeEnum::MODIFICATION_TEXTE,
                        TypeModificationDpeEnum::MODIFICATION_MCCC,
                        TypeModificationDpeEnum::MODIFICATION_MCCC_TEXTE,
                    ], true);
                    if ($isOpenState) {
                        $enumVal = $oldEnumVal;
                    }
                }
                foreach ($parcours->getDpeParcours() as $d) {
                    if ($d->getCampagneCollecte() === $campagne) {
                        $d->setEtatReconduction($enumVal);
                        $em->persist($d);
                        break;
                    }
                }
                $isParcoursClosed = in_array($enumVal, [
                    TypeModificationDpeEnum::NON_OUVERTURE,
                    TypeModificationDpeEnum::NON_OUVERTURE_SES,
                    TypeModificationDpeEnum::NON_OUVERTURE_CFVU,
                    TypeModificationDpeEnum::FERMETURE_DEFINITIVE
                ], true);
                $trackOpenClosedChanged = ($oldIsClosed !== $isParcoursClosed);
            } else {
                $isParcoursClosed = $oldIsClosed;
            }

            foreach ($parcours->getAnnees() as $annee) {
                $anneeId = $annee->getId();
                
                $isOuvertKey = 'annee_' . $anneeId . '_isOuvert';
                $capaciteAccueilKey = 'annee_' . $anneeId . '_capaciteAccueil';
                
                $oldIsOuvert = $annee->isOuvert();
                $newIsOuvert = $oldIsOuvert;

                if ($isParcoursClosed) {
                    $newIsOuvert = false;
                } elseif ($request->request->has($isOuvertKey)) {
                    $val = $request->request->get($isOuvertKey);
                    $newIsOuvert = ($val === 'Ouverte' || $val === '1' || $val === 'true');
                }

                if ($oldIsOuvert !== $newIsOuvert || $trackOpenClosedChanged) {
                    $changedYears[$anneeId] = true;
                    $annee->setIsOuvert($newIsOuvert);
                }
                
                if ($isParcoursClosed) {
                    $annee->setCapaciteAccueil(0);
                } elseif ($request->request->has($capaciteAccueilKey)) {
                    $annee->setCapaciteAccueil((int)$request->request->get($capaciteAccueilKey));
                }
                
                $typeDiplome = $formation->getTypeDiplome();
                if ($typeDiplome) {
                    foreach ($typeDiplome->getTypeDiplomePlateformeAdmissions() as $tpa) {
                        if ($tpa->getCampagne() === $campagne && $tpa->getPlateforme()?->getActive()) {
                            $plateforme = $tpa->getPlateforme();
                            if (in_array($annee->getOrdre(), $tpa->getAnnees(), true)) {
                                $plateformeId = $plateforme->getId();
                                
                                $activeKey = 'annee_' . $anneeId . '_plateforme_' . $plateformeId . '_active';
                                $globaleKey = 'annee_' . $anneeId . '_plateforme_' . $plateformeId . '_globale';
                                $classiqueKey = 'annee_' . $anneeId . '_plateforme_' . $plateformeId . '_classique';
                                $alternanceKey = 'annee_' . $anneeId . '_plateforme_' . $plateformeId . '_alternance';
                                $specifiqueKey = 'annee_' . $anneeId . '_plateforme_' . $plateformeId . '_specifique';
                                
                                $parametre = $plateformeParamRepo->findOneBy([
                                    'annee' => $annee,
                                    'plateforme' => $plateforme,
                                    'campagne' => $campagne
                                ]);
                                
                                if (!$parametre) {
                                    $parametre = new PlateformeAdmissionParametre();
                                    $parametre->setAnnee($annee);
                                    $parametre->setPlateforme($plateforme);
                                    $parametre->setCampagne($campagne);
                                }
                                
                                $isActive = false;
                                if (!$isParcoursClosed && $newIsOuvert) {
                                    if ($request->request->has($activeKey)) {
                                        $actVal = $request->request->get($activeKey);
                                        $isActive = ($actVal === '1' || $actVal === 'on' || $actVal === 'true');
                                    }
                                }
                                $parametre->setActive($isActive);
                                
                                if ($isParcoursClosed || !$newIsOuvert) {
                                    $parametre->setCapaciteGlobale(null);
                                    $parametre->setCapaciteFi(null);
                                    $parametre->setCapaciteAlternance(0);
                                    $parametre->setCapaciteSpecifique(0);
                                } else {
                                    if ($request->request->has($globaleKey)) {
                                        $val = $request->request->get($globaleKey);
                                        $parametre->setCapaciteGlobale($val !== '' ? (int)$val : null);
                                    }
                                    
                                    if ($request->request->has($classiqueKey)) {
                                        $val = $request->request->get($classiqueKey);
                                        $parametre->setCapaciteFi($val !== '' ? (int)$val : null);
                                    }
                                    
                                    if ($request->request->has($alternanceKey)) {
                                        $val = $request->request->get($alternanceKey);
                                        $parametre->setCapaciteAlternance($val !== '' ? (int)$val : null);
                                    }
                                    
                                    if ($request->request->has($specifiqueKey)) {
                                        $val = $request->request->get($specifiqueKey);
                                        $parametre->setCapaciteSpecifique($val !== '' ? (int)$val : null);
                                    }
                                }
                                
                                $em->persist($parametre);
                            }
                        }
                    }
                }
            }
        }

        $em->flush();

        // Si la requête demande explicitement du Turbo Stream
        if ($request->headers->get('Accept') === 'text/vnd.turbo-stream.html' || str_contains($request->headers->get('Accept', ''), 'text/vnd.turbo-stream.html')) {
            $statsData = $this->calculerStatistiques($formation, $this->getCampagneCollecte(), $comparaisonService, $offreValidationService);
            
            $typeDiplome = $formation->getTypeDiplome();
            $plateformes = [];
            if ($typeDiplome) {
                foreach ($typeDiplome->getTypeDiplomePlateformeAdmissions() as $tpa) {
                    if ($tpa->getCampagne() === $campagne && $tpa->getPlateforme()?->getActive()) {
                        $plateforme = $tpa->getPlateforme();
                        $plateformes[] = [
                            'id' => $plateforme->getId(),
                            'libelle' => $plateforme->getLibelle(),
                            'code' => $plateforme->getCode(),
                            'color' => $plateforme->getColor(),
                            'definitionChamps' => $plateforme->getDefinitionChamps(),
                            'annees' => array_values($tpa->getAnnees()),
                        ];
                    }
                }
            }

            $response = $this->render('offre_v2/_configurer_streams.html.twig', [
                'formation' => $formation,
                'plateformes' => $plateformes,
                'campagne' => $campagne,
                'tabStatistiques' => $statsData['tabStatistiques'],
                'anomalies' => $statsData['anomalies'],
                'comparaison' => $statsData['comparaison'],
                'changedYears' => $changedYears,
            ]);
            $response->headers->set('Content-Type', 'text/vnd.turbo-stream.html');
            return $response;
        }

        if ($request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json' || $request->headers->get('Turbo-Frame') !== null) {
            return new JsonResponse(['success' => true, 'message' => 'Le brouillon a été enregistré avec succès.']);
        }

        $this->addFlashBag(Constantes::FLASHBAG_SUCCESS, 'Le brouillon a été enregistré avec succès.');
        return $this->redirectToRoute('offre_v2_configurer', ['slug' => $formation->getSlug()]);
    }

    private function calculerStatistiques(
        Formation                  $formation,
        CampagneCollecte           $campagne,
        ParcoursComparaisonService $comparaisonService,
        OffreValidationService     $offreValidationService,
    ): array {
        $tabStatistiques = [];
        $tabStatistiques['nbFormations'] = 1;
        $tabStatistiques['parcours'] = [];
        $tabStatistiques['nbParcours'] = $formation->getParcours()->count();
        $tabStatistiques['nbParcoursOuvert'] = 0;
        $tabStatistiques['capacite'] = 0;
        
        $anomalies = $offreValidationService->getAnomaliesFormation($formation, $campagne);
        $tableau = [];

        foreach ($formation->getParcours() as $parcours) {
            $tableau[$parcours->getId()] = $comparaisonService->construireTableauComparaison($parcours);
            
            $tabStatistiques['parcours'][$parcours->getId()] = [
                'nbAnnees' => $parcours->getAnnees()->count(),
                'nbAnneesOuvertes' => 0,
                'capacite' => 0,
                'nbPlateformesActives' => 0,
                'anomalies' => $offreValidationService->getAnomaliesParcours($parcours, $campagne)
            ];
            
            if ($parcours->isOuvert() === true) {
                $tabStatistiques['nbParcoursOuvert']++;
                
                $activePlateformes = [];
                foreach ($parcours->getAnnees() as $annee) {
                    if ($annee->isOuvert() === true) {
                        $tabStatistiques['parcours'][$parcours->getId()]['nbAnneesOuvertes']++;
                        $tabStatistiques['parcours'][$parcours->getId()]['capacite'] += $annee->getCapaciteAccueil();
                        $tabStatistiques['capacite'] += $annee->getCapaciteAccueil();
                        
                        foreach ($annee->getAdmissionPlateformeParametres() as $param) {
                            if ($param->getCampagne() === $campagne && $param->isActive()) {
                                $activePlateformes[$param->getPlateforme()?->getId()] = true;
                            }
                        }
                    }
                }
                
                $tabStatistiques['parcours'][$parcours->getId()]['nbPlateformesActives'] = count($activePlateformes);
            }
        }
        
        return [
            'tabStatistiques' => $tabStatistiques,
            'anomalies' => $anomalies,
            'comparaison' => $tableau
        ];
    }

    #[Route('/offre/parcours/{parcours}/modal-edit', name: 'offre_v2_parcours_modal_edit', methods: ['GET'])]
    public function modalEditParcours(
        Parcours $parcours,
        TurboStreamResponseFactory $turboStream,
        EntityManagerInterface $em,
    ): Response {
        $campagne = $this->getCampagneCollecte();
        $formation = $parcours->getFormation();

        $dpeParcours = $em->getRepository(DpeParcours::class)->findOneBy([
            'parcours' => $parcours,
            'campagneCollecte' => $campagne,
        ]);

        $parcoursOrigine = $parcours->getParcoursOrigine() ?? $parcours->getParcoursOrigineCopie();

        $isModifieN1 = ($dpeParcours?->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION_INTITULE);
        if (!$isModifieN1 && $parcoursOrigine && $parcoursOrigine->getLibelle() !== $parcours->getLibelle()) {
            $isModifieN1 = true;
        }

        return $turboStream->streamOpenModalFromTemplates(
            'Modifier le parcours',
            $parcours->getLibelle(),
            'offre_v2/_modal_edit_parcours.html.twig',
            [
                'parcours' => $parcours,
                'formation' => $formation,
                'parcoursOrigine' => $parcoursOrigine,
                'dpeParcours' => $dpeParcours,
                'isModifieN1' => $isModifieN1,
                'typesParcours' => TypeParcoursEnum::cases(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            [
                'submitLabel' => 'Enregistrer',
            ]
        );
    }

    #[Route('/offre/parcours/{parcours}/modal-edit/sauvegarder', name: 'offre_v2_parcours_modal_sauvegarder', methods: ['POST'])]
    public function modalSauvegarderParcours(
        Parcours $parcours,
        Request $request,
        EntityManagerInterface $em,
        TurboStreamResponseFactory $turboStream,
    ): Response {
        $csrfToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('parcours_edit_' . $parcours->getId(), $csrfToken)) {
            return $turboStream->streamToastError('Token CSRF invalide.');
        }

        $campagne = $this->getCampagneCollecte();
        $formation = $parcours->getFormation();

        $isSesOrAdmin = $this->isGranted('ROLE_SES') || $this->isGranted('ROLE_ADMIN');
        if (!$isSesOrAdmin) {
            if (!$campagne->isPeriodActive()) {
                return $turboStream->streamToastError('La campagne de collecte est fermée. Modification impossible.');
            }

            $dpeFormation = $em->getRepository(DpeFormation::class)->findOneBy([
                'formation' => $formation,
                'campagneCollecte' => $campagne,
            ]);

            $isBrouillon = $dpeFormation === null || array_key_exists('brouillon', $dpeFormation->getEtatValidation());
            if (!$isBrouillon) {
                return $turboStream->streamToastError('L\'offre a déjà été transmise pour validation. Modification impossible.');
            }
        }

        $libelle = trim((string)$request->request->get('libelle', ''));
        if ($libelle === '') {
            return $turboStream->streamToastError('Le libellé du parcours ne peut pas être vide.');
        }

        $parcours->setLibelle($libelle);

        $typeParcoursRaw = $request->request->get('typeParcours');
        if ($typeParcoursRaw) {
            $typeEnum = TypeParcoursEnum::tryFrom($typeParcoursRaw);
            if ($typeEnum !== null) {
                $parcours->setTypeParcours($typeEnum);
            }
        }

        $dpeParcours = $em->getRepository(DpeParcours::class)->findOneBy([
            'parcours' => $parcours,
            'campagneCollecte' => $campagne,
        ]);

        if ($dpeParcours === null) {
            $dpeParcours = new DpeParcours();
            $dpeParcours->setParcours($parcours);
            $dpeParcours->setCampagneCollecte($campagne);
            $dpeParcours->setFormation($formation);
            $dpeParcours->setEtatReconduction(TypeModificationDpeEnum::OUVERT);
            $em->persist($dpeParcours);
        }

        $isModifieN1 = $request->request->getBoolean('isModifieN1');
        if ($isModifieN1) {
            $dpeParcours->setEtatReconduction(TypeModificationDpeEnum::MODIFICATION_INTITULE);
        } else {
            if ($dpeParcours->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION_INTITULE) {
                $dpeParcours->setEtatReconduction(TypeModificationDpeEnum::OUVERT);
            }
        }

        $em->flush();

        $dpeFormation = $em->getRepository(DpeFormation::class)->findOneBy([
            'formation' => $formation,
            'campagneCollecte' => $campagne,
        ]);
        $isBrouillon = $dpeFormation === null || array_key_exists('brouillon', $dpeFormation->getEtatValidation());
        $canEdit = $isSesOrAdmin || ($isBrouillon && $campagne->isPeriodActive());

        return $turboStream->stream('offre_v2/_parcours_save_stream.html.twig', [
            'parcours' => $parcours,
            'campagne' => $campagne,
            'can_edit' => $canEdit,
        ]);
    }


    #[Route('/conseils/synthese-offre', name: 'app_conseils_synthese_offre')]
    public function syntheseOffre(
        PlateformeAdmissionRepository $plateformeAdmissionRepository
    ): Response
    {
        if (
            !$this->isGranted('ROLE_ADMIN')
            && !$this->isGranted('EDIT', [
                'route' => 'app_etablissement',
                'subject' => 'etablissement',
            ])
        ) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        $projectDir = $this->getParameter('kernel.project_dir');
        $jsonPath = $projectDir . '/public/Docs-offre/synthese_offre_data.json';
        
        $data = [];
        if (file_exists($jsonPath)) {
            $json = file_get_contents($jsonPath);
            $data = json_decode($json, true);
        }
        
        return $this->render('offre_v2/synthese_offre.html.twig', [
            'plateformes' => $plateformeAdmissionRepository->findAll()
        ]);
    }

    #[Route('/conseils/synthese-offre/table', name: 'app_conseils_synthese_offre_table')]
    public function syntheseOffreTable(
        Request $request,
        DpeParcoursRepository $dpeParcoursRepository,
        PlateformeAdmissionParametreRepository $plateformeParamRepo,
        TypeDiplomePlateformeAdmissionRepository $typeDiplomePlateformeAdmissionRepository,
        AnneeRepository $anneeRepository,
        EntityManagerInterface $em
    ): Response {
        if (
            !$this->isGranted('ROLE_ADMIN')
            && !$this->isGranted('EDIT', [
                'route' => 'app_etablissement',
                'subject' => 'etablissement',
            ])
        ) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        $platformCode = $request->query->get('platform', 'PSUP');
        $categoryIdx = (int)$request->query->get('category', 0);
        $campagne = $this->getCampagneCollecte();

        // Find platform entity
        $platformEntity = $em->getRepository(\App\Entity\PlateformeAdmission::class)->findOneBy(['code' => $platformCode]);
        if (!$platformEntity) {
            $platformEntity = $em->getRepository(\App\Entity\PlateformeAdmission::class)->findOneBy(['code' => strtolower($platformCode)]);
        }

        $ecCategories = [
            "BUT 2 & BUT 3",
            "Licences L2 & L3",
            "Licence Professionnelle (LP)",
            "Diplôme d'Ingénieur (DI)",
            "Master 2 & Autres"
        ];
        $selectedCategory = $ecCategories[$categoryIdx] ?? "Master 2 & Autres";

        // Filter and build tree from Database
        $tFormations = [];
        $anneeParams = []; // anneeId => PlateformeAdmissionParametre

        if ($platformEntity) {
            $allDpeParcours = $dpeParcoursRepository->findByCampagneCollecte($campagne);
            $tpaByTypeDiplome = $typeDiplomePlateformeAdmissionRepository->findByCampagneIndexedByTypeDiplome($campagne);
            $anneesByParcours = $anneeRepository->findByCampagneIndexedByParcours($campagne);

            // Pre-load all parameters for this platform in 1 single query (instead of 1000+ findOneBy)
            $platformParams = $plateformeParamRepo->findBy([
                'plateforme' => $platformEntity,
                'campagne' => $campagne,
            ]);
            $platformParamsByAnnee = [];
            foreach ($platformParams as $pp) {
                if ($pp->getAnnee() !== null) {
                    $platformParamsByAnnee[$pp->getAnnee()->getId()] = $pp;
                }
            }

            foreach ($allDpeParcours as $dpePar) {
                $par = $dpePar->getParcours();
                if (!$par) continue;
                $formation = $par->getFormation();
                if (!$formation) continue;

                $keptAnnees = [];
                $typeDipl = $formation->getTypeDiplome();
                $typeDiplId = $typeDipl?->getId();
                $tpas = ($typeDiplId && isset($tpaByTypeDiplome[$typeDiplId])) ? $tpaByTypeDiplome[$typeDiplId] : [];

                $parAnnees = $anneesByParcours[$par->getId()] ?? [];
                foreach ($parAnnees as $annee) {
                    // Check if this platform is configured/valid for this diploma type and year order in this campaign
                    $shouldBeActive = false;
                    foreach ($tpas as $tpa) {
                        if ($tpa->getPlateforme()?->getId() === $platformEntity->getId()) {
                            if (in_array($annee->getOrdre(), $tpa->getAnnees() ?? [], true)) {
                                $shouldBeActive = true;
                                break;
                            }
                        }
                    }

                    // For eCandidat, we group by selected sub-category
                    if (strtoupper($platformEntity->getCode()) === 'EC') {
                        $diplCode = $typeDipl ? strtoupper($typeDipl->getLibelleCourt()) : '';

                        $cat = "Master 2 & Autres";
                        if ($diplCode === 'BUT') {
                            $cat = "BUT 2 & BUT 3";
                        } elseif ($diplCode === 'L' || $diplCode === 'LICENCE') {
                            $cat = "Licences L2 & L3";
                        } elseif ($diplCode === 'LP') {
                            $cat = "Licence Professionnelle (LP)";
                        } elseif ($diplCode === 'DI') {
                            $cat = "Diplôme d'Ingénieur (DI)";
                        }

                        if ($cat !== $selectedCategory) {
                            continue; // Skip because it belongs to another sub-tab
                        }
                    } else {
                        // For other platforms, only show the year if the platform is defined for this year's order
                        if (!$shouldBeActive) {
                            continue;
                        }
                    }

                    $param = $platformParamsByAnnee[$annee->getId()] ?? null;

                    $keptAnnees[] = $annee;
                    if ($param) {
                        $anneeParams[$annee->getId()] = $param;
                    }
                }

                if (count($keptAnnees) > 0) {
                    $idFormation = $formation->getId();
                    if (!isset($tFormations[$idFormation])) {
                        $tFormations[$idFormation] = [
                            'formation' => $formation,
                            'parcoursList' => []
                        ];
                    }
                    
                    $isParcoursOuvert = ($dpePar->getEtatReconduction() === \App\Enums\TypeModificationDpeEnum::OUVERT);

                    $tFormations[$idFormation]['parcoursList'][] = [
                        'parcours' => $par,
                        'isOuvert' => $isParcoursOuvert,
                        'etatReconductionLibelle' => $dpePar->getEtatReconduction() ? $dpePar->getEtatReconduction()->value : '-',
                        'annees' => $keptAnnees
                    ];
                }
            }
        }

        return $this->render('offre_v2/_synthese_offre_table.html.twig', [
            'tFormations' => $tFormations,
            'anneeParams' => $anneeParams,
            'platformCode' => $platformCode,
            'categoryIdx' => $categoryIdx,
        ]);
    }

    #[Route('/offrev2/configurer-dates', name: 'offre_v2_configurer_dates', methods: ['GET'])]
    public function configurerDates(
        TurboStreamResponseFactory $turboStream
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $campagne = $this->getCampagneCollecte();

        return $turboStream->streamOpenModalFromTemplates(
            'Dates de collecte des capacités',
            'Campagne : ' . $campagne->getLibelle(),
            'offre_v2/_modal_configurer_dates.html.twig',
            [
                'campagne' => $campagne,
            ],
            'offre_v2/_modal_configurer_dates_footer.html.twig',
            [
                'campagne' => $campagne,
            ]
        );
    }

    #[Route('/offrev2/configurer-dates/sauvegarder', name: 'offre_v2_sauvegarder_dates', methods: ['POST'])]
    public function sauvegarderDates(
        Request $request,
        CampagneCollecteService $campagneService,
        TurboStreamResponseFactory $turboStream
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $campagne = $this->getCampagneCollecte();
        $csrfToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('configurer_dates_' . $campagne->getId(), $csrfToken)) {
            return new JsonResponse(['success' => false, 'message' => 'Token CSRF invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $dateOuvertureStr = $request->request->get('dateOuvertureDpe');
        $dateClotureStr = $request->request->get('dateClotureDpe');

        $dateOuverture = $dateOuvertureStr ? new \DateTime($dateOuvertureStr) : null;
        $dateCloture = $dateClotureStr ? new \DateTime($dateClotureStr) : null;

        $campagneService->updateDates($campagne, $dateOuverture, $dateCloture);

        return $turboStream->streamToastSuccess('Dates de la campagne de collecte des capacités enregistrées.', true);
    }

    #[Route('/offre/composante/{composante}/validation/{transition}', name: 'offre_v2_composante_valider', methods: ['GET', 'POST'])]
    public function composanteValidation(
        Composante $composante,
        string $transition,
        Request $request,
        FormationRepository $formationRepository,
        DpeParcoursRepository $dpeParcoursRepository,
        DpeFormationRepository $dpeFormationRepository,
        DocumentConseilRepository $documentConseilRepository,
        AnneeRepository $anneeRepository,
        PlateformeAdmissionParametreRepository $plateformeParamRepository,
        OffreValidationService $offreValidationService,
        SecureUploadService $secureUploadService,
        EntityManagerInterface $em,
        WorkflowInterface $dpeFormationWorkflow,
        TurboStreamResponseFactory $turboStream
    ): Response {
        $campagne = $this->getCampagneCollecte();

        // 1. Contrôle des droits d'accès
        if ($transition === 'transmettre') {
            if (!$this->isGranted('ROLE_ADMIN')) {
                $this->denyAccessUnlessGranted('MANAGE', [
                    'route' => 'app_composante',
                    'subject' => $composante,
                ]);
            }
            if (!$campagne->isPeriodActive() && !$this->isGranted('ROLE_SES') && !$this->isGranted('ROLE_ADMIN')) {
                throw $this->createAccessDeniedException('La campagne de collecte est fermée.');
            }
        } else {
            if (!$this->isGranted('ROLE_SES') && !$this->isGranted('ROLE_ADMIN')) {
                throw $this->createAccessDeniedException('Accès interdit aux responsables de composante.');
            }
        }

        // 2. Récupérer les métadonnées de la transition du workflow
        $meta = [];
        foreach ($dpeFormationWorkflow->getDefinition()->getTransitions() as $t) {
            if ($t->getName() === $transition) {
                $meta = $dpeFormationWorkflow->getMetadataStore()->getTransitionMetadata($t);
                break;
            }
        }
        $isRefuse = ($meta['type'] ?? '') === 'reserver' || str_starts_with($transition, 'reserver');

        // 3. Récupérer toutes les formations de la composante et leurs parcours
        $allFormations = $formationRepository->findBy([
            'composantePorteuse' => $composante,
        ]);

        $allParcours = $dpeParcoursRepository->findByCampagneCollecte($campagne, $composante);
        $anneesByParcours = $anneeRepository->findByCampagneIndexedByParcours($campagne);
        $paramsByAnnee = $plateformeParamRepository->findByCampagneIndexedByAnnee($campagne);

        $dpeFormations = !empty($allFormations) ? $dpeFormationRepository->findBy([
            'campagneCollecte' => $campagne,
            'formation' => $allFormations,
        ]) : [];

        $dpeFormationByFormationId = [];
        foreach ($dpeFormations as $df) {
            if ($df->getFormation() !== null) {
                $dpeFormationByFormationId[$df->getFormation()->getId()] = $df;
            }
        }

        $parcoursByFormationId = [];
        foreach ($allParcours as $dpePar) {
            $parcours = $dpePar->getParcours();
            $formation = $parcours?->getFormation();
            if ($formation !== null && $parcours !== null) {
                $parcoursByFormationId[$formation->getId()][] = $dpePar;
            }
        }

        $formationsData = [];
        foreach ($allFormations as $forma) {
            $fId = $forma->getId();
            $dpeParList = $parcoursByFormationId[$fId] ?? [];
            $formaHasAnomalies = false;
            $formaAnomaliesCount = 0;
            $parcoursList = [];

            foreach ($dpeParList as $dpePar) {
                $parcours = $dpePar->getParcours();
                if ($parcours === null) {
                    continue;
                }
                $parcAnnees = $anneesByParcours[$parcours->getId()] ?? [];
                $parcAnoms = $offreValidationService->getAnomaliesParcours($parcours, $campagne, $dpePar, $paramsByAnnee, $parcAnnees);
                $isParcConforme = (count($parcAnoms) === 0);
                if (!$isParcConforme) {
                    $formaHasAnomalies = true;
                    $formaAnomaliesCount += count($parcAnoms);
                }

                $capParcours = 0;
                foreach ($parcAnnees as $an) {
                    $capParcours += $an->getCapaciteAccueil();
                }

                $parcoursList[] = [
                    'parcours' => $parcours,
                    'dpeParcours' => $dpePar,
                    'isOuvert' => ($dpePar->getEtatReconduction() === TypeModificationDpeEnum::OUVERT),
                    'capacite' => $capParcours,
                    'isConforme' => $isParcConforme,
                    'anomalies' => $parcAnoms,
                ];
            }

            $formationsData[$fId] = [
                'formation' => $forma,
                'dpeFormation' => $dpeFormationByFormationId[$fId] ?? null,
                'hasAnomalies' => $formaHasAnomalies,
                'anomaliesCount' => $formaAnomaliesCount,
                'parcoursList' => $parcoursList,
            ];
        }

        // 4. Traitement POST
        if ($request->isMethod('POST')) {
            $selectedFormationIds = array_map('intval', (array)$request->request->all('formations'));
            $selectedParcoursIds = array_map('intval', (array)$request->request->all('parcours'));

            $dateStr = (string)$request->request->get('date');
            $dateConseil = !empty($dateStr) ? new \DateTime($dateStr) : new \DateTime();

            $commentaire = (string)$request->request->get('commentaire');
            $laisserPasser = (bool)$request->request->get('laisserPasser');
            $laisserPasserJustif = (string)$request->request->get('laisserPasserJustif');

            // Documents PV
            $docPv = null;
            $pvId = $request->request->get('pv_id');
            if ($pvId) {
                $docPv = $documentConseilRepository->find($pvId);
            } elseif ($request->files->has('file') && $request->files->get('file') !== null) {
                try {
                    $uploadedPv = $secureUploadService->uploadFromRequest($request, 'file', 'conseils');
                    if ($uploadedPv !== null) {
                        $docPv = new DocumentConseil();
                        $docPv->setType('pv');
                        $docPv->setFilename($uploadedPv->getStoredFilename());
                        $docPv->setOriginalFilename($uploadedPv->getOriginalFilename());
                        $docPv->setDateConseil($dateConseil);
                        $docPv->setUploadedBy($this->getUser());
                        $docPv->setComposante($composante);
                        $em->persist($docPv);
                    }
                } catch (FileUploadException $exception) {
                    return $turboStream->streamToastError($exception->getPublicMessage());
                }
            }

            // Document Note
            $docNote = null;
            $noteId = $request->request->get('note_id');
            if ($noteId) {
                $docNote = $documentConseilRepository->find($noteId);
            } elseif ($request->files->has('fileNote') && $request->files->get('fileNote') !== null) {
                try {
                    $uploadedNote = $secureUploadService->uploadFromRequest($request, 'fileNote', 'conseils');
                    if ($uploadedNote !== null) {
                        $docNote = new DocumentConseil();
                        $docNote->setType('note_explicative');
                        $docNote->setFilename($uploadedNote->getStoredFilename());
                        $docNote->setOriginalFilename($uploadedNote->getOriginalFilename());
                        $docNote->setDateConseil($dateConseil);
                        $docNote->setUploadedBy($this->getUser());
                        $docNote->setComposante($composante);
                        $em->persist($docNote);
                    }
                } catch (FileUploadException $exception) {
                    return $turboStream->streamToastError($exception->getPublicMessage());
                }
            }

            $motifs = [];
            if ($laisserPasser) {
                $motifs['laisserPasser'] = $laisserPasserJustif ?: '1';
            }
            if ($commentaire !== '') {
                $motifs['motif'] = $commentaire;
            }

            foreach ($allFormations as $forma) {
                $fId = $forma->getId();
                if (!empty($selectedFormationIds) && !in_array($fId, $selectedFormationIds, true)) {
                    continue;
                }

                $dpeF = $dpeFormationByFormationId[$fId] ?? null;
                if ($dpeF === null) {
                    $dpeF = new DpeFormation();
                    $dpeF->setFormation($forma);
                    $dpeF->setCampagneCollecte($campagne);
                    $dpeF->setEtatValidation(['brouillon' => 1]);
                    $em->persist($dpeF);
                    $dpeFormationByFormationId[$fId] = $dpeF;
                }

                if ($docPv !== null) {
                    $docPv->addFormation($forma);
                    $forma->addDocumentConseil($docPv);
                }
                if ($docNote !== null) {
                    $docNote->addFormation($forma);
                    $forma->addDocumentConseil($docNote);
                }

                if ($laisserPasser) {
                    $dpeF->setLaissezPasser($laisserPasserJustif ?: '1');
                }

                // HistoriqueFormation
                $histo = new HistoriqueFormation();
                $histo->setFormation($forma);
                $histo->setDpeFormation($dpeF);
                $histo->setDate($dateConseil);
                $histo->setUser($this->getUser());
                $histo->setEtape($transition);
                $histo->setEtat($isRefuse ? 'refuse' : ($laisserPasser ? 'laisserPasser' : 'valide'));
                $histo->setCommentaire($commentaire);

                if ($docPv !== null) {
                    $histo->setDocumentPv($docPv);
                }
                if ($docNote !== null) {
                    $histo->setDocumentNote($docNote);
                }

                $complements = [];
                if ($docPv !== null) {
                    $complements['fichier'] = $docPv->getFilename();
                    $complements['fichier_original'] = $docPv->getOriginalFilename();
                }
                if ($docNote !== null) {
                    $complements['fichier_note'] = $docNote->getFilename();
                    $complements['fichier_note_original'] = $docNote->getOriginalFilename();
                }
                if ($laisserPasser) {
                    $complements['laisserPasser'] = $laisserPasserJustif ?: '1';
                }
                $histo->setComplements($complements);
                $em->persist($histo);

                if ($dpeFormationWorkflow->can($dpeF, $transition)) {
                    $dpeFormationWorkflow->apply($dpeF, $transition, $motifs);
                }
            }

            $em->flush();

            return $turboStream->stream('offre_v2/turbo/apply_success.stream.html.twig', [
                'message' => sprintf('Validation de l\'offre enregistrée pour %s', $composante->getLibelle()),
            ]);
        }

        // 5. Affichage GET Modal
        $existingPvs = $documentConseilRepository->findBy([
            'composante' => $composante,
            'type' => 'pv',
        ], ['uploadedAt' => 'DESC']);

        $existingNotes = $documentConseilRepository->findBy([
            'composante' => $composante,
            'type' => 'note_explicative',
        ], ['uploadedAt' => 'DESC']);

        $modalTitle = $meta['label'] ?? sprintf('Validation — %s', $transition);

        return $turboStream->streamOpenModalFromTemplates(
            $modalTitle,
            sprintf('Composante : %s', $composante->getLibelle()),
            'offre_v2/_modal_validation_composante.html.twig',
            [
                'composante' => $composante,
                'transition' => $transition,
                'meta' => $meta,
                'isRefuse' => $isRefuse,
                'formationsData' => $formationsData,
                'existingPvs' => $existingPvs,
                'existingNotes' => $existingNotes,
            ],
            '_ui/_footer_submit_cancel.html.twig'
        );
    }
}
