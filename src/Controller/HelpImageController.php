<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file //wsl.localhost/Ubuntu/home/louca/oreof-stack/oreofv2/src/Controller/HelpImageController.php
 * @author louca
 * @project oreofv2
 * @lastUpdate 04/05/2026 14:19
 */

namespace App\Controller;

use App\Entity\HelpImage;
use App\Repository\HelpImageRepository;
use App\Service\SecureUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/help/images', name: 'app_admin_help_images_')]
#[IsGranted('ROLE_ADMIN')]
class HelpImageController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, HelpImageRepository $repository): JsonResponse
    {
        $query = $request->query->getString('q');
        $images = $repository->search($query);

        return $this->json($images, 200, [], ['groups' => 'help_image:read']);
    }

    #[Route('/upload', name: 'upload', methods: ['POST'])]
    public function upload(
        Request                $request,
        SecureUploadService    $secureUploadService,
        EntityManagerInterface $em
    ): JsonResponse {
        $nom = $request->request->getString('nom');

        if (empty($nom)) {
            return $this->json(['error' => 'Le nom de l\'image est requis.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $metadata = $secureUploadService->uploadFromRequest($request, 'image', 'help_images');
            if (!$metadata) {
                return $this->json(['error' => 'Aucun fichier reçu.'], Response::HTTP_BAD_REQUEST);
            }

            $helpImage = new HelpImage();
            $helpImage->setNom($nom);
            $helpImage->setFichier($metadata->getStoredFilename());

            $em->persist($helpImage);
            $em->flush();

            return $this->json($helpImage, Response::HTTP_CREATED, [], ['groups' => 'help_image:read']);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['DELETE'])]
    public function delete(
        HelpImage $helpImage,
        EntityManagerInterface $em,
        SecureUploadService $secureUploadService
    ): JsonResponse {
        try {
            // Tente de supprimer le fichier physique
            try {
                $filePath = $secureUploadService->resolveStoredFilePath('help_images', $helpImage->getFichier());
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            } catch (\Throwable $e) {
                // Ignore l'erreur de résolution si le fichier n'existe pas ou regex ne matche pas, on supprime quand même l'entité.
            }

            // Supprimer l'entité
            $em->remove($helpImage);
            $em->flush();

            return $this->json(['success' => true]);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
