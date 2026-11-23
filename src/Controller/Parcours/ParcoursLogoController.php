<?php

declare(strict_types=1);

namespace App\Controller\Parcours;

use App\Entity\Parcours;
use App\Exception\FileUploadException;
use App\Service\SecureUploadService;
use App\Utils\Access;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/parcours/v2', name: 'parcours_v2_')]
final class ParcoursLogoController extends AbstractController
{
    public function __construct(
        private readonly SecureUploadService    $secureUploadService,
        private readonly EntityManagerInterface $entityManager,
    )
    {
    }

    #[Route('/{parcours}/logos', name: 'logos', methods: ['GET'])]
    public function logos(Parcours $parcours, Request $request): Response
    {
        if (!$this->isGranted('SHOW', $parcours)) {
            throw $this->createAccessDeniedException();
        }

        $canEdit = $request->query->getBoolean('editable')
            && $this->isGranted('EDIT', $parcours)
            && Access::isTabEditable($parcours, 'presentation');

        return $this->render('parcours_v2/tabs/_logos.html.twig', [
            'parcours' => $parcours,
            'editable' => $canEdit,
        ]);
    }

    #[Route('/{parcours}/upload-logo', name: 'upload_logo', methods: ['POST'])]
    public function uploadLogo(Request $request, Parcours $parcours): JsonResponse
    {
        if (!$this->isGranted('EDIT', $parcours) || !Access::isTabEditable($parcours, 'presentation')) {
            return new JsonResponse(['success' => false, 'error' => 'Non autorisé.'], 403);
        }

        $url = trim((string)$request->request->get('url'));
        if ($url !== '') {
            if (!preg_match('~^https?://~i', $url)) {
                $url = 'https://' . $url;
            }
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return new JsonResponse([
                    'success' => false,
                    'errors' => ['L\'URL web renseignée n\'est pas valide.'],
                ], 422);
            }
        } else {
            $url = null;
        }

        try {
            $uploaded = $this->secureUploadService->uploadFromRequest($request, 'logo', 'logos');
            if ($uploaded === null) {
                $file = $request->files->get('logo');
                if (is_array($file) && isset($file[0]) && $file[0] instanceof UploadedFile) {
                    $uploaded = $this->secureUploadService->upload($file[0], 'logos');
                } else {
                    return new JsonResponse([
                        'success' => false,
                        'errors' => ['Veuillez sélectionner un fichier image valide (PNG ou JPEG).'],
                    ], 400);
                }
            }

            $parcours->addLogo($uploaded->getStoredFilename(), $url);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'filename' => $uploaded->getStoredFilename(),
                'url' => $url,
            ]);
        } catch (FileUploadException $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'volumineux') || str_contains($message, 'trop lourd')) {
                $message = 'Le fichier est trop volumineux (10 Mo maximum).';
            } elseif (str_contains($message, 'Extension') || str_contains($message, 'Type MIME')) {
                $message = 'Format de fichier non autorisé. Formats acceptés : PNG et JPEG uniquement.';
            }

            return new JsonResponse([
                'success' => false,
                'errors' => [$message],
            ], 422);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'errors' => ['Une erreur est survenue lors du téléversement du logo.'],
            ], 500);
        }
    }

    #[Route('/{parcours}/update-logo-url', name: 'update_logo_url', methods: ['POST'])]
    public function updateLogoUrl(Request $request, Parcours $parcours): JsonResponse
    {
        if (!$this->isGranted('EDIT', $parcours) || !Access::isTabEditable($parcours, 'presentation')) {
            return new JsonResponse(['success' => false, 'error' => 'Non autorisé.'], 403);
        }

        $data = json_decode($request->getContent(), true) ?? $request->request->all();
        $filename = $data['filename'] ?? null;
        $url = isset($data['url']) ? trim((string)$data['url']) : null;

        if (!$filename) {
            return new JsonResponse(['success' => false, 'error' => 'Nom de fichier manquant.'], 400);
        }

        if ($url !== null && $url !== '') {
            if (!preg_match('~^https?://~i', $url)) {
                $url = 'https://' . $url;
            }
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return new JsonResponse([
                    'success' => false,
                    'errors' => ['L\'URL web renseignée n\'est pas valide.'],
                ], 422);
            }
        } else {
            $url = null;
        }

        $parcours->updateLogoUrl((string)$filename, $url);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'filename' => $filename,
            'url' => $url,
        ]);
    }

    #[Route('/{parcours}/delete-logo', name: 'delete_logo', methods: ['DELETE', 'POST'])]
    public function deleteLogo(Request $request, Parcours $parcours): JsonResponse
    {
        if (!$this->isGranted('EDIT', $parcours) || !Access::isTabEditable($parcours, 'presentation')) {
            return new JsonResponse(['success' => false, 'error' => 'Non autorisé.'], 403);
        }

        $data = json_decode($request->getContent(), true) ?? $request->request->all();
        $filename = $data['filename'] ?? null;

        if (!$filename) {
            return new JsonResponse(['success' => false, 'error' => 'Nom de fichier manquant.'], 400);
        }

        $normalized = $parcours->getLogosNormalized();
        $exists = false;
        foreach ($normalized as $logo) {
            if ($logo['filename'] === $filename) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            return new JsonResponse(['success' => false, 'error' => 'Logo introuvable.'], 404);
        }

        $parcours->removeLogo((string)$filename);
        $this->entityManager->flush();

        try {
            $this->secureUploadService->delete('logos', (string)$filename);
        } catch (\Throwable) {
            // Continuer même si le fichier physique est manquant
        }

        return new JsonResponse(['success' => true]);
    }

    #[Route('/{parcours}/logo/{filename}', name: 'logo_file', methods: ['GET'])]
    public function logoFile(Parcours $parcours, string $filename): Response
    {
        $filePath = $this->secureUploadService->resolveStoredFilePath('logos', $filename);
        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Logo introuvable.');
        }

        return new BinaryFileResponse($filePath);
    }
}
