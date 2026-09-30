<?php

declare(strict_types=1);

namespace App\Controller;

use App\Navigation\Breadcrumb\Attribute\Breadcrumb;
use App\Repository\FaqRepository;
use App\Repository\HelpImageRepository;
use App\Repository\HelpRepository;
use App\Service\HelpTransferService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Export / import des aides, de la FAQ et de la galerie d'images entre instances (local → pré-prod…). */
#[Route('/administration/help/transfert')]
#[IsGranted('ROLE_ADMIN')]
class HelpTransferController extends AbstractController
{
    #[Route('', name: 'app_help_transfer', methods: ['GET'])]
    #[Breadcrumb(menuKey: 'administration.help')]
    #[Breadcrumb(label: 'Import / export')]
    public function index(
        Request             $request,
        HelpRepository      $helpRepository,
        FaqRepository       $faqRepository,
        HelpImageRepository $helpImageRepository,
    ): Response {
        return $this->render('help_admin/transfer.html.twig', [
            'nbHelps' => $helpRepository->count([]),
            'nbFaqs' => $faqRepository->count([]),
            'nbImages' => $helpImageRepository->count([]),
            'maxUploadSize' => $this->formatSize(UploadedFile::getMaxFilesize()),
            // Bilan du dernier import (déposé en session par import(), affiché une seule fois)
            'report' => $request->getSession()->remove('help_import_report'),
        ]);
    }

    #[Route('/export', name: 'app_help_export', methods: ['GET'])]
    public function export(Request $request, HelpTransferService $helpTransferService): BinaryFileResponse
    {
        $path = $helpTransferService->export($request->query->getBoolean('images', true));

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', 'application/zip');
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            sprintf('oreof-aides-faq-%s.zip', date('Ymd-Hi'))
        );
        $response->deleteFileAfterSend(true);

        return $response;
    }

    #[Route('/import', name: 'app_help_import', methods: ['POST'])]
    public function import(Request $request, HelpTransferService $helpTransferService): Response
    {
        $file = $request->files->get('archive');

        // Au-delà de post_max_size, PHP vide $_POST et $_FILES : le jeton CSRF disparaît aussi.
        if ($file === null && $request->request->count() === 0) {
            $this->addFlash('danger', sprintf('Archive trop volumineuse pour ce serveur (limite : %s). Exportez sans les images ou faites relever la limite.', $this->formatSize(UploadedFile::getMaxFilesize())));

            return $this->redirectToRoute('app_help_transfer');
        }

        if (!$this->isCsrfTokenValid('help_import', (string)$request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide, veuillez réessayer.');

            return $this->redirectToRoute('app_help_transfer');
        }

        if (!$file instanceof UploadedFile) {
            $this->addFlash('danger', 'Aucune archive sélectionnée.');

            return $this->redirectToRoute('app_help_transfer');
        }

        if (!$file->isValid()) {
            $this->addFlash('danger', in_array($file->getError(), [\UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE], true)
                ? sprintf('Archive trop volumineuse pour ce serveur (limite : %s). Exportez sans les images ou faites relever la limite.', $this->formatSize(UploadedFile::getMaxFilesize()))
                : 'Le téléversement de l\'archive a échoué : ' . $file->getErrorMessage());

            return $this->redirectToRoute('app_help_transfer');
        }

        try {
            $result = $helpTransferService->import($file->getPathname(), $request->request->getBoolean('overwrite'));
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('app_help_transfer');
        }

        $this->addFlash('success', 'Import terminé.');
        $request->getSession()->set('help_import_report', ['summary' => $result->summary(), 'warnings' => $result->warnings]);

        return $this->redirectToRoute('app_help_transfer');
    }

    private function formatSize(int|float $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' Mo' : round($bytes / 1024) . ' Ko';
    }
}
