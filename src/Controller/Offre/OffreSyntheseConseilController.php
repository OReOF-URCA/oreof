<?php

namespace App\Controller\Offre;

use App\Controller\BaseController;
use App\Entity\PlateformeAdmission;
use App\Enums\TypeModificationDpeEnum;
use App\Repository\AnneeRepository;
use App\Repository\DpeParcoursRepository;
use App\Repository\PlateformeAdmissionParametreRepository;
use App\Repository\PlateformeAdmissionRepository;
use App\Repository\TypeDiplomePlateformeAdmissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OffreSyntheseConseilController extends BaseController
{
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
        $platformEntity = $em->getRepository(PlateformeAdmission::class)->findOneBy(['code' => $platformCode]);
        if (!$platformEntity) {
            $platformEntity = $em->getRepository(PlateformeAdmission::class)->findOneBy(['code' => strtolower($platformCode)]);
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
                    
                    $isParcoursOuvert = ($dpePar->getEtatReconduction() === TypeModificationDpeEnum::OUVERT);

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
}
