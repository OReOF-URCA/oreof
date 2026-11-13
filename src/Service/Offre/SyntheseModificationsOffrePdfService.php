<?php

declare(strict_types=1);

namespace App\Service\Offre;

use App\Classes\MyGotenbergPdf;
use App\Entity\CampagneCollecte;
use App\Entity\Composante;
use App\Entity\DpeDemande;
use App\Entity\DpeParcours;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Enums\TypeModificationDpeEnum;
use App\Referentiel\RegimeInscription;
use App\Repository\ComposanteRepository;
use App\Repository\DpeDemandeRepository;
use App\Repository\DpeParcoursRepository;
use App\Utils\Tools;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

final class SyntheseModificationsOffrePdfService
{
    public function __construct(
        private readonly DpeParcoursRepository  $dpeParcoursRepository,
        private readonly DpeDemandeRepository   $dpeDemandeRepository,
        private readonly ComposanteRepository   $composanteRepository,
        private readonly MyGotenbergPdf         $myGotenbergPdf,
        private readonly EntityManagerInterface $entityManager,
    )
    {
    }

    /**
     * Génère la réponse HTTP contenant le fichier PDF streamé.
     */
    public function generatePdfResponse(CampagneCollecte $campagne): Response
    {
        $reportData = $this->collectModifications($campagne);

        $context = [
            'titre' => 'Synthèse des modifications de l\'offre',
            'campagne' => $campagne,
            'dateGeneration' => new \DateTime(),
            'stats' => $reportData['stats'],
            'composantes' => $reportData['composantes'],
            'allComposantesStats' => $reportData['allComposantesStats'],
        ];

        $cleanCampagneName = Tools::FileName($campagne->getLibelle() ?: 'campagne-' . $campagne->getAnnee());
        $filename = sprintf('synthese_modifications_offre_%s.pdf', $cleanCampagneName);

        return $this->myGotenbergPdf->render(
            'pdf/offre_synthese_modifications.html.twig',
            $context,
            $filename,
            [
                'attachment' => true,
                'landscape' => false,
                'paperSize' => 'A4',
            ]
        );
    }

    /**
     * Analyse l'offre de la campagne pour détecter toutes les créations et modifications
     * sur les libellés de formation/parcours et sur les régimes d'inscription,
     * groupées par composante.
     *
     * @return array{
     *     stats: array{
     *         total_composantes_avec_modifs: int,
     *         total_formations_creees: int,
     *         total_formations_modifiees: int,
     *         total_parcours_crees: int,
     *         total_parcours_modifies: int,
     *         total_evolutions: int
     *     },
     *     allComposantesStats: list<array{
     *         composante: Composante|null,
     *         libelle: string,
     *         formations_creees: int,
     *         formations_modifiees: int,
     *         parcours_crees: int,
     *         parcours_modifies: int,
     *         total: int
     *     }>,
     *     composantes: list<array{
     *         composante: Composante|null,
     *         libelle: string,
     *         total_creations: int,
     *         total_modifications: int,
     *         total_items: int,
     *         formations: list<array{
     *             formation: Formation,
     *             is_creation: bool,
     *             creation_details: ?array<string, mixed>,
     *             modifications: list<array<string, mixed>>,
     *             parcours: list<array{
     *                 parcours: Parcours,
     *                 is_creation: bool,
     *                 creation_details: ?array<string, mixed>,
     *                 modifications: list<array<string, mixed>>,
     *                 demande_justification: ?string
     *             }>
     *         }>
     *     }>
     * }
     */
    public function collectModifications(CampagneCollecte $campagne): array
    {
        // 1. Récupérer tous les DpeParcours de la campagne
        $allDpeParcours = $this->dpeParcoursRepository->findBy(['campagneCollecte' => $campagne]);

        // 2. Indexer les demandes DPE éventuelles de la campagne pour afficher les argumentaires
        $allDemandes = $this->dpeDemandeRepository->findBy(['campagneCollecte' => $campagne]);
        $demandesByParcours = [];
        $demandesByFormation = [];
        foreach ($allDemandes as $demande) {
            if ($demande->getParcours() !== null) {
                $demandesByParcours[$demande->getParcours()->getId()] = $demande;
            } elseif ($demande->getFormation() !== null) {
                $demandesByFormation[$demande->getFormation()->getId()] = $demande;
            }
        }

        // 3. Regrouper les parcours par formation
        /** @var array<int, array{formation: Formation, dpeParcoursList: list<DpeParcours>}> $formationsMap */
        $formationsMap = [];
        foreach ($allDpeParcours as $dp) {
            $p = $dp->getParcours();
            if ($p === null) {
                continue;
            }
            $f = $p->getFormation();
            if ($f === null) {
                continue;
            }
            $fId = $f->getId();
            if ($fId === null) {
                continue;
            }
            if (!isset($formationsMap[$fId])) {
                $formationsMap[$fId] = [
                    'formation' => $f,
                    'dpeParcoursList' => [],
                ];
            }
            $formationsMap[$fId]['dpeParcoursList'][] = $dp;
        }

        // 4. Analyser les composantes et les évolutions
        $composantesMap = [];
        $allComposantesList = $this->composanteRepository->findBy([], ['libelle' => 'ASC']);
        $statsPerComposante = [];
        foreach ($allComposantesList as $comp) {
            $statsPerComposante[$comp->getId() ?? 0] = [
                'composante' => $comp,
                'libelle' => $comp->getLibelle() ?? 'Composante #' . $comp->getId(),
                'formations_creees' => 0,
                'formations_modifiees' => 0,
                'parcours_crees' => 0,
                'parcours_modifies' => 0,
                'total' => 0,
            ];
        }
        $statsPerComposante[0] = [
            'composante' => null,
            'libelle' => 'Sans composante',
            'formations_creees' => 0,
            'formations_modifiees' => 0,
            'parcours_crees' => 0,
            'parcours_modifies' => 0,
            'total' => 0,
        ];

        $globalFormationsCreees = 0;
        $globalFormationsModifiees = 0;
        $globalParcoursCrees = 0;
        $globalParcoursModifies = 0;

        foreach ($formationsMap as $entry) {
            $formation = $entry['formation'];
            $dpeList = $entry['dpeParcoursList'];
            $composante = $formation->getComposantePorteuse();
            $compId = $composante?->getId() ?? 0;
            $compLibelle = $composante?->getLibelle() ?? 'Sans composante';

            $formationOrigine = $formation->getFormationOrigineCopie();
            $isFormationCreation = ($formationOrigine === null);

            $formationCreationDetails = null;
            $formationModifications = [];

            if (!isset($statsPerComposante[$compId])) {
                $statsPerComposante[$compId] = [
                    'composante' => $composante,
                    'libelle' => $compLibelle,
                    'formations_creees' => 0,
                    'formations_modifiees' => 0,
                    'parcours_crees' => 0,
                    'parcours_modifies' => 0,
                    'total' => 0,
                ];
            }

            if ($isFormationCreation) {
                $globalFormationsCreees++;
                $statsPerComposante[$compId]['formations_creees']++;
                $statsPerComposante[$compId]['total']++;

                $formationCreationDetails = [
                    'libelle' => $formation->getDisplayLong(),
                    'typeDiplome' => $formation->getTypeDiplome()?->getLibelle(),
                    'mention' => $formation->getDisplay(false),
                    'regimes' => $this->normalizeRegimes($formation->getRegimeInscriptionValues()),
                    'sigle' => $formation->getSigle(),
                    'justification' => ($demandesByFormation[$formation->getId()] ?? null)?->getArgumentaireDemande(),
                ];
            } else {
                // Vérifier modification de libellé de formation
                $oldLibelle = $formationOrigine->getDisplay(false);
                $newLibelle = $formation->getDisplay(false);
                $oldSigle = $formationOrigine->getSigle();
                $newSigle = $formation->getSigle();

                $libelleChanged = (trim((string)$oldLibelle) !== trim((string)$newLibelle))
                    || (trim((string)$oldSigle) !== trim((string)$newSigle));

                if ($libelleChanged) {
                    $formationModifications[] = [
                        'type' => 'LIBELLE',
                        'label' => 'Modification de l\'intitulé de mention / formation',
                        'ancien' => $formationOrigine->getDisplayLong(),
                        'nouveau' => $formation->getDisplayLong(),
                        'justification' => ($demandesByFormation[$formation->getId()] ?? null)?->getArgumentaireDemande(),
                    ];
                }

                // Vérifier modification des régimes d'inscription de formation
                $oldRegimes = $this->normalizeRegimes($formationOrigine->getRegimeInscriptionValues());
                $newRegimes = $this->normalizeRegimes($formation->getRegimeInscriptionValues());

                if ($oldRegimes !== $newRegimes) {
                    $formationModifications[] = [
                        'type' => 'REGIMES',
                        'label' => 'Modification des régimes d\'inscription de la formation',
                        'anciens' => $oldRegimes,
                        'nouveaux' => $newRegimes,
                        'ajouts' => array_values(array_diff($newRegimes, $oldRegimes)),
                        'suppressions' => array_values(array_diff($oldRegimes, $newRegimes)),
                        'justification' => ($demandesByFormation[$formation->getId()] ?? null)?->getArgumentaireDemande(),
                    ];
                }

                if (!empty($formationModifications)) {
                    $globalFormationsModifiees++;
                    $statsPerComposante[$compId]['formations_modifiees']++;
                    $statsPerComposante[$compId]['total']++;
                }
            }

            // Analyser les parcours de cette formation
            $parcoursItems = [];
            foreach ($dpeList as $dp) {
                $parcours = $dp->getParcours();
                if ($parcours === null) {
                    continue;
                }

                $parcoursOrigine = $parcours->getParcoursOrigineCopie() ?? $parcours->getParcoursOrigine();
                $isParcoursCreation = $isFormationCreation
                    || ($dp->getEtatReconduction() === TypeModificationDpeEnum::CREATION)
                    || ($parcoursOrigine === null);

                $parcoursCreationDetails = null;
                $parcoursModifications = [];
                $demandeJustification = ($demandesByParcours[$parcours->getId()] ?? null)?->getArgumentaireDemande();

                if ($isParcoursCreation) {
                    $globalParcoursCrees++;
                    $statsPerComposante[$compId]['parcours_crees']++;
                    $statsPerComposante[$compId]['total']++;

                    // Récupérer régimes globaux et éventuelles spécialisations par année
                    $anneesDetails = [];
                    foreach ($parcours->getAnnees() as $annee) {
                        $aRegs = $this->normalizeRegimes($annee->getRegimeInscriptionValues());
                        if (!empty($aRegs)) {
                            $anneesDetails[] = [
                                'ordre' => $annee->getOrdre(),
                                'regimes' => $aRegs,
                            ];
                        }
                    }

                    $parcoursCreationDetails = [
                        'libelle' => $parcours->getLibelle() ?: $parcours->getDisplay(),
                        'regimes' => $this->normalizeRegimes($parcours->getRegimeInscriptionValues()),
                        'annees' => $anneesDetails,
                        'justification' => $demandeJustification,
                    ];
                } else {
                    // Modification d'intitulé de parcours
                    $oldPLib = $parcoursOrigine?->getLibelle();
                    $newPLib = $parcours->getLibelle();
                    $isModifIntitule = ($dp->getEtatReconduction() === TypeModificationDpeEnum::MODIFICATION_INTITULE);

                    if ($isModifIntitule || ($oldPLib !== null && trim($oldPLib) !== trim((string)$newPLib))) {
                        $parcoursModifications[] = [
                            'type' => 'LIBELLE',
                            'label' => 'Modification de l\'intitulé du parcours',
                            'ancien' => $oldPLib ?: 'Sans libellé',
                            'nouveau' => $newPLib ?: 'Sans libellé',
                            'justification' => $demandeJustification,
                        ];
                    }

                    // Modification des régimes d'inscription du parcours
                    $oldPRegimes = $this->normalizeRegimes($parcoursOrigine?->getRegimeInscriptionValues() ?? []);
                    $newPRegimes = $this->normalizeRegimes($parcours->getRegimeInscriptionValues());

                    $regimesParcoursChanged = ($oldPRegimes !== $newPRegimes);

                    // Vérifier aussi les modifications par année
                    $anneesModifs = [];
                    foreach ($parcours->getAnnees() as $annee) {
                        $anneeOrigine = $annee->getAnneeOrigineCopie();
                        if ($anneeOrigine !== null) {
                            $oldAReg = $this->normalizeRegimes($anneeOrigine->getRegimeInscriptionValues());
                            $newAReg = $this->normalizeRegimes($annee->getRegimeInscriptionValues());
                            if ($oldAReg !== $newAReg) {
                                $anneesModifs[] = [
                                    'ordre' => $annee->getOrdre(),
                                    'anciens' => $oldAReg,
                                    'nouveaux' => $newAReg,
                                    'ajouts' => array_values(array_diff($newAReg, $oldAReg)),
                                    'suppressions' => array_values(array_diff($oldAReg, $newAReg)),
                                ];
                            }
                        }
                    }

                    if ($regimesParcoursChanged || !empty($anneesModifs)) {
                        $parcoursModifications[] = [
                            'type' => 'REGIMES',
                            'label' => 'Modification des régimes d\'inscription du parcours',
                            'anciens' => $oldPRegimes,
                            'nouveaux' => $newPRegimes,
                            'ajouts' => array_values(array_diff($newPRegimes, $oldPRegimes)),
                            'suppressions' => array_values(array_diff($oldPRegimes, $newPRegimes)),
                            'annees' => $anneesModifs,
                            'justification' => $demandeJustification,
                        ];
                    }

                    if (!empty($parcoursModifications)) {
                        $globalParcoursModifies++;
                        $statsPerComposante[$compId]['parcours_modifies']++;
                        $statsPerComposante[$compId]['total']++;
                    }
                }

                if ($isParcoursCreation || !empty($parcoursModifications)) {
                    $parcoursItems[] = [
                        'parcours' => $parcours,
                        'is_creation' => $isParcoursCreation,
                        'creation_details' => $parcoursCreationDetails,
                        'modifications' => $parcoursModifications,
                        'demande_justification' => $demandeJustification,
                    ];
                }
            }

            // Si cette formation ou au moins un de ses parcours a des évolutions
            $hasFormationChanges = $isFormationCreation || !empty($formationModifications);
            $hasParcoursChanges = !empty($parcoursItems);

            if ($hasFormationChanges || $hasParcoursChanges) {
                if (!isset($composantesMap[$compId])) {
                    $composantesMap[$compId] = [
                        'composante' => $composante,
                        'libelle' => $compLibelle,
                        'total_creations' => 0,
                        'total_modifications' => 0,
                        'total_items' => 0,
                        'formations' => [],
                    ];
                }

                $fCreationsCount = ($isFormationCreation ? 1 : 0);
                $fModifsCount = count($formationModifications);
                foreach ($parcoursItems as $pItem) {
                    if ($pItem['is_creation']) {
                        $fCreationsCount++;
                    } else {
                        $fModifsCount += count($pItem['modifications']);
                    }
                }

                $composantesMap[$compId]['total_creations'] += $fCreationsCount;
                $composantesMap[$compId]['total_modifications'] += $fModifsCount;
                $composantesMap[$compId]['total_items'] += ($fCreationsCount + $fModifsCount);

                $composantesMap[$compId]['formations'][] = [
                    'formation' => $formation,
                    'is_creation' => $isFormationCreation,
                    'creation_details' => $formationCreationDetails,
                    'modifications' => $formationModifications,
                    'parcours' => $parcoursItems,
                ];
            }
        }

        // Trier les composantes par libellé
        usort($composantesMap, static function (array $a, array $b): int {
            return strcasecmp((string)$a['libelle'], (string)$b['libelle']);
        });

        // Filtrer les stats des composantes pour la table récapitulative
        $allStatsList = array_values($statsPerComposante);
        usort($allStatsList, static function (array $a, array $b): int {
            // D'abord celles avec des modifs, puis alphabétique
            if ($a['total'] !== $b['total']) {
                return $b['total'] <=> $a['total'];
            }
            return strcasecmp((string)$a['libelle'], (string)$b['libelle']);
        });

        return [
            'stats' => [
                'total_composantes_avec_modifs' => count($composantesMap),
                'total_formations_creees' => $globalFormationsCreees,
                'total_formations_modifiees' => $globalFormationsModifiees,
                'total_parcours_crees' => $globalParcoursCrees,
                'total_parcours_modifies' => $globalParcoursModifies,
                'total_evolutions' => $globalFormationsCreees + $globalFormationsModifiees + $globalParcoursCrees + $globalParcoursModifies,
            ],
            'allComposantesStats' => $allStatsList,
            'composantes' => $composantesMap,
        ];
    }

    /**
     * @param list<string>|array<string> $regimes
     * @return list<string>
     */
    private function normalizeRegimes(array $regimes): array
    {
        $normalized = RegimeInscription::normalize($regimes);
        sort($normalized);
        return $normalized;
    }
}
