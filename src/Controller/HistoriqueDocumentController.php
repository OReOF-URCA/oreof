<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv1/src/Controller/HistoriqueDocumentController.php
 * @author davidannebicque
 * @project oreofv1
 * @lastUpdate 02/09/2026 14:59
 */

namespace App\Controller;

use App\Classes\GetDpeParcours;
use App\Repository\CampagneCollecteRepository;
use App\Repository\HistoriqueParcoursRepository;
use App\Service\SecureUploadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use ZipArchive;

class HistoriqueDocumentController extends BaseController
{
    private SecureUploadService $secureUploadService;

    public function __construct(SecureUploadService $secureUploadService)
    {
        $this->secureUploadService = $secureUploadService;
    }

    #[Route('/administration/historique/documents', name: 'app_historique_documents')]
    public function index(HistoriqueParcoursRepository $historiqueParcoursRepository, CampagneCollecteRepository $campagneCollecteRepository): Response
    {
        $activeCampagne = $this->getCampagneCollecte();
        $historiques = $historiqueParcoursRepository->findAll();
        $results = [];

        foreach ($historiques as $histo) {
            $complements = $histo->getComplements();
            if ($complements && (array_key_exists('fichier', $complements) || array_key_exists('fichier_note', $complements))) {
                $parcours = $histo->getParcours();
                $dpe = GetDpeParcours::getFromParcours($parcours);
                if ($dpe && $dpe->getCampagneCollecte() === $activeCampagne) {

                    $results[] = [
                        'historique' => $histo,
                        'parcours' => $parcours,
                        'formation' => $parcours?->getFormation(),
                        'composante' => $parcours?->getFormation()?->getComposantePorteuse(),
                    ];
                }
            }
        }

        // Sort by year descending
        krsort($results);

        return $this->render('historique/documents.html.twig', [
            'documents' => $results,
        ]);
    }

    #[Route('/historique/documents/download-zip/{type}', name: 'app_historique_documents_download_zip')]
    public function downloadZip(string $type, HistoriqueParcoursRepository $historiqueParcoursRepository, CampagneCollecteRepository $campagneCollecteRepository): StreamedResponse
    {
        $activeCampagne = $campagneCollecteRepository->findOneBy(['defaut' => true]);
        $historiques = $historiqueParcoursRepository->findAll();
        $zip = new ZipArchive();
        $zipName = 'documents_' . $type . '_' . date('Y-m-d_H-i-s') . '.zip';
        $tempFile = tempnam(sys_get_temp_dir(), 'zip');
        $zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($historiques as $histo) {
            $complements = $histo->getComplements();
            $fileKey = ($type === 'pv') ? 'fichier' : 'fichier_note';
            $nameKey = ($type === 'pv') ? 'fichier_original' : 'fichier_note_original';

            if ($complements && array_key_exists($fileKey, $complements)) {
                $parcours = $histo->getParcours();
                $dpe = GetDpeParcours::getFromParcours($parcours);

                if ($dpe && $dpe->getCampagneCollecte() === $activeCampagne) {
                    $formation = $parcours?->getFormation();
                    $composante = $formation?->getComposantePorteuse();

                    $composanteName = $composante ? $composante->getLibelle() : 'SansComposante';
                    $formationName = $formation ? $formation->getMention()?->getSigle() : 'SansFormation';
                    $parcoursName = $parcours ? $parcours->getSigle() : 'SansParcours';

                    $filename = $complements[$fileKey];
                    $originalName = $complements[$nameKey];

                    $filePath = $this->secureUploadService->resolveStoredFilePath('conseils', $filename);

                    if (file_exists($filePath)) {
                        $zipNameInZip = $composanteName . '/' . $formationName . '/' . $parcoursName . '/' . $originalName;
                        $zip->addFile($filePath, $zipNameInZip);
                    }
                }
            }
        }

        $zip->close();

        $response = new StreamedResponse(function () use ($tempFile) {
            readfile($tempFile);
            unlink($tempFile);
        });

        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $zipName . '"');

        return $response;
    }
}
