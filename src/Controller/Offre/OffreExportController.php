<?php

namespace App\Controller\Offre;

use App\Controller\BaseController;
use App\Entity\Composante;
use App\Entity\PlateformeAdmission;
use App\Repository\ComposanteRepository;
use App\Repository\TypeDiplomeRepository;
use App\Service\Offre\OffreAnnexesExportService;
use App\Utils\TurboStreamResponseFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OffreExportController extends BaseController
{
    #[Route('/offrev2/modal-annexes-ca', name: 'offre_v2_modal_annexes_ca', methods: ['GET'])]
    #[Route('/offre/composante/{composante}/modal-annexes-ca', name: 'offre_v2_composante_modal_annexes_ca', methods: ['GET'])]
    public function modalAnnexesCa(
        OffreAnnexesExportService $exportService,
        TurboStreamResponseFactory $turboStream,
        ?Composante $composante = null,
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

        $campagne = $this->getCampagneCollecte();
        $platformsData = $exportService->getPlatformsExportData($campagne, $composante);

        return $turboStream->streamOpenModalFromTemplates(
            'Génération des annexes CA',
            'Campagne ' . $campagne->getLibelle() . ($composante ? ' — ' . $composante->getLibelle() : ''),
            'offre_v2/_modal_annexes_ca.html.twig',
            [
                'campagne' => $campagne,
                'composante' => $composante,
                'platforms' => $platformsData,
            ],
            '_ui/_footer_cancel.html.twig',
        );
    }

    #[Route('/offrev2/export-plateforme/{plateforme}', name: 'offre_v2_export_plateforme', methods: ['GET'])]
    #[Route('/offre/composante/{composante}/export-plateforme/{plateforme}', name: 'offre_v2_composante_export_plateforme', methods: ['GET'])]
    public function exportPlateforme(
        PlateformeAdmission $plateforme,
        Request $request,
        OffreAnnexesExportService $exportService,
        TypeDiplomeRepository $typeDiplomeRepository,
        ComposanteRepository $composanteRepository,
        ?Composante $composante = null,
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

        $campagne = $this->getCampagneCollecte();

        $typeDiplomeId = $request->query->getInt('typeDiplome');
        $typeDiplome = $typeDiplomeId > 0 ? $typeDiplomeRepository->find($typeDiplomeId) : null;

        $targetComposanteId = $request->query->getInt('targetComposante');
        $effectiveComposante = $composante ?? ($targetComposanteId > 0 ? $composanteRepository->find($targetComposanteId) : null);

        return $exportService->exportPlatformSingle($plateforme, $campagne, $effectiveComposante, $typeDiplome);
    }

    #[Route('/offrev2/export-plateforme-zip/{plateforme}', name: 'offre_v2_export_plateforme_zip', methods: ['GET'])]
    #[Route('/offre/composante/{composante}/export-plateforme-zip/{plateforme}', name: 'offre_v2_composante_export_plateforme_zip', methods: ['GET'])]
    public function exportPlateformeZip(
        PlateformeAdmission $plateforme,
        Request $request,
        OffreAnnexesExportService $exportService,
        TypeDiplomeRepository $typeDiplomeRepository,
        ?Composante $composante = null,
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

        $campagne = $this->getCampagneCollecte();
        $split = $request->query->getString('split', 'diplomes');

        if ($split === 'composantes') {
            $typeDiplomeId = $request->query->getInt('typeDiplome');
            $typeDiplome = $typeDiplomeId > 0 ? $typeDiplomeRepository->find($typeDiplomeId) : null;
            return $exportService->exportPlatformZipByComposantes($plateforme, $campagne, $typeDiplome);
        }

        return $exportService->exportPlatformZipByDiplomes($plateforme, $campagne, $composante);
    }

    #[Route('/offrev2/export-all-plateformes-zip', name: 'offre_v2_export_all_plateformes_zip', methods: ['GET'])]
    #[Route('/offre/composante/{composante}/export-all-plateformes-zip', name: 'offre_v2_composante_export_all_plateformes_zip', methods: ['GET'])]
    public function exportAllPlateformesZip(
        OffreAnnexesExportService $exportService,
        ?Composante $composante = null,
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

        $campagne = $this->getCampagneCollecte();
        return $exportService->exportAllPlatformsZip($campagne, $composante);
    }

    #[Route('/offrev2/export-annexe/{type}', name: 'offre_v2_export_annexe', methods: ['GET'])]
    #[Route('/offre/composante/{composante}/export-annexe/{type}', name: 'offre_v2_composante_export_annexe', methods: ['GET'])]
    public function exportAnnexe(
        string $type,
        OffreAnnexesExportService $exportService,
        ?Composante $composante = null,
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

        $campagne = $this->getCampagneCollecte();
        return $exportService->exportSingleAnnexe($type, $campagne, $composante);
    }

    #[Route('/offrev2/export-annexes-zip', name: 'offre_v2_export_annexes_zip', methods: ['GET'])]
    #[Route('/offre/composante/{composante}/export-annexes-zip', name: 'offre_v2_composante_export_annexes_zip', methods: ['GET'])]
    public function exportAnnexesZip(
        OffreAnnexesExportService $exportService,
        ?Composante $composante = null,
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

        $campagne = $this->getCampagneCollecte();
        return $exportService->exportAllAsZip($campagne, $composante);
    }
}
