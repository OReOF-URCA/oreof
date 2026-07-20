<?php

namespace App\Controller\Offre;

use App\Controller\BaseController;
use App\Entity\Constantes;
use App\Entity\CampagneCollecte;
use App\Entity\Formation;
use App\Entity\PlateformeAdmissionParametre;
use App\Enums\TypeModificationDpeEnum;
use App\Repository\AnneeRepository;
use App\Repository\DpeParcoursRepository;
use App\Repository\PlateformeAdmissionParametreRepository;
use App\Repository\PlateformeAdmissionRepository;
use App\Service\CampagneCollecteService;
use App\Service\ParcoursComparaisonService;
use App\Utils\TurboStreamResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OffreController extends BaseController
{
    #[Route('/offrev2', name: 'app_offre_index')]
    public function index(
        Request               $request,
        DpeParcoursRepository $dpeParcoursRepository,
    ): Response
    {
        $tabStatistiques = [
            'nbFormations' => 0,
            'nbParcours' => 0,
            'nbParcoursOuvert' => 0,
            'capacite' => 0,
            'capaciteTotale' => 0
        ];

        // récupérer l'ensemble des formations et des parcours associés, pour chacun l'état du DPE courant
        $allParcours = $dpeParcoursRepository->findByCampagneCollecte($this->getCampagneCollecte());
        $tFormations = [];
        foreach ($allParcours as $parcours) {
            $formation = $parcours->getParcours()?->getFormation();
            $idFormation = $formation?->getId();
            if ($idFormation === null) {
                continue;
            }
            if (!array_key_exists($idFormation, $tFormations)) {
                $tFormations[$idFormation]['formation'] = $formation;
                $tFormations[$idFormation]['dpeParcours'] = [];
            }
            $tFormations[$idFormation]['dpeParcours'][] = $parcours;
            $tabStatistiques['nbParcoursOuvert'] += $parcours->getEtatReconduction() === TypeModificationDpeEnum::OUVERT ? 1 : 0;
        }

        // Construire les listes de filtres disponibles à partir des données
        $types = [];
        $composantes = [];
        $villes = [];
        foreach ($tFormations as $row) {
            $tabStatistiques['capaciteTotale'] += $row['formation']->getCapacite();
            $f = $row['formation'];
            $types[$f->getTypeDiplome()?->getLibelle() ?? ''] = true;
            $composantes[$f->getComposantePorteuse()?->getLibelle() ?? ''] = true;
            if ($f->isHasParcours() === false) {
                $loc = $f->getLocalisationMention()->first();
                $ville = is_object($loc) ? $loc->getLibelle() : null;
                $villes[$ville ?? ''] = true;
            } else {
                // si RF, les villes sont gérées au niveau des parcours; on ne les agrège pas ici pour simplifier le POC
            }
        }
        $types = array_values(array_filter(array_keys($types)));
        sort($types);
        $composantes = array_values(array_filter(array_keys($composantes)));
        sort($composantes);
        $villes = array_values(array_filter(array_keys($villes)));
        sort($villes);

        // Lire les filtres de la requête
        $q = trim((string)$request->query->get('q', ''));
        $type = (string)$request->query->get('type', '');
        $comp = (string)$request->query->get('comp', '');
        $ville = (string)$request->query->get('ville', '');

        // Appliquer les filtres côté PHP sur le tableau tFormations
        if ($q || $type || $comp || $ville) {
            $tFormations = array_filter($tFormations, static function (array $row) use ($q, $type, $comp, $ville): bool {
                $f = $row['formation'];
                // Libellé
                if ($q !== '') {
                    $lib = (string)($f->getDisplayLong() ?? '');
                    if (mb_stripos($lib, $q) === false) {
                        return false;
                    }
                }
                // Type diplôme
                if ($type !== '') {
                    if (((string)($f->getTypeDiplome()?->getLibelle() ?? '')) !== $type) {
                        return false;
                    }
                }
                // Composante
                if ($comp !== '') {
                    if (((string)($f->getComposantePorteuse()?->getLibelle() ?? '')) !== $comp) {
                        return false;
                    }
                }
                // Ville (seulement lorsque pas de parcours)
                if ($ville !== '' && $f->isHasParcours() === false) {
                    $v = (string)(($f->getLocalisationMention()->first())?->getLibelle() ?? '');
                    if ($v !== $ville) {
                        return false;
                    }
                }

                return true;
            });
        }

        foreach ($tFormations as $row) {
            $tabStatistiques['nbFormations']++;
            $tabStatistiques['nbParcours'] += count($row['dpeParcours']);
            $tabStatistiques['capacite'] += $row['formation']->getCapacite();
        }

        // Déterminer si on ne doit renvoyer que le fragment du frame
        $frameId = $request->headers->get('Turbo-Frame');
        $isFrame = $frameId === 'offre_table';

        $params = [
            'tFormations' => $tFormations,
            'filters' => [
                'q' => $q,
                'type' => $type,
                'comp' => $comp,
                'ville' => $ville,
            ],
            'choices' => [
                'types' => $types,
                'composantes' => $composantes,
                'villes' => $villes,
            ],
            'tabStatistiques' => $tabStatistiques,
            'campagne' => $this->getCampagneCollecte()
        ];


        return $this->render('offre_v2/index.html.twig', $params);
    }

    #[Route('/offre/{slug}/configurer', name: 'offre_v2_configurer')]
    public function configurer(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Formation                  $formation,
        ParcoursComparaisonService $comparaisonService,
    ): Response
    {
        $campagne = $this->getCampagneCollecte();
        $statsData = $this->calculerStatistiques($formation, $campagne, $comparaisonService);

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

        return $this->render('offre_v2/configurer.html.twig', [
            'formation' => $formation,
            'plateformes' => $plateformes,
            'campagne' => $campagne,
            'tabStatistiques' => $statsData['tabStatistiques'],
            'anomalies' => $statsData['anomalies'],
            'comparaison' => $statsData['comparaison'],
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
    ): Response {
        $csrfToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('offre_v2_configurer_' . $formation->getId(), $csrfToken)) {
            return new JsonResponse(['success' => false, 'message' => 'Token CSRF invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $campagne = $this->getCampagneCollecte();

        if (!$campagne->isPeriodActive() && !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse([
                'success' => false,
                'message' => 'La campagne de collecte est fermée. Modification impossible.'
            ], Response::HTTP_FORBIDDEN);
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
            $statsData = $this->calculerStatistiques($formation, $this->getCampagneCollecte(), $comparaisonService);
            
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
    ): array {
        $tabStatistiques = [];
        $tabStatistiques['nbFormations'] = 1;
        $tabStatistiques['parcours'] = [];
        $tabStatistiques['nbParcours'] = $formation->getParcours()->count();
        $tabStatistiques['nbParcoursOuvert'] = 0;
        $tabStatistiques['capacite'] = 0;
        
        $anomalies = [];
        $tableau = [];

        foreach ($formation->getParcours() as $parcours) {
            $tableau[$parcours->getId()] = $comparaisonService->construireTableauComparaison($parcours);
            
            $tabStatistiques['parcours'][$parcours->getId()] = [
                'nbAnnees' => $parcours->getAnnees()->count(),
                'nbAnneesOuvertes' => 0,
                'capacite' => 0,
                'nbPlateformesActives' => 0,
                'anomalies' => []
            ];
            
            if ($parcours->isOuvert() === true) {
                $tabStatistiques['nbParcoursOuvert']++;
                
                $activePlateformes = [];
                foreach ($parcours->getAnnees() as $annee) {
                    if ($annee->isOuvert() === true) {
                        $tabStatistiques['parcours'][$parcours->getId()]['nbAnneesOuvertes']++;
                        $tabStatistiques['parcours'][$parcours->getId()]['capacite'] += $annee->getCapaciteAccueil();
                        $tabStatistiques['capacite'] += $annee->getCapaciteAccueil();
                        
                        // Anomalie 1: Capacité globale nulle ou non renseignée
                        if ($annee->getCapaciteAccueil() <= 0) {
                            $msg = sprintf("Le parcours %s (%s) est ouvert mais sa capacité globale est nulle ou non renseignée.", $parcours->getLibelle(), "Année " . $annee->getOrdre());
                            $anomalies[] = ['message' => $msg];
                            $tabStatistiques['parcours'][$parcours->getId()]['anomalies'][] = $msg;
                        }

                        // Parcourir les plateformes actives
                        foreach ($annee->getAdmissionPlateformeParametres() as $param) {
                            if ($param->getCampagne() === $campagne && $param->isActive()) {
                                $activePlateformes[$param->getPlateforme()?->getId()] = true;
                                
                                // Anomalie 2: Plateforme active sans aucune capacité renseignée
                                if (($param->getCapaciteGlobale() === null || $param->getCapaciteGlobale() <= 0) &&
                                    ($param->getCapaciteFi() === null || $param->getCapaciteFi() <= 0) &&
                                    ($param->getCapaciteAlternance() === null || $param->getCapaciteAlternance() <= 0)) {
                                    
                                    $msg = sprintf("Le parcours %s (%s) a la plateforme %s active, mais aucune capacité n'est renseignée.", $parcours->getLibelle(), "Année " . $annee->getOrdre(), $param->getPlateforme()?->getLibelle());
                                    $anomalies[] = ['message' => $msg];
                                    $tabStatistiques['parcours'][$parcours->getId()]['anomalies'][] = $msg;
                                }
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

            foreach ($allDpeParcours as $dpePar) {
                $par = $dpePar->getParcours();
                if (!$par) continue;
                $formation = $par->getFormation();
                if (!$formation) continue;

                $keptAnnees = [];
                foreach ($par->getAnnees() as $annee) {
                    // Check if this platform is configured/valid for this diploma type and year order in this campaign
                    $shouldBeActive = false;
                    $typeDipl = $formation->getTypeDiplome();
                    if ($typeDipl) {
                        foreach ($typeDipl->getTypeDiplomePlateformeAdmissions() as $tpa) {
                            if ($tpa->getCampagne() === $campagne && $tpa->getPlateforme() === $platformEntity) {
                                if (in_array($annee->getOrdre(), $tpa->getAnnees(), true)) {
                                    $shouldBeActive = true;
                                    break;
                                }
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

                    $param = $plateformeParamRepo->findOneBy([
                        'annee' => $annee,
                        'plateforme' => $platformEntity,
                        'campagne' => $campagne
                    ]);

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
}
