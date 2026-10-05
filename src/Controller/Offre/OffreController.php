<?php

namespace App\Controller\Offre;

use App\Controller\BaseController;
use App\Entity\Composante;
use App\Entity\DpeFormation;
use App\Repository\AnneeRepository;
use App\Repository\ComposanteRepository;
use App\Repository\DocumentConseilRepository;
use App\Repository\DpeParcoursRepository;
use App\Repository\FormationRepository;
use App\Repository\ParcoursRepository;
use App\Repository\PlateformeAdmissionParametreRepository;
use App\Repository\PlateformeAdmissionRepository;
use App\Repository\TypeDiplomePlateformeAdmissionRepository;
use App\Repository\TypeDiplomeRepository;
use App\Service\Validation\OffreValidationService;
use App\Utils\TurboStreamResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Workflow\WorkflowInterface;

final class OffreController extends BaseController
{
    #[Route('/offrev2', name: 'app_offre_index')]
    #[Route('/offre/composante/{composante}', name: 'app_offre_composante_index')]
    public function index(
        Request                                  $request,
        FormationRepository                      $formationRepository,
        DpeParcoursRepository                    $dpeParcoursRepository,
        ComposanteRepository                     $composanteRepository,
        TypeDiplomeRepository                    $typeDiplomeRepository,
        PlateformeAdmissionRepository            $plateformeAdmissionRepository,
        PlateformeAdmissionParametreRepository   $plateformeParamRepository,
        TypeDiplomePlateformeAdmissionRepository $typeDiplomePlateformeAdmissionRepository,
        DocumentConseilRepository                $documentConseilRepository,
        AnneeRepository                          $anneeRepository,
        OffreValidationService                   $offreValidationService,
        EntityManagerInterface                   $em,
        WorkflowInterface                        $dpeFormationWorkflow,
        ?Composante                              $composante = null,
    ): Response {
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
            'nbAConfirmer' => 0,
            'nbAnomalies' => 0,
            'allAnomalies' => [],
        ];

        $campagne = $this->getCampagneCollecte();

        // 1. Récupérer les formations de la campagne avec relations préchargées
        $formations = $formationRepository->findByCampagneWithRelations($campagne, $composante);

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
                'isOuverte' => false,
            ];
        }

        // Récupérer les DpeParcours de la campagne (filtré par composante si renseignée)
        $allParcours = $dpeParcoursRepository->findByCampagneCollecte($campagne, $composante);

        // 2. Batch loading des années (1 requête pour tous les parcours au lieu de 400+)
        $anneesByParcours = $anneeRepository->findByCampagneIndexedByParcours($campagne, $composante);

        // 3. Batch loading des configurations et paramètres de plateformes (1 requête chacune au lieu de 1000+)
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
                    'isOuverte' => false,
                ];
            }

            $isParcoursOuvert = $dpePar->isOuvert();
            if ($isParcoursOuvert) {
                $tFormations[$idFormation]['isOuverte'] = true;
            }

            $parcoursAnnees = $anneesByParcours[$parcours->getId()] ?? [];

            // Calcul anomalies pour ce parcours en mémoire (0 requête)
            $parcAnoms = $offreValidationService->getAnomaliesParcours($parcours, $campagne, $dpePar, $paramsByAnnee, $parcoursAnnees, $tpaByTypeDiplome);
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

        // 4. Batch loading des DocumentConseil pour toutes les formations
        $formationIds = array_keys($tFormations);
        $docsByFormation = $documentConseilRepository->findIndexedByFormationIds($formationIds);

        // Batch loading des DpeFormation pour la gestion du workflow de validation des composantes
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

        // Calcul capacité de chaque formation et association des statuts documents
        foreach ($tFormations as $fId => &$row) {
            $formationCapacite = $row['formation']->getCapaciteAccueil();
            if ($formationCapacite <= 0) {
                // Calcul en mémoire depuis les données préchargées (0 requête supplémentaire)
                $tcAnneesCounted = [];
                $formationCapacite = 0;
                foreach ($row['parcoursData'] as $pd) {
                    if (!$pd['isOuvert']) {
                        continue;
                    }
                    foreach ($pd['annees'] as $annee) {
                        if (!$annee->isOuvert()) {
                            continue;
                        }
                        $ordre = $annee->getOrdre();
                        if ($ordre !== null && $row['formation']->isAnneeTroncCommun($ordre)) {
                            if (!isset($tcAnneesCounted[$ordre])) {
                                $formationCapacite += $annee->getCapaciteAccueil();
                                $tcAnneesCounted[$ordre] = true;
                            }
                        } else {
                            $formationCapacite += $annee->getCapaciteAccueil();
                        }
                    }
                }
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

        // Types de diplôme présents (liste du filtre sur la page d'une composante)
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
                
                // 5. Statut : état de l'offre de la formation (workflow dpeFormation) ou présence d'anomalies
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

        // Calculer les statistiques et agréger les anomalies et les groupes par composante
        $allAnomalies = [];
        $nbAConfirmer = 0;
        $nbParcoursOuvert = 0;
        $nbParcoursNonOuvert = 0;
        $nbNonOuverture = 0;
        $nbFermetureDefinitive = 0;
        $capacite = 0;
        $groupedFormations = [];

        foreach ($tFormations as $row) {
            $tabStatistiques['nbFormations']++;
            $tabStatistiques['nbParcours'] += count($row['dpeParcours']);
            
            $allAnomalies = array_merge($allAnomalies, $row['anomalies']);
            
            foreach ($row['dpeParcours'] as $dpePar) {
                $isOuvert = $dpePar->isOuvert();
                if ($isOuvert) {
                    $nbParcoursOuvert++;
                    
                    $state = array_key_first($dpePar->getEtatValidation()) ?? 'initialisation_dpe';
                    if (!in_array($state, ['valide_cfvu', 'valide_a_publier', 'publie'], true)) {
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

        // Tri par défaut :
        // 1. Composantes par ordre alphabétique
        // 2. Formations (dont le libellé est en lien avec la mention) par ordre alphabétique
        // 3. Parcours par libellé
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
        $isPeriodActive = $campagne->isPeriodActive();

        foreach ($groupedFormations as &$compGroup) {
            // Tri alphabétique des formations de la composante (selon display / mention)
            usort($compGroup['formations'], static function (array $a, array $b) use ($compareStr): int {
                $libA = (string)($a['formation']->getDisplay() ?? $a['formation']->getDisplayLong() ?? '');
                $libB = (string)($b['formation']->getDisplay() ?? $b['formation']->getDisplayLong() ?? '');
                return $compareStr($libA, $libB);
            });

            // Tri alphabétique des parcours au sein de chaque formation
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

            $compGroup['transitions'] = array_values($enabledTransitionsMap);
        }
        unset($compGroup);

        $tabStatistiques['nbParcoursOuvert'] = $nbParcoursOuvert;
        $tabStatistiques['nbParcoursNonOuvert'] = $nbParcoursNonOuvert;
        $tabStatistiques['nbNonOuverture'] = $nbNonOuverture;
        $tabStatistiques['nbFermetureDefinitive'] = $nbFermetureDefinitive;
        $tabStatistiques['capacite'] = $capacite;
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
        ];

        return $this->render('offre_v2/index.html.twig', $params);
    }

    /**
     * Lignes d'années des parcours demandés (?parcours=1,2,3), chargées à la demande depuis la liste de l'offre :
     * la page ne rend pas d'emblée les centaines de lignes d'années masquées.
     */
    #[Route('/offre/annees-parcours', name: 'offre_v2_annees_parcours', methods: ['GET'])]
    public function anneesParcours(
        Request                                  $request,
        ParcoursRepository                       $parcoursRepository,
        AnneeRepository                          $anneeRepository,
        DpeParcoursRepository                    $dpeParcoursRepository,
        TypeDiplomePlateformeAdmissionRepository $typeDiplomePlateformeAdmissionRepository,
        TurboStreamResponseFactory               $turboStream,
    ): Response {
        $ids = array_slice(array_values(array_unique(array_filter(
            array_map('intval', explode(',', (string)$request->query->get('parcours', '')))
        ))), 0, 100);
        $campagne = $this->getCampagneCollecte();

        // Mêmes droits que la liste : admin, ou gestion de la composante porteuse de la formation.
        $parcoursAutorises = [];
        $droitsParComposante = [];
        foreach ($ids === [] ? [] : $parcoursRepository->findBy(['id' => $ids]) as $parcours) {
            $composante = $parcours->getFormation()?->getComposantePorteuse();
            if ($composante === null) {
                continue;
            }
            $droitsParComposante[$composante->getId()] ??= $this->isGranted('ROLE_ADMIN') || $this->isGranted('MANAGE', [
                'route' => 'app_composante',
                'subject' => $composante,
            ]);
            if ($droitsParComposante[$composante->getId()]) {
                $parcoursAutorises[$parcours->getId()] = $parcours;
            }
        }

        $anneesParParcours = [];
        $dpeParParcours = [];
        if ($parcoursAutorises !== []) {
            $parcoursList = array_values($parcoursAutorises);
            foreach ($anneeRepository->findBy(['parcours' => $parcoursList], ['ordre' => 'ASC']) as $annee) {
                $anneesParParcours[$annee->getParcours()?->getId()][] = $annee;
            }
            foreach ($dpeParcoursRepository->findBy(['parcours' => $parcoursList, 'campagneCollecte' => $campagne]) as $dpeParcours) {
                $dpeParParcours[$dpeParcours->getParcours()?->getId()] ??= $dpeParcours;
            }
        }

        $lignes = [];
        foreach ($parcoursAutorises as $parcoursId => $parcours) {
            $lignes[] = [
                'parcours' => $parcours,
                'annees' => $anneesParParcours[$parcoursId] ?? [],
                'dpeParcours' => $dpeParParcours[$parcoursId] ?? null,
            ];
        }

        return $turboStream->stream('offre_v2/_annees_parcours.stream.html.twig', [
            'lignes' => $lignes,
            'tpaByTypeDiplome' => $typeDiplomePlateformeAdmissionRepository->findByCampagneIndexedByTypeDiplome($campagne),
        ]);
    }
}
