<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/TypeDiplomeController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Config;

use App\Entity\TypeDiplome;
use App\Form\TypeDiplomeType;
use App\Repository\TypeDiplomeRepository;
use App\Utils\JsonRequest;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use App\Service\SecureUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/administration/type-diplome')]
class TypeDiplomeController extends AbstractController
{

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SecureUploadService $secureUploadService
    ) {}

    #[Route('/', name: 'app_type_diplome_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('config/type_diplome/index.html.twig');
    }

    #[Route('/liste', name: 'app_type_diplome_liste', methods: ['GET'])]
    public function liste(TypeDiplomeRepository $typeDiplomeRepository): Response
    {
        return $this->render('config/type_diplome/_liste.html.twig', [
            'type_diplomes' => $typeDiplomeRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_type_diplome_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        TypeDiplomeRepository $typeDiplomeRepository
    ): Response
    {
        $typeDiplome = new TypeDiplome();
        $form = $this->createForm(TypeDiplomeType::class, $typeDiplome, [
            'action' => $this->generateUrl('app_type_diplome_new'),
        ]);
        $form->handleRequest($request);


        if ($form->isSubmitted() && $form->isValid())
        {
            $logoData = $form->get('logo')->getData();
            $hasFormatError = false;
            $hasSizeError = false;

            if ($logoData) {
                $logoFiles = is_array($logoData) ? $logoData : [$logoData];

                foreach ($logoFiles as $logoFile) {
                    try {
                        $uploaded = $this->secureUploadService->upload($logoFile, 'logos');
                        $logos = $typeDiplome->getLogo() ?? [];
                        $logos[] = $uploaded->getStoredFilename();
                        $typeDiplome->setLogo($logos);
                    } catch (\Exception $e) {
                        if (str_contains($e->getMessage(), 'volumineux')) {
                            $hasSizeError = true;
                        } else {
                            $hasFormatError = true;
                        }
                    }
                }

                if ($hasSizeError) {
                    $this->addFlash('toast', ['type' => 'error', 'text' => 'Fichier(s) trop lourd(s) (10 Mo max)', 'title' => 'Erreur']);
                }
                if ($hasFormatError) {
                    $this->addFlash('toast', ['type' => 'error', 'text' => 'Format invalide (PNG/JPEG uniquement)', 'title' => 'Erreur']);
                }
            }

            $typeDiplomeRepository->save($typeDiplome, true);
            // Abandon de la fenêtre modale
            // return $this->json(true);

            $this->addFlash('toast', [
                'type' => $hasFormatError || $hasSizeError ? 'warning' : 'success',
                'text' => $hasFormatError || $hasSizeError
                    ? 'Type de diplôme créé mais le logo n\'a pas pu être ajouté.'
                    : 'Création du type de diplôme réussie',
                'title' => $hasFormatError || $hasSizeError ? 'Attention' : 'Succès',
            ]);

            return $this->redirectToRoute('app_type_diplome_index');
        }

        return $this->render('config/type_diplome/new.html.twig', [
            'type_diplome' => $typeDiplome,
            'form' => $form->createView(),
            'titre' => "Création d'un type de diplôme"
        ]);
    }

    //region La section pour les logos

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}/logos', name: 'app_type_diplome_logos', methods: ['GET'])]
    public function logos(TypeDiplome $typeDiplome, Request $request): Response
    {
        return $this->render('config/type_diplome/_logos.html.twig', [
            'typeDiplome' => $typeDiplome,
            'editable' => $request->query->getBoolean('editable'),
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}/upload-logo', name: 'app_type_diplome_upload_logo', methods: ['POST'])]
    public function uploadLogo(Request $request, TypeDiplome $typeDiplome): JsonResponse
    {
        $files = $request->files->get('logo');

        if (!$files) {
            return new JsonResponse(['success' => false, 'error' => 'Aucun fichier reçu'], 400);
        }

        // Ne prend que le premier logo
        $file = is_array($files) ? $files[0] : $files;

        try
        {
            // Supprime le logo si il y en a déjà un
            $existingLogos = $typeDiplome->getLogo() ?? [];
            foreach ($existingLogos as $existing)
            {
                $this->secureUploadService->delete('logos', $existing);
            }

            $uploaded = $this->secureUploadService->upload($file, 'logos');
            $typeDiplome->setLogo([$uploaded->getStoredFilename()]);
        }

        catch (\Exception $e)
        {
            $error = str_contains($e->getMessage(), 'volumineux')
                ? 'Fichier trop lourd (10 Mo max)'
                : 'Format invalide (PNG/JPEG uniquement)';
            return new JsonResponse(['success' => false, 'errors' => [$error]], 422);
        }

        $this->entityManager->flush();
        return new JsonResponse(['success' => true]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}/delete-logo', name: 'app_type_diplome_delete_logo', methods: ['DELETE'])]
    public function deleteLogo(Request $request, TypeDiplome $typeDiplome): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $filename = $data['filename'] ?? null;

        if (!$filename) {
            return new JsonResponse(['success' => false, 'error' => 'Nom de fichier manquant'], 400);
        }

        $logos = $typeDiplome->getLogo() ?? [];

        if (!in_array($filename, $logos)) {
            return new JsonResponse(['success' => false, 'error' => 'Fichier introuvable'], 404);
        }

        $typeDiplome->setLogo(array_values(array_filter($logos, fn($l) => $l !== $filename)));
        $this->entityManager->flush();
        $this->secureUploadService->delete('logos', $filename);

        return new JsonResponse(['success' => true]);
    }

    //endregion

    //region Les 2 routes pour l'API

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}/logos-api', name: 'app_type_diplome_logos_api', methods: ['GET'])]
    public function logosApi(TypeDiplome $typeDiplome): JsonResponse
    {
        $logos = $typeDiplome->getLogo() ?? [];
        $result = array_map(fn($filename) => [
            'image_data' => $this->generateUrl(
                'app_type_diplome_logo_api',
                ['id' => $typeDiplome->getId(), 'filename' => $filename],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'image_type' => mime_content_type($this->secureUploadService->resolveStoredFilePath('logos', $filename)) ?: 'image/png',
        ], $logos);

        return new JsonResponse(['logos' => $result]);
    }

    #[Route('/{id}/logo/{filename}', name: 'app_type_diplome_logo_api', methods: ['GET'])]
    public function logoApi(TypeDiplome $typeDiplome, string $filename): Response
    {
        $filePath = $this->secureUploadService->resolveStoredFilePath('logos', $filename);

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException();
        }

        return new BinaryFileResponse($filePath);
    }

    //endregion

    #[Route('/{id}', name: 'app_type_diplome_show', methods: ['GET'])]
    public function show(TypeDiplome $typeDiplome): Response
    {
        return $this->render('config/type_diplome/show.html.twig', [
            'type_diplome' => $typeDiplome,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_type_diplome_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        TypeDiplome $typeDiplome,
        TypeDiplomeRepository $typeDiplomeRepository
    ): Response
    {
        $form = $this->createForm(TypeDiplomeType::class, $typeDiplome, [
            'action' => $this->generateUrl('app_type_diplome_edit', ['id' => $typeDiplome->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
            $logoFiles = $form->get('logo')->getData();
            if ($logoFiles)
            {
                $existingLogos = $typeDiplome->getLogo() ?? [];
                foreach ($existingLogos as $existing)
                {
                    $this->secureUploadService->delete('logos', $existing);
                }
                $typeDiplome->setLogo([]);

                $hasFormatError = false;
                $hasSizeError = false;

                foreach ($logoFiles as $logoFile)
                {
                    try
                    {
                        $uploaded = $this->secureUploadService->upload($logoFile, 'logos');
                        $logos = $typeDiplome->getLogo() ?? [];
                        $logos[] = $uploaded->getStoredFilename();
                        $typeDiplome->setLogo($logos);
                    }
                    catch (\Exception $e)
                    {
                        if (str_contains($e->getMessage(), 'volumineux'))
                        {
                            $hasSizeError = true;
                        }
                        else
                        {
                            $hasFormatError = true;
                        }
                    }
                }

                if ($hasSizeError)
                {
                    $this->addFlash('toast', ['type' => 'error', 'text' => 'Fichier(s) trop lourd(s) (10 Mo max)', 'title' => 'Erreur']);
                }
                if ($hasFormatError)
                {
                    $this->addFlash('toast', ['type' => 'error', 'text' => 'Format invalide (PNG/JPEG uniquement)', 'title' => 'Erreur']);
                }
            }

            $typeDiplomeRepository->save($typeDiplome, true);
            // Abandon de la fenêtre modale
            // return $this->json(true);
            $this->addFlash('toast', [
                'type' => 'success',
                'text' => 'Type Diplôme modifié avec succès',
                'title' => 'Succès',
            ]);
            return $this->redirectToRoute('app_type_diplome_index');
        }

        return $this->render('config/type_diplome/new.html.twig', [
            'type_diplome' => $typeDiplome,
            'form' => $form->createView(),
            'titre' => "Modification d'un type de diplôme"
        ]);
    }

    #[Route('/{id}/duplicate', name: 'app_type_diplome_duplicate', methods: ['GET'])]
    public function duplicate(
        TypeDiplomeRepository $typeDiplomeRepository,
        TypeDiplome $typeDiplome
    ): Response {
        $typeDiplomeNew = clone $typeDiplome;
        $typeDiplomeNew->setLibelle($typeDiplome->getLibelle() . ' - Copie');
        $typeDiplomeRepository->save($typeDiplomeNew, true);
        return $this->json(true);
    }

    /**
     * @throws JsonException
     */
    #[Route('/{id}', name: 'app_type_diplome_delete', methods: ['DELETE'])]
    public function delete(
        Request $request,
        TypeDiplome $typeDiplome,
        TypeDiplomeRepository $typeDiplomeRepository
    ): Response {
        if ($this->isCsrfTokenValid(
            'delete' . $typeDiplome->getId(),
            JsonRequest::getValueFromRequest($request, 'csrf')
        )) {
            $typeDiplomeRepository->remove($typeDiplome, true);

            return $this->json(true);
        }

        return $this->json(false);
    }
}
