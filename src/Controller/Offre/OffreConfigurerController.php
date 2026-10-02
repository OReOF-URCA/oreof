<?php

namespace App\Controller\Offre;

use App\Controller\BaseController;
use App\Entity\CampagneCollecte;
use App\Entity\Constantes;
use App\Entity\DpeFormation;
use App\Entity\Formation;
use App\Entity\PlateformeAdmissionParametre;
use App\Enums\CampagneModuleEnum;
use App\Enums\TypeModificationDpeEnum;
use App\Repository\PlateformeAdmissionParametreRepository;
use App\Service\CampagneCollecteService;
use App\Service\ParcoursComparaisonService;
use App\Service\Validation\OffreValidationService;
use App\Utils\TurboStreamResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OffreConfigurerController extends BaseController
{
    use OffreAccessTrait;

    #[Route('/offre/{slug}/configurer', name: 'offre_v2_configurer')]
    public function configurer(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Formation                  $formation,
        ParcoursComparaisonService $comparaisonService,
        OffreValidationService     $offreValidationService,
        EntityManagerInterface     $em,
    ): Response
    {
        $this->denyAccessUnlessCanConfigurerOffre($formation);

        $campagne = $this->getCampagneCollecte();

        $dpeFormation = $em->getRepository(DpeFormation::class)->findOneBy([
            'formation' => $formation,
            'campagneCollecte' => $campagne,
        ]);

        if ($dpeFormation === null) {
            // Affichage seul : le DpeFormation est créé lors de la première transition (validation composante).
            $dpeFormation = new DpeFormation();
            $dpeFormation->setFormation($formation);
            $dpeFormation->setCampagneCollecte($campagne);
            $dpeFormation->setEtatValidation(['brouillon' => 1]);
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
                        'hasDefinitionChamps' => $plateforme->hasDefinitionChamps(),
                        'annees' => array_values($tpa->getAnnees() ?? []),
                        'anneesCapaciteRequise' => array_values($tpa->getAnneesCapaciteRequise() ?? []),
                        'anneesCapaciteSpecifique' => array_values($tpa->getAnneesCapaciteSpecifique() ?? []),
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
            'dpeFormation' => $dpeFormation,
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
        $this->denyAccessUnlessCanConfigurerOffre($formation);

        $csrfToken = (string)$request->request->get('_token');
        if (!$this->isCsrfTokenValid('offre_v2_configurer_' . $formation->getId(), $csrfToken)) {
            return new JsonResponse(['success' => false, 'message' => 'Votre session a expiré : rechargez la page puis recommencez.'], Response::HTTP_BAD_REQUEST);
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

            $dpeFormation = $em->getRepository(DpeFormation::class)->findOneBy([
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
        $modifiedAnneesByOrdre = [];

        // Paramètres de plateforme existants des années de la formation, en une requête : [anneeId][plateformeId]
        $annees = [];
        foreach ($formation->getParcours() as $p) {
            // Cette sauvegarde ne touche pas la maquette : inutile de recalculer le remplissage au flush().
            $p->suspendreRecalculRemplissage();
            foreach ($p->getAnnees() as $a) {
                $annees[] = $a;
            }
        }
        $parametresParAnnee = [];
        $parametresExistants = $annees === [] ? [] : $plateformeParamRepo->findBy(['annee' => $annees, 'campagne' => $campagne]);
        foreach ($parametresExistants as $param) {
            if ($param->getAnnee() !== null && $param->getPlateforme() !== null) {
                $parametresParAnnee[$param->getAnnee()->getId()][$param->getPlateforme()->getId()] = $param;
            }
        }

        // Gestion de la configuration du tronc commun
        if ($request->request->has('has_tronc_commun_config')) {
            $troncCommunChanged = false;
            foreach ($formation->getAnneesOrdres() as $anneeOrdre) {
                $isTc = $request->request->has('tronc_commun_annee_' . $anneeOrdre);
                if ($formation->isAnneeTroncCommun($anneeOrdre) !== $isTc) {
                    $formation->setAnneeTroncCommun($anneeOrdre, $isTc);
                    $troncCommunChanged = true;
                }
            }
            if ($troncCommunChanged) {
                $em->persist($formation);
                foreach ($formation->getParcours() as $p) {
                    foreach ($p->getAnnees() as $a) {
                        $changedYears[$a->getId()] = true;
                    }
                }
            }
        }

        foreach ($formation->getParcours() as $parcours) {
            $parcoursKey = 'parcours_' . $parcours->getId() . '_reconduction';
            $trackOpenClosedChanged = false;

            $dpeParcours = $parcours->getDpeParcoursPourCampagne($campagne);
            $oldEnumVal = $dpeParcours?->getEtatReconduction();
            $oldIsClosed = $oldEnumVal?->isFerme() ?? false;
            $isParcoursClosed = $oldIsClosed;

            // Le select ne propose que 3 choix : l'état n'est modifié que si l'utilisateur change de choix,
            // pour ne pas écraser un état plus précis (MODIFICATION_*, NON_OUVERTURE_CFVU…) à chaque autosave.
            $choixPossibles = [
                TypeModificationDpeEnum::OUVERT,
                TypeModificationDpeEnum::NON_OUVERTURE,
                TypeModificationDpeEnum::FERMETURE_DEFINITIVE,
            ];
            $enumVal = TypeModificationDpeEnum::tryFrom((string)$request->request->get($parcoursKey));
            $oldCategorie = $oldEnumVal?->getCategorieOuverture() ?? TypeModificationDpeEnum::OUVERT;
            if ($dpeParcours !== null && in_array($enumVal, $choixPossibles, true) && $enumVal !== $oldCategorie) {
                $dpeParcours->setEtatReconduction($enumVal);
                $isParcoursClosed = $enumVal->isFerme();
                $trackOpenClosedChanged = ($oldIsClosed !== $isParcoursClosed);
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
                    $modifiedAnneesByOrdre[$annee->getOrdre()] = $annee;
                }
                
                $oldCapacite = $annee->getCapaciteAccueil();
                if ($isParcoursClosed) {
                    $annee->setCapaciteAccueil(0);
                    if ($oldCapacite !== 0) {
                        $modifiedAnneesByOrdre[$annee->getOrdre()] = $annee;
                    }
                } elseif ($request->request->has($capaciteAccueilKey)) {
                    $newCap = (int)$request->request->get($capaciteAccueilKey);
                    $annee->setCapaciteAccueil($newCap);
                    if ($oldCapacite !== $newCap) {
                        $modifiedAnneesByOrdre[$annee->getOrdre()] = $annee;
                    }
                }

                $regimeKey = 'annee_' . $anneeId . '_regimeInscription';
                $oldRegimes = $annee->getRegimeInscriptionValues();
                if ($request->request->has($regimeKey)) {
                    $newRegimes = (array)$request->request->all($regimeKey);
                    $annee->setRegimeInscription($newRegimes);
                    if ($oldRegimes !== $annee->getRegimeInscriptionValues()) {
                        $modifiedAnneesByOrdre[$annee->getOrdre()] = $annee;
                    }
                } elseif ($request->request->has('annee_' . $anneeId . '_regime_present')) {
                    $annee->setRegimeInscription([]);
                    if ($oldRegimes !== []) {
                        $modifiedAnneesByOrdre[$annee->getOrdre()] = $annee;
                    }
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
                                $remarquesKey = 'annee_' . $anneeId . '_plateforme_' . $plateformeId . '_remarques';
                                
                                $parametre = $parametresParAnnee[$anneeId][$plateformeId] ?? null;

                                if (!$parametre) {
                                    $parametre = new PlateformeAdmissionParametre();
                                    $annee->addAdmissionPlateformeParametre($parametre);
                                    $parametre->setPlateforme($plateforme);
                                    $parametre->setCampagne($campagne);
                                    $parametresParAnnee[$anneeId][$plateformeId] = $parametre;
                                }
                                
                                $oldParamActive = $parametre->isActive();
                                $oldParamGlobale = $parametre->getCapaciteGlobale();
                                $oldParamFi = $parametre->getCapaciteFi();
                                $oldParamAlt = $parametre->getCapaciteAlternance();
                                $oldParamSpe = $parametre->getCapaciteSpecifique();
                                $oldParamRem = $parametre->getRemarques();

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

                                    if ($request->request->has($remarquesKey)) {
                                        $val = trim((string)$request->request->get($remarquesKey));
                                        $parametre->setRemarques($val !== '' ? $val : null);
                                    }
                                }

                                if ($oldParamActive !== $parametre->isActive()
                                    || $oldParamGlobale !== $parametre->getCapaciteGlobale()
                                    || $oldParamFi !== $parametre->getCapaciteFi()
                                    || $oldParamAlt !== $parametre->getCapaciteAlternance()
                                    || $oldParamSpe !== $parametre->getCapaciteSpecifique()
                                    || $oldParamRem !== $parametre->getRemarques()
                                ) {
                                    $modifiedAnneesByOrdre[$annee->getOrdre()] = $annee;
                                }
                                
                                $em->persist($parametre);
                            }
                        }
                    }
                }
            }
        }

        // Synchronisation des données des années en tronc commun entre tous les parcours
        foreach ($formation->getAnneesTroncCommun() as $tcOrdre) {
            $allAnneesWithOrdre = [];
            foreach ($formation->getParcours() as $p) {
                foreach ($p->getAnnees() as $a) {
                    if ($a->getOrdre() === $tcOrdre) {
                        $allAnneesWithOrdre[] = $a;
                    }
                }
            }

            if (count($allAnneesWithOrdre) <= 1) {
                continue;
            }

            // Trouver l'année source : l'année qui a été modifiée, sinon la première
            $sourceAnnee = $modifiedAnneesByOrdre[$tcOrdre] ?? $allAnneesWithOrdre[0];

            $sourceParams = $parametresParAnnee[$sourceAnnee->getId()] ?? [];

            foreach ($allAnneesWithOrdre as $targetAnnee) {
                if ($targetAnnee->getId() === $sourceAnnee->getId()) {
                    continue;
                }

                $changed = false;
                if ($targetAnnee->isOuvert() !== $sourceAnnee->isOuvert()) {
                    $targetAnnee->setIsOuvert($sourceAnnee->isOuvert());
                    $changed = true;
                }
                if ($targetAnnee->getCapaciteAccueil() !== $sourceAnnee->getCapaciteAccueil()) {
                    $targetAnnee->setCapaciteAccueil($sourceAnnee->getCapaciteAccueil());
                    $changed = true;
                }
                if ($targetAnnee->getRegimeInscriptionValues() !== $sourceAnnee->getRegimeInscriptionValues()) {
                    $targetAnnee->setRegimeInscription($sourceAnnee->getRegimeInscriptionValues());
                    $changed = true;
                }

                $typeDiplome = $formation->getTypeDiplome();
                if ($typeDiplome) {
                    foreach ($typeDiplome->getTypeDiplomePlateformeAdmissions() as $tpa) {
                        if ($tpa->getCampagne() === $campagne && $tpa->getPlateforme()?->getActive() && in_array($tcOrdre, $tpa->getAnnees(), true)) {
                            $plat = $tpa->getPlateforme();
                            $platId = $plat->getId();

                            $targetParam = $parametresParAnnee[$targetAnnee->getId()][$platId] ?? null;

                            $srcParam = $sourceParams[$platId] ?? null;
                            if ($srcParam) {
                                if (!$targetParam) {
                                    $targetParam = new PlateformeAdmissionParametre();
                                    $targetAnnee->addAdmissionPlateformeParametre($targetParam);
                                    $targetParam->setPlateforme($plat);
                                    $targetParam->setCampagne($campagne);
                                    $targetParam->setActive(false);
                                    $parametresParAnnee[$targetAnnee->getId()][$platId] = $targetParam;
                                }

                                if ($targetParam->isActive() !== $srcParam->isActive()
                                    || $targetParam->getCapaciteGlobale() !== $srcParam->getCapaciteGlobale()
                                    || $targetParam->getCapaciteFi() !== $srcParam->getCapaciteFi()
                                    || $targetParam->getCapaciteAlternance() !== $srcParam->getCapaciteAlternance()
                                    || $targetParam->getCapaciteSpecifique() !== $srcParam->getCapaciteSpecifique()
                                    || $targetParam->getRemarques() !== $srcParam->getRemarques()
                                ) {
                                    $targetParam->setActive((bool)$srcParam->isActive());
                                    $targetParam->setCapaciteGlobale($srcParam->getCapaciteGlobale());
                                    $targetParam->setCapaciteFi($srcParam->getCapaciteFi());
                                    $targetParam->setCapaciteAlternance($srcParam->getCapaciteAlternance());
                                    $targetParam->setCapaciteSpecifique($srcParam->getCapaciteSpecifique());
                                    $targetParam->setRemarques($srcParam->getRemarques());
                                    $changed = true;
                                }
                                $em->persist($targetParam);
                            } else {
                                if ($targetParam !== null) {
                                    if ($targetParam->isActive()) {
                                        $targetParam->setActive(false);
                                        $targetParam->setCapaciteGlobale(null);
                                        $targetParam->setCapaciteFi(null);
                                        $targetParam->setCapaciteAlternance(0);
                                        $targetParam->setCapaciteSpecifique(0);
                                        $targetParam->setRemarques(null);
                                        $changed = true;
                                    }
                                    $em->persist($targetParam);
                                }
                            }
                        }
                    }
                }

                $em->persist($targetAnnee);
                if ($changed) {
                    $changedYears[$targetAnnee->getId()] = true;
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
                            'hasDefinitionChamps' => $plateforme->hasDefinitionChamps(),
                            'annees' => array_values($tpa->getAnnees() ?? []),
                            'anneesCapaciteRequise' => array_values($tpa->getAnneesCapaciteRequise() ?? []),
                            'anneesCapaciteSpecifique' => array_values($tpa->getAnneesCapaciteSpecifique() ?? []),
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
        
        $anomalies = [];
        $tableau = [];

        foreach ($formation->getParcours() as $parcours) {
            $pId = $parcours->getId();
            $tableau[$pId] = $comparaisonService->construireTableauComparaison($parcours);

            $nbAnneesOuvertes = 0;
            $parcoursCapacite = 0;
            $nbPlateformesActives = 0;

            if ($parcours->isOuvertPourCampagne($campagne)) {
                $tabStatistiques['nbParcoursOuvert']++;

                $activePlateformes = [];
                foreach ($parcours->getAnnees() as $annee) {
                    if ($annee->isOuvert() === true) {
                        $nbAnneesOuvertes++;
                        $parcoursCapacite += $annee->getCapaciteAccueil();

                        foreach ($annee->getAdmissionPlateformeParametres() as $param) {
                            if ($param->getCampagne() === $campagne && $param->isActive()) {
                                $activePlateformes[$param->getPlateforme()?->getId()] = true;
                            }
                        }
                    }
                }

                $nbPlateformesActives = count($activePlateformes);
            }

            $anomaliesParcours = $offreValidationService->getAnomaliesParcours($parcours, $campagne);
            foreach ($anomaliesParcours as $message) {
                $anomalies[] = ['message' => $message];
            }

            $tabStatistiques['parcours'][$pId] = [
                'nbAnnees' => $parcours->getAnnees()->count(),
                'nbAnneesOuvertes' => $nbAnneesOuvertes,
                'capacite' => $parcoursCapacite,
                'nbPlateformesActives' => $nbPlateformesActives,
                'anomalies' => $anomaliesParcours,
            ];
        }

        $tabStatistiques['capacite'] = $formation->getCapacite();
        
        return [
            'tabStatistiques' => $tabStatistiques,
            'anomalies' => $anomalies,
            'comparaison' => $tableau
        ];
    }

    #[Route('/offrev2/configurer-dates', name: 'offre_v2_configurer_dates', methods: ['GET'])]
    public function configurerDates(
        TurboStreamResponseFactory $turboStream
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $campagne = $this->getCampagneCollecte();

        $timelineStep = $campagne->getTimelineDateForModule(CampagneModuleEnum::OFFRE_FORMATION);
        $dateDebut = $timelineStep?->getDateDebut() ?? $campagne->getDateOuvertureDpe();
        $dateFin = $timelineStep?->getDate() ?? $campagne->getDateClotureDpe();
        $heureFin = $timelineStep?->getHeure();
        $libelle = $timelineStep?->getLibelle() ?? 'Saisie de l\'offre de formation (ouverture, capacités)';
        $inTimeline = $timelineStep?->isInTimeline() ?? true;

        return $turboStream->streamOpenModalFromTemplates(
            'Dates de collecte — Offre de formation',
            'Campagne : ' . $campagne->getLibelle(),
            'offre_v2/_modal_configurer_dates.html.twig',
            [
                'campagne' => $campagne,
                'timelineStep' => $timelineStep,
                'dateDebut' => $dateDebut,
                'dateFin' => $dateFin,
                'heureFin' => $heureFin,
                'libelle' => $libelle,
                'inTimeline' => $inTimeline,
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
        $csrfToken = (string)$request->request->get('_token');
        if (!$this->isCsrfTokenValid('configurer_dates_' . $campagne->getId(), $csrfToken)) {
            return new JsonResponse(['success' => false, 'message' => 'Token CSRF invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $libelle = trim((string)$request->request->get('libelle', ''));
        $dateDebutStr = (string)$request->request->get('dateDebut');
        $dateFinStr = (string)$request->request->get('dateFin');
        $heureFinStr = (string)$request->request->get('heureFin');
        $inTimeline = $request->request->getBoolean('inTimeline', true);

        // Rétrocompatibilité si anciens champs transmis
        if ($dateDebutStr === '' && $request->request->has('dateOuvertureDpe')) {
            $dateDebutStr = (string)$request->request->get('dateOuvertureDpe');
        }
        if ($dateFinStr === '' && $request->request->has('dateClotureDpe')) {
            $dateFinStr = (string)$request->request->get('dateClotureDpe');
        }

        try {
            $dateDebut = $dateDebutStr !== '' ? new \DateTime($dateDebutStr) : null;
            $dateFin = $dateFinStr !== '' ? new \DateTime($dateFinStr) : null;
            $heureFin = $heureFinStr !== '' ? new \DateTime($heureFinStr) : null;
        } catch (\Exception) {
            return $turboStream->streamToastError('Date ou heure invalide.');
        }
        if ($dateDebut !== null && $dateFin !== null && $dateDebut > $dateFin) {
            return $turboStream->streamToastError('La date de début doit précéder la date de fin.');
        }

        $campagneService->updateModuleDates(
            $campagne,
            CampagneModuleEnum::OFFRE_FORMATION,
            $dateDebut,
            $dateFin,
            $heureFin,
            $libelle !== '' ? $libelle : null,
            $inTimeline
        );

        return $turboStream->streamToastSuccess('Dates de collecte de l\'offre enregistrées et synchronisées avec la timeline.', true);
    }
}
