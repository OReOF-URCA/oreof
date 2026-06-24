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
use App\Service\ParcoursComparaisonService;
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
        ];
        //        if ($isFrame) {
        //            // Répondre avec un markup contenant le <turbo-frame id="offre_table"> attendu
        //            return $this->render('offre/_table_frame.html.twig', $params);
        //        }


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

        foreach ($formation->getParcours() as $parcours) {
            foreach ($parcours->getAnnees() as $annee) {
                $anneeId = $annee->getId();
                
                $isOuvertKey = 'annee_' . $anneeId . '_isOuvert';
                $capaciteAccueilKey = 'annee_' . $anneeId . '_capaciteAccueil';
                
                if ($request->request->has($isOuvertKey)) {
                    $val = $request->request->get($isOuvertKey);
                    $annee->setIsOuvert($val === 'Ouverte' || $val === '1' || $val === 'true');
                }
                
                if ($request->request->has($capaciteAccueilKey)) {
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
                                if ($request->request->has($activeKey)) {
                                    $actVal = $request->request->get($activeKey);
                                    $isActive = ($actVal === '1' || $actVal === 'on' || $actVal === 'true');
                                }
                                $parametre->setActive($isActive);
                                
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
}
