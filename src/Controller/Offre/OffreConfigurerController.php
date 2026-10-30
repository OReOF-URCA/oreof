<?php

namespace App\Controller\Offre;

use App\Controller\BaseController;
use App\Entity\CampagneCollecte;
use App\Entity\Constantes;
use App\Entity\DpeFormation;
use App\Entity\Formation;
use App\Entity\PlateformeAdmissionParametre;
use App\Entity\Portail;
use App\Entity\Annee;
use App\Entity\TypeDiplome;
use App\Navigation\Breadcrumb\Attribute\Breadcrumb as BreadcrumbAttribute;
use App\Navigation\Breadcrumb\Breadcrumb as BreadcrumbService;
use App\Enums\CampagneModuleEnum;
use App\Enums\TypeModificationDpeEnum;
use App\Repository\PlateformeAdmissionParametreRepository;
use App\Service\CampagneCollecteService;
use App\Service\Offre\RemplissageSuspension;
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
    #[BreadcrumbAttribute(menuKey: 'pilotage.gestion_offre')]
    public function configurer(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Formation                  $formation,
        ParcoursComparaisonService $comparaisonService,
        OffreValidationService     $offreValidationService,
        EntityManagerInterface     $em,
        BreadcrumbService          $breadcrumb,
    ): Response
    {
        $this->denyAccessUnlessCanConfigurerOffre($formation);

        if ($formation->getComposantePorteuse() !== null) {
            $composante = $formation->getComposantePorteuse();
            $breadcrumb->add(
                $composante->getLibelle() ?? 'Composante',
                'app_offre_composante_index',
                ['composante' => $composante->getId()]
            );
        }

        $breadcrumb->add($formation->getDisplayLong());

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

        $portailsCampagne = $em->getRepository(Portail::class)->findBy(['campagne' => $campagne], ['libelle' => 'ASC']);
        $formationsCampagneObjets = $em->getRepository(Formation::class)->findByCampagneCollecte($campagne);
        $formationsCampagneData = $this->getFormationsCampagneData($campagne, $em, $formation);

        return $this->render('offre_v2/configurer.html.twig', [
            'formation' => $formation,
            'plateformes' => $plateformes,
            'campagne' => $campagne,
            'tabStatistiques' => $statsData['tabStatistiques'],
            'anomalies' => $statsData['anomalies'],
            'comparaison' => $statsData['comparaison'],
            'dpeFormation' => $dpeFormation,
            'portailsCampagne' => $portailsCampagne,
            'formationsCampagneData' => $formationsCampagneData,
            'formationsCampagneObjets' => $formationsCampagneObjets,
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
        RemplissageSuspension                  $remplissageSuspension,
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
        $modifiedAnnees = [];
        $parametresCharges = [];

        // Paramètres de plateforme existants des années de la formation, en une requête : [anneeId][plateformeId]
        $annees = [];
        foreach ($formation->getParcours() as $p) {
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
            if ($request->request->has('troncs_communs')) {
                $rawTcs = $request->request->all('troncs_communs');
                $cleanedTcs = [];
                foreach ($rawTcs as $raw) {
                    if (!is_array($raw)) {
                        continue;
                    }
                    $tAnnees = array_values(array_unique(array_map('intval', $raw['annees'] ?? [])));
                    $tParcours = array_values(array_unique(array_map('intval', $raw['parcours'] ?? [])));
                    if (!empty($tAnnees) && !empty($tParcours)) {
                        $cleanedTcs[] = [
                            'id' => !empty($raw['id']) ? (string)$raw['id'] : 'tc_' . uniqid(),
                            'nom' => !empty($raw['nom']) ? (string)$raw['nom'] : 'Tronc commun ' . (count($cleanedTcs) + 1),
                            'annees' => $tAnnees,
                            'parcours' => $tParcours,
                        ];
                    }
                }
                $formation->setTroncsCommuns($cleanedTcs);
                $em->persist($formation);
                foreach ($formation->getParcours() as $p) {
                    foreach ($p->getAnnees() as $a) {
                        $changedYears[$a->getId()] = true;
                    }
                }
            } elseif ($request->request->has('troncs_communs_cleared')) {
                $formation->setTroncsCommuns([]);
                $em->persist($formation);
                foreach ($formation->getParcours() as $p) {
                    foreach ($p->getAnnees() as $a) {
                        $changedYears[$a->getId()] = true;
                    }
                }
            } else {
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
        }

        $activePortailEntities = [];

        // Un portail relie des formations : chaque année rattachée/détachée doit appartenir à une formation
        // que l'utilisateur a le droit de configurer.
        $droitsFormation = [];
        $peutModifierFormation = function (?Formation $f) use (&$droitsFormation): bool {
            if ($f === null) {
                return false;
            }

            return $droitsFormation[$f->getId()] ??= $this->canConfigurerOffre($f);
        };
        $peutModifierAnnee = fn (Annee $a): bool => $peutModifierFormation($a->getParcours()?->getFormation());

        // Gestion de la suppression de portails
        $portailsSupprimes = $request->request->all('portails_supprimes');
        foreach ($portailsSupprimes as $pId) {
            $pId = (int)$pId;
            if ($pId > 0) {
                $pEntity = $em->getRepository(Portail::class)->find($pId);
                if ($pEntity !== null && $pEntity->getCampagne() === $campagne) {
                    // Le portail n'est supprimé que si toutes ses années relèvent de formations modifiables
                    // par l'utilisateur ; sinon on détache seulement les années autorisées.
                    $suppressionComplete = true;
                    foreach ($pEntity->getAnnees()->toArray() as $a) {
                        if (!$peutModifierAnnee($a)) {
                            $suppressionComplete = false;
                            continue;
                        }
                        $a->setPortail(null);
                        $em->persist($a);
                        if ($a->getId() !== null) {
                            $changedYears[$a->getId()] = true;
                        }
                    }
                    if ($suppressionComplete) {
                        $em->remove($pEntity);
                    }
                }
            }
        }

        // Gestion de la configuration des portails (multi-formations, années et parcours)
        if ($request->request->has('has_portail_config')) {
            $portailsData = $request->request->all('portails');
            foreach ($portailsData as $portailKey => $pData) {
                if (!is_array($pData)) {
                    continue;
                }
                $libelle = trim((string)($pData['libelle'] ?? ''));
                if ($libelle === '') {
                    $libelle = 'Portail ' . $portailKey;
                }

                    $portail = null;
                    if (is_numeric($portailKey) && (int)$portailKey > 0) {
                        $portail = $em->getRepository(Portail::class)->find((int)$portailKey);
                        if ($portail !== null && $portail->getCampagne() !== $campagne) {
                            continue;
                        }
                    }
                    if ($portail === null) {
                        $portail = new Portail();
                        $portail->setCampagne($campagne);
                        $em->persist($portail);
                    }
                    $portail->setLibelle($libelle);
                    $activePortailEntities[] = $portail;

                    // Nouvelle structure directe : portails[id][annees][] = [anneeId, ...]
                    if (isset($pData['annees']) || isset($pData['formations_presentes'])) {
                        $selectedAnneeIds = array_map('intval', (array)($pData['annees'] ?? []));
                        $presentFormationIds = array_map('intval', (array)($pData['formations_presentes'] ?? []));
                        if ($formation->getId() !== null && !in_array($formation->getId(), $presentFormationIds, true)) {
                            $presentFormationIds[] = (int)$formation->getId();
                        }
                        // Formations déjà dans le portail mais non modifiables par l'utilisateur : conservées telles quelles.
                        $presentFormationIds = array_values(array_filter(
                            $presentFormationIds,
                            fn (int $fId): bool => $fId === $formation->getId()
                                || $peutModifierFormation($em->getRepository(Formation::class)->find($fId))
                                || in_array($fId, $portail->getFormationsIds(), true)
                        ));

                        $portail->setFormationsIds($presentFormationIds);

                        // 1. Rattachement des années cochées
                        foreach ($selectedAnneeIds as $anneeId) {
                            if ($anneeId <= 0) {
                                continue;
                            }
                            $annee = $em->getRepository(Annee::class)->find($anneeId);
                            if ($annee !== null && !$peutModifierAnnee($annee)) {
                                continue;
                            }
                            if ($annee !== null && $annee->getPortail() !== $portail) {
                                $annee->setPortail($portail);
                                $em->persist($annee);
                                $changedYears[$anneeId] = true;
                            }
                        }

                        // 2. Détachement des années décochées pour les formations présentes dans ce portail
                        foreach ($portail->getAnnees()->toArray() as $existingAnnee) {
                            $existingAnneeId = $existingAnnee->getId();
                            if ($existingAnneeId === null) {
                                continue;
                            }
                            if (!in_array($existingAnneeId, $selectedAnneeIds, true)) {
                                $anneeFormationId = $existingAnnee->getParcours()?->getFormation()?->getId();
                                if ($anneeFormationId !== null && in_array($anneeFormationId, $presentFormationIds, true) && $peutModifierAnnee($existingAnnee)) {
                                    $existingAnnee->setPortail(null);
                                    $em->persist($existingAnnee);
                                    $changedYears[$existingAnneeId] = true;
                                }
                            }
                        }
                    } else {
                        // Rétrocompatibilité avec l'ancienne structure par sous-tableaux
                        $formationsData = $pData['formations'] ?? [];
                        $selectedAnneeIdsInPortail = [];

                        if (is_array($formationsData)) {
                            foreach ($formationsData as $fId => $fConfig) {
                                $fId = (int)$fId;
                                if ($fId <= 0 || !is_array($fConfig)) {
                                    continue;
                                }
                                $targetFormation = $em->getRepository(Formation::class)->find($fId);
                                if ($targetFormation === null || !$peutModifierFormation($targetFormation)) {
                                    continue;
                                }

                                $selectedAnneesOrdres = array_map('intval', $fConfig['annees'] ?? []);
                                $selectedParcoursIds = array_map('intval', $fConfig['parcours'] ?? []);

                                if (!empty($selectedAnneesOrdres) && empty($selectedParcoursIds)) {
                                    foreach ($targetFormation->getParcours() as $tp) {
                                        if ($tp->getId() !== null) {
                                            $selectedParcoursIds[] = $tp->getId();
                                        }
                                    }
                                }
                                if (!empty($selectedParcoursIds) && empty($selectedAnneesOrdres)) {
                                    $selectedAnneesOrdres = [1];
                                }

                                foreach ($targetFormation->getParcours() as $parcours) {
                                    $pId = $parcours->getId();
                                    $isParcoursSelected = $pId !== null && in_array($pId, $selectedParcoursIds, true);

                                    foreach ($parcours->getAnnees() as $annee) {
                                        $ordre = $annee->getOrdre();
                                        $anneeId = $annee->getId();
                                        $isAnneeSelected = $ordre !== null && in_array($ordre, $selectedAnneesOrdres, true);

                                        if ($isParcoursSelected && $isAnneeSelected) {
                                            $selectedAnneeIdsInPortail[] = $anneeId;
                                            if ($annee->getPortail() !== $portail) {
                                                $annee->setPortail($portail);
                                                $em->persist($annee);
                                                if ($anneeId !== null) {
                                                    $changedYears[$anneeId] = true;
                                                }
                                            }
                                        } elseif ($annee->getPortail() === $portail) {
                                            $annee->setPortail(null);
                                            $em->persist($annee);
                                            if ($anneeId !== null) {
                                                $changedYears[$anneeId] = true;
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        foreach ($portail->getAnnees()->toArray() as $annee) {
                            $anneeId = $annee->getId();
                            if ($anneeId !== null && !in_array($anneeId, $selectedAnneeIdsInPortail, true)) {
                                $anneeFormationId = $annee->getParcours()?->getFormation()?->getId();
                                if ($anneeFormationId !== null && isset($formationsData[$anneeFormationId])) {
                                    $annee->setPortail(null);
                                    $em->persist($annee);
                                    $changedYears[$anneeId] = true;
                                }
                            }
                        }
                    }
                }
            }

            // Rétrocompatibilité : gestion de la création éventuelle d'un nouveau portail par libellé simple
            $newPortailLibelle = trim((string)$request->request->get('portail_nouveau_libelle'));
            if ($newPortailLibelle !== '') {
                $createdPortail = new Portail();
                $createdPortail->setLibelle($newPortailLibelle);
                $createdPortail->setCampagne($campagne);
                $em->persist($createdPortail);
                $activePortailEntities[] = $createdPortail;

                $newPortailAnnees = array_map('intval', $request->request->all('portail_nouveau_annees'));
                foreach ($formation->getParcours() as $p) {
                    foreach ($p->getAnnees() as $a) {
                        $aId = $a->getId();
                        if ($aId !== null && in_array($aId, $newPortailAnnees, true)) {
                            $a->setPortail($createdPortail);
                            $em->persist($a);
                            $changedYears[$aId] = true;
                        }
                    }
                }
            }

            // Rétrocompatibilité : portail_annees
            $portailAnneesRaw = $request->request->all('portail_annees');
            if (!empty($portailAnneesRaw)) {
                foreach ($formation->getParcours() as $p) {
                    foreach ($p->getAnnees() as $a) {
                        $aId = $a->getId();
                        if ($aId === null) {
                            continue;
                        }
                        foreach ($portailAnneesRaw as $pId => $anneeIdsList) {
                            if (is_array($anneeIdsList) && in_array((string)$aId, array_map('strval', $anneeIdsList), true)) {
                                $pEntity = $em->getRepository(Portail::class)->find((int)$pId);
                                if ($pEntity !== null && $pEntity->getCampagne() === $campagne) {
                                    $a->setPortail($pEntity);
                                    $em->persist($a);
                                    $changedYears[$aId] = true;
                                    $activePortailEntities[] = $pEntity;
                                }
                            }
                        }
                    }
                }
            }

        // Support d'un select unitaire annee_{anneeId}_portail (uniquement si le bloc de mutualisation n'est pas utilisé)
        if (!$request->request->has('has_portail_config')) {
            foreach ($formation->getParcours() as $p) {
                foreach ($p->getAnnees() as $a) {
                    $selectKey = 'annee_' . $a->getId() . '_portail';
                    if ($request->request->has($selectKey)) {
                        $val = (int)$request->request->get($selectKey);
                        if ($val > 0) {
                            $pEntity = $em->getRepository(Portail::class)->find($val);
                            if ($pEntity && $pEntity->getCampagne() === $campagne && $a->getPortail()?->getId() !== $val) {
                                $a->setPortail($pEntity);
                                $em->persist($a);
                                $changedYears[$a->getId()] = true;
                                $activePortailEntities[] = $pEntity;
                            }
                        } elseif ($a->getPortail() !== null) {
                            $a->setPortail(null);
                            $em->persist($a);
                            $changedYears[$a->getId()] = true;
                        }
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
                    $modifiedAnneesByOrdre[$annee->getOrdre()] = $modifiedAnnees[$annee->getId()] = $annee;
                }
                
                $oldCapacite = $annee->getCapaciteAccueil();
                if ($isParcoursClosed) {
                    $annee->setCapaciteAccueil(0);
                    if ($oldCapacite !== 0) {
                        $modifiedAnneesByOrdre[$annee->getOrdre()] = $modifiedAnnees[$annee->getId()] = $annee;
                    }
                } elseif ($request->request->has($capaciteAccueilKey)) {
                    $newCap = (int)$request->request->get($capaciteAccueilKey);
                    $annee->setCapaciteAccueil($newCap);
                    if ($oldCapacite !== $newCap) {
                        $modifiedAnneesByOrdre[$annee->getOrdre()] = $modifiedAnnees[$annee->getId()] = $annee;
                    }
                }

                // Case « pas de capacité d'accueil » : l'année est ouverte sans capacité à saisir
                if (!$isParcoursClosed && $request->request->has('annee_' . $anneeId . '_sansCapacite_present')) {
                    $hasCapacite = !$request->request->has('annee_' . $anneeId . '_sansCapacite');
                    if ($annee->hasCapacite() !== $hasCapacite) {
                        $annee->setHasCapacite($hasCapacite);
                        $changedYears[$anneeId] = true;
                        $modifiedAnneesByOrdre[$annee->getOrdre()] = $modifiedAnnees[$annee->getId()] = $annee;
                    }
                    if (!$hasCapacite && $annee->getCapaciteAccueil() !== 0) {
                        $annee->setCapaciteAccueil(0);
                        $modifiedAnneesByOrdre[$annee->getOrdre()] = $modifiedAnnees[$annee->getId()] = $annee;
                    }
                }

                $regimeKey = 'annee_' . $anneeId . '_regimeInscription';
                $oldRegimes = $annee->getRegimeInscriptionValues();
                if ($request->request->has($regimeKey)) {
                    $newRegimes = (array)$request->request->all($regimeKey);
                    $annee->setRegimeInscription($newRegimes);
                    if ($oldRegimes !== $annee->getRegimeInscriptionValues()) {
                        $modifiedAnneesByOrdre[$annee->getOrdre()] = $modifiedAnnees[$annee->getId()] = $annee;
                    }
                } elseif ($request->request->has('annee_' . $anneeId . '_regime_present')) {
                    $annee->setRegimeInscription([]);
                    if ($oldRegimes !== []) {
                        $modifiedAnneesByOrdre[$annee->getOrdre()] = $modifiedAnnees[$annee->getId()] = $annee;
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
                                    $modifiedAnneesByOrdre[$annee->getOrdre()] = $modifiedAnnees[$annee->getId()] = $annee;
                                }
                                
                                $em->persist($parametre);
                            }
                        }
                    }
                }
            }
        }

        // Synchronisation des données des années en tronc commun
        foreach ($formation->getTroncsCommuns() as $tc) {
            $tcParcoursIds = $tc['parcours'] ?? [];
            foreach ($tc['annees'] ?? [] as $tcOrdre) {
                $anneesInGroup = [];
                foreach ($formation->getParcours() as $p) {
                    if (!in_array($p->getId(), $tcParcoursIds, true)) {
                        continue;
                    }
                    foreach ($p->getAnnees() as $a) {
                        if ($a->getOrdre() === $tcOrdre) {
                            $anneesInGroup[] = $a;
                        }
                    }
                }

                if (count($anneesInGroup) <= 1) {
                    continue;
                }

                // Trouver l'année source : modifiée expressément dans la requête ou possédant déjà des capacités
                $sourceAnnee = null;
                foreach ($anneesInGroup as $a) {
                    if (isset($modifiedAnneesByOrdre[$a->getOrdre()]) && $a->getId() !== null && isset($changedYears[$a->getId()])) {
                        $sourceAnnee = $a;
                        break;
                    }
                }
                if ($sourceAnnee === null) {
                    foreach ($anneesInGroup as $a) {
                        if ($a->getCapaciteAccueil() !== null && $a->getCapaciteAccueil() > 0) {
                            $sourceAnnee = $a;
                            break;
                        }
                    }
                }
                $sourceAnnee ??= ($modifiedAnneesByOrdre[$tcOrdre] ?? $anneesInGroup[0]);

                foreach ($anneesInGroup as $targetAnnee) {
                    if ($targetAnnee->getId() === $sourceAnnee->getId()) {
                        continue;
                    }
                    if ($this->synchroniserDonneesAnnee($sourceAnnee, $targetAnnee, $campagne, $parametresParAnnee, $formation->getTypeDiplome(), $em)) {
                        $changedYears[$targetAnnee->getId()] = true;
                        $modifiedAnnees[$targetAnnee->getId()] = $targetAnnee;
                    }
                }
            }
        }

        // Les portails créés dans cette requête n'ont pas encore d'identifiant : on les enregistre avant de synchroniser.
        $remplissageSuspension->suspendre();
        $em->flush();

        // Synchronisation des données des années en portail
        $portailsSynchronises = [];
        $portailsListToSync = $activePortailEntities;
        foreach ($formation->getParcours() as $p) {
            foreach ($p->getAnnees() as $a) {
                $portail = $a->getPortail();
                if ($portail !== null && !in_array($portail, $portailsListToSync, true)) {
                    $portailsListToSync[] = $portail;
                }
            }
        }

        foreach ($portailsListToSync as $portail) {
            if ($portail->getId() === null || isset($portailsSynchronises[$portail->getId()])) {
                continue;
            }
            $portailsSynchronises[$portail->getId()] = true;
            $membres = $portail->getAnnees();
            if ($membres->count() <= 1) {
                continue;
            }

            // Les années d'autres formations n'ont pas leurs paramètres de plateforme chargés : sans cela,
            // la synchronisation recréerait des doublons.
            $this->chargerParametresPlateforme($membres->toArray(), $campagne, $plateformeParamRepo, $parametresParAnnee, $parametresCharges);

            // Trouver l'année source pour la synchronisation du portail :
            // 1. Priorité aux années dont les capacités/plateformes ont été modifiées dans cette requête
            // (directement, ou par propagation du tronc commun)
            $sourceAnnee = null;
            foreach ($membres as $ma) {
                if ($ma->getId() !== null && isset($modifiedAnnees[$ma->getId()]) && $ma->getParcours()?->getFormation()?->getId() === $formation->getId()) {
                    $sourceAnnee = $ma;
                    break;
                }
            }

            // 2. Si aucune capacité n'a été expressément modifiée (ex: ajout/rattachement a posteriori d'une nouvelle formation au portail),
            // chercher un membre existant qui possède déjà des capacités ou des plateformes configurées pour que la nouvelle formation en hérite !
            if ($sourceAnnee === null) {
                foreach ($membres as $ma) {
                    $hasCap = ($ma->getCapaciteAccueil() !== null && $ma->getCapaciteAccueil() > 0);
                    $hasPlat = false;
                    $params = $parametresParAnnee[$ma->getId()] ?? [];
                    foreach ($params as $pParam) {
                        if ($pParam->isActive()) {
                            $hasPlat = true;
                            break;
                        }
                    }
                    if ($hasCap || $hasPlat) {
                        $sourceAnnee = $ma;
                        break;
                    }
                }
            }

            // 3. Fallback : année dans changedYears ou premier membre disponible
            if ($sourceAnnee === null) {
                foreach ($membres as $ma) {
                    if ($ma->getId() !== null && isset($changedYears[$ma->getId()])) {
                        $sourceAnnee = $ma;
                        break;
                    }
                }
            }
            $sourceAnnee ??= $membres->first();
            if ($sourceAnnee === false || $sourceAnnee === null) {
                continue;
            }

            foreach ($membres as $targetAnnee) {
                if ($targetAnnee->getId() === $sourceAnnee->getId()) {
                    continue;
                }
                $tDiplome = $targetAnnee->getParcours()?->getFormation()?->getTypeDiplome();
                if ($this->synchroniserDonneesAnnee($sourceAnnee, $targetAnnee, $campagne, $parametresParAnnee, $tDiplome, $em)) {
                    if ($targetAnnee->getParcours()?->getFormation()?->getId() === $formation->getId()) {
                        $targetId = $targetAnnee->getId();
                        if ($targetId !== null) {
                            $changedYears[$targetId] = true;
                        }
                    }
                    // Les années en tronc commun avec la cible (dans sa propre formation) suivent la cible.
                    $this->propagerTroncCommun($targetAnnee, $formation, $campagne, $plateformeParamRepo, $parametresParAnnee, $parametresCharges, $changedYears, $em);
                }
            }
        }

        $remplissageSuspension->suspendre();
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

            $portailsCampagne = $em->getRepository(Portail::class)->findBy(['campagne' => $campagne], ['libelle' => 'ASC']);
            $formationsCampagneObjets = $em->getRepository(Formation::class)->findByCampagneCollecte($campagne);
            $formationsCampagneData = $this->getFormationsCampagneData($campagne, $em, $formation);
            $activeMutualisationTab = (string)$request->request->get('active_mutualisation_tab', 'troncs-communs');

            $response = $this->render('offre_v2/_configurer_streams.html.twig', [
                'formation' => $formation,
                'plateformes' => $plateformes,
                'campagne' => $campagne,
                'tabStatistiques' => $statsData['tabStatistiques'],
                'anomalies' => $statsData['anomalies'],
                'comparaison' => $statsData['comparaison'],
                'changedYears' => $changedYears,
                'portailsCampagne' => $portailsCampagne,
                'formationsCampagneData' => $formationsCampagneData,
                'formationsCampagneObjets' => $formationsCampagneObjets,
                'active_mutualisation_tab' => $activeMutualisationTab,
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

    /**
     * Charge les paramètres de plateforme des années données (une requête) sans doublonner ceux déjà connus.
     *
     * @param list<Annee> $annees
     * @param array<int, array<int, PlateformeAdmissionParametre>> $parametresParAnnee
     * @param array<int, true> $parametresCharges
     */
    private function chargerParametresPlateforme(
        array $annees,
        CampagneCollecte $campagne,
        PlateformeAdmissionParametreRepository $plateformeParamRepo,
        array &$parametresParAnnee,
        array &$parametresCharges
    ): void {
        $aCharger = [];
        foreach ($annees as $annee) {
            $id = $annee->getId();
            if ($id !== null && !isset($parametresCharges[$id]) && !isset($parametresParAnnee[$id])) {
                $aCharger[] = $annee;
            }
            if ($id !== null) {
                $parametresCharges[$id] = true;
            }
        }
        if ($aCharger === []) {
            return;
        }
        foreach ($plateformeParamRepo->findBy(['annee' => $aCharger, 'campagne' => $campagne]) as $param) {
            if ($param->getAnnee() !== null && $param->getPlateforme() !== null) {
                $parametresParAnnee[$param->getAnnee()->getId()][$param->getPlateforme()->getId()] = $param;
            }
        }
    }

    /**
     * Recopie les données de $source sur les autres années du tronc commun qui la contient (dans sa formation).
     *
     * @param array<int, array<int, PlateformeAdmissionParametre>> $parametresParAnnee
     * @param array<int, true> $parametresCharges
     * @param array<int, true> $changedYears
     */
    private function propagerTroncCommun(
        Annee $source,
        Formation $formationCourante,
        CampagneCollecte $campagne,
        PlateformeAdmissionParametreRepository $plateformeParamRepo,
        array &$parametresParAnnee,
        array &$parametresCharges,
        array &$changedYears,
        EntityManagerInterface $em
    ): void {
        $parcoursSource = $source->getParcours();
        $formationSource = $parcoursSource?->getFormation();
        if ($parcoursSource === null || $formationSource === null) {
            return;
        }

        foreach ($formationSource->getTroncsCommuns() as $tc) {
            $tcParcours = $tc['parcours'] ?? [];
            if (!in_array($parcoursSource->getId(), $tcParcours, true) || !in_array($source->getOrdre(), $tc['annees'] ?? [], true)) {
                continue;
            }

            $groupe = [];
            foreach ($formationSource->getParcours() as $p) {
                if (!in_array($p->getId(), $tcParcours, true)) {
                    continue;
                }
                foreach ($p->getAnnees() as $a) {
                    if ($a->getOrdre() === $source->getOrdre() && $a->getId() !== $source->getId()) {
                        $groupe[] = $a;
                    }
                }
            }
            $this->chargerParametresPlateforme($groupe, $campagne, $plateformeParamRepo, $parametresParAnnee, $parametresCharges);

            foreach ($groupe as $cible) {
                if ($this->synchroniserDonneesAnnee($source, $cible, $campagne, $parametresParAnnee, $formationSource->getTypeDiplome(), $em)
                    && $formationSource->getId() === $formationCourante->getId()
                    && $cible->getId() !== null) {
                    $changedYears[$cible->getId()] = true;
                }
            }
        }
    }

    private function synchroniserDonneesAnnee(
        Annee $sourceAnnee,
        Annee $targetAnnee,
        CampagneCollecte $campagne,
        array &$parametresParAnnee,
        ?TypeDiplome $typeDiplome,
        EntityManagerInterface $em
    ): bool {
        $changed = false;
        if ($targetAnnee->isOuvert() !== $sourceAnnee->isOuvert()) {
            $targetAnnee->setIsOuvert($sourceAnnee->isOuvert());
            $changed = true;
        }
        if ($targetAnnee->getCapaciteAccueil() !== $sourceAnnee->getCapaciteAccueil()) {
            $targetAnnee->setCapaciteAccueil($sourceAnnee->getCapaciteAccueil());
            $changed = true;
        }
        if ($targetAnnee->hasCapacite() !== $sourceAnnee->hasCapacite()) {
            $targetAnnee->setHasCapacite((bool) $sourceAnnee->hasCapacite());
            $changed = true;
        }
        if ($targetAnnee->getRegimeInscriptionValues() !== $sourceAnnee->getRegimeInscriptionValues()) {
            $targetAnnee->setRegimeInscription($sourceAnnee->getRegimeInscriptionValues());
            $changed = true;
        }

        $sourceParams = $parametresParAnnee[$sourceAnnee->getId()] ?? [];
        if ($typeDiplome) {
            foreach ($typeDiplome->getTypeDiplomePlateformeAdmissions() as $tpa) {
                if ($tpa->getCampagne() === $campagne && $tpa->getPlateforme()?->getActive() && in_array($targetAnnee->getOrdre(), $tpa->getAnnees(), true)) {
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

        return $changed;
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

    /**
     * @return list<array{
     *     id: int,
     *     libelle: string,
     *     composante: string,
     *     typeDiplome: string,
     *     annees: list<int>,
     *     parcours: list<array{id: int, libelle: string}>
     * }>
     */
    private function getFormationsCampagneData(CampagneCollecte $campagne, EntityManagerInterface $em, Formation $currentFormation): array
    {
        $formations = $em->getRepository(Formation::class)->findByCampagneCollecte($campagne);
        $allFormations = array_merge([$currentFormation], $formations);
        $data = [];
        foreach ($allFormations as $f) {
            $fId = $f->getId();
            if ($fId === null || isset($data[$fId])) {
                continue;
            }
            $parcoursList = [];
            foreach ($f->getParcours() as $p) {
                $pId = $p->getId();
                if ($pId !== null) {
                    $anneesList = [];
                    foreach ($p->getAnnees() as $a) {
                        $aId = $a->getId();
                        $aOrdre = $a->getOrdre();
                        if ($aId !== null && $aOrdre !== null) {
                            $anneesList[] = [
                                'id' => $aId,
                                'ordre' => $aOrdre,
                            ];
                        }
                    }
                    $typeP = $p->getTypeParcours();
                    $isClassique = ($typeP === null || $typeP === \App\Enums\TypeParcoursEnum::TYPE_PARCOURS_CLASSIQUE);
                    $parcoursList[] = [
                        'id' => $pId,
                        'libelle' => $p->getLibelle() ?? 'Parcours sans titre',
                        'typeParcoursLibelle' => !$isClassique ? $typeP->getLabel() : null,
                        'typeParcoursColor' => $typeP?->getColor() ?? 'secondary',
                        'annees' => $anneesList,
                    ];
                }
            }
            $composante = $f->getComposantePorteuse();
            $composanteLib = $composante?->getLibelle() ?? 'Sans composante';
            $typeDiplome = $f->getTypeDiplome();
            $typeDiplomeLib = $typeDiplome?->getLibelleCourt() ?? 'Diplôme';

            $data[$fId] = [
                'id' => $fId,
                'libelle' => $f->getDisplayLong(),
                'composante' => $composanteLib,
                'typeDiplome' => $typeDiplomeLib,
                'annees' => array_values($f->getAnneesOrdres()),
                'parcours' => $parcoursList,
            ];
        }

        return array_values($data);
    }
}
