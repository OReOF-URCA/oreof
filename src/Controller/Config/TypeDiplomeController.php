<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/TypeDiplomeController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Config;

use App\Controller\BaseController;
use App\DTO\TranslatableKey;
use App\Entity\TypeDiplome;
use App\Form\TypeDiplomeType;
use App\Service\DetailBuilder;
use App\Repository\TypeDiplomeRepository;
use App\Service\DataTableBuilder;
use App\Service\TypeDiplomePlateformeService;
use App\Utils\JsonRequest;
use App\Utils\TurboStreamResponseFactory;
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
class TypeDiplomeController extends BaseController
{

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SecureUploadService $secureUploadService
    ) {}

    #[Route('/', name: 'app_type_diplome_index', methods: ['GET'])]
    public function index(
        DataTableBuilder $builder
    ): Response
    {
        $table = $builder
            ->setEntity(TypeDiplome::class)
            ->setPerPage(20)
            ->setDefaultSort('libelle')

            // Colonne simple avec tri et recherche
            ->addColumn('libelle', [
                'label' => 'Libellé du type de diplôme',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('libelleCourt', [
                'label' => 'Sigle',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('codeApogee', [
                'label' => 'Code Apogée',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('hasMemoire', [
                'label' => 'Mémoire ?',
                'sortable' => true,
                'filterable' => true,
                'type' => 'boolean',
                'format' => 'boolean',
            ])
            ->addColumn('hasStage', [
                'label' => 'Stage ?',
                'sortable' => true,
                'filterable' => true,
                'type' => 'boolean',
                'format' => 'boolean',
            ])
            ->addColumn('hasProjet', [
                'label' => 'Projet ?',
                'sortable' => true,
                'filterable' => true,
                'type' => 'boolean',
                'format' => 'boolean',
            ])
            ->addColumn('hasSituationPro', [
                'label' => 'Situation Pro. ?',
                'sortable' => true,
                'filterable' => true,
                'type' => 'boolean',
                'format' => 'boolean',
            ])
            ->addColumn('ectsObligatoireSurEc', [
                'label' => 'ECTS Obli. ?',
                'sortable' => true,
                'filterable' => true,
                'type' => 'boolean',
                'format' => 'boolean',
            ])
            ->addShowAction('app_type_diplome_show', [
                'modal' => true,
                'modal_size' => 'lg',
                'modal_title' => 'Voir un type de diplôme',
            ])
            ->addEditAction('app_type_diplome_edit', [
                'modal' => false,
            ])
            ->addDuplicateAction('app_type_diplome_duplicate')
            ->addDeleteAction('app_type_diplome_delete')
            ->build();
        return $this->render('config/type_diplome/index.html.twig', [
            'table' => $table,
        ]);
    }

    #[Route('/new', name: 'app_type_diplome_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        TypeDiplomeRepository        $typeDiplomeRepository,
        TypeDiplomePlateformeService $typeDiplomePlateformeService
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

            // Gérer les plateformes d'admission avec leurs années
            $plateformesData = $form->get('plateformesAdmission')->getData();
            if ($plateformesData) {
                $typeDiplomePlateformeService->syncPlateformes($typeDiplome, $plateformesData, $this->getCampagneCollecte());
            }

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
    public function show(
        TurboStreamResponseFactory $turboStream,
        TypeDiplome                $typeDiplome,
        DetailBuilder              $builder
    ): Response
    {
        $detail = $builder
            ->setEntity(TypeDiplome::class)
            ->addField('libelle', [
                'label' => 'Libellé du type de diplôme',
            ])
            ->addField('libelleCourt', [
                'label' => 'Sigle',
            ])
            ->addField('codeApogee', [
                'label' => 'Code Apogée',
                'empty_text' => 'Non renseigné',
            ])
            ->addField('hasMemoire', [
                'label' => 'Mémoire ?',
                'type' => 'boolean',
                'format' => 'boolean',
            ])
            ->addField('hasStage', [
                'label' => 'Stage ?',
                'type' => 'boolean',
                'format' => 'boolean',
            ])
            ->addField('hasProjet', [
                'label' => 'Projet ?',
                'type' => 'boolean',
                'format' => 'boolean',
            ])
            ->addField('hasSituationPro', [
                'label' => 'Situation Pro. ?',
                'type' => 'boolean',
                'format' => 'boolean',
            ])
            ->addField('ectsObligatoireSurEc', [
                'label' => 'ECTS obligatoires sur EC ?',
                'type' => 'boolean',
                'format' => 'boolean',
            ])
            ->addField('modalitesAdmission', [
                'label' => 'Modalités d’admission',
                'format' => 'html',
                'empty_text' => 'Non renseigné',
            ])
            ->addField('presentationFormation', [
                'label' => 'Présentation des formations',
                'format' => 'html',
                'empty_text' => 'Non renseigné',
            ])
            ->addField('prerequisObligatoires', [
                'label' => 'Prérequis obligatoires',
                'format' => 'html',
                'empty_text' => 'Non renseigné',
            ])
            ->addField('insertionProfessionnelle', [
                'label' => 'Devenir des diplômés',
                'format' => 'html',
                'empty_text' => 'Non renseigné',
            ])
            ->addCustomField('plateformes', function ($typeDiplome) {
                $plateformes = $typeDiplome->getTypeDiplomePlateformeAdmissions();
                if ($plateformes->isEmpty()) {
                    return 'Aucune plateforme définie';
                }

                $html = '<ul class="list-disc pl-5">';
                foreach ($plateformes as $tpa) {
                    $plateforme = $tpa->getPlateforme();
                    $annees = $tpa->getAnnees();

                    $anneesText = '';
                    if ($annees && count($annees) > 0) {
                        sort($annees);
                        $anneesText = ' (Année' . (count($annees) > 1 ? 's' : '') . ' ' . implode(', ', $annees) . ')';
                    }

                    $html .= '<li>' . $plateforme?->getLibelle() . $anneesText . '</li>';
                }
                $html .= '</ul>';

                return $html;
            }, [
                'label' => 'Plateformes d\'admission configurées',
                'format' => 'html',
            ])
            ->build();


        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('type_diplome.show.title', [], 'modal'),
            'Dans : type diplôme ' . $typeDiplome->getLibelle(),
            '_ui/_modal_show_generic.html.twig',
            [
                'entity' => $typeDiplome,
                'detail' => $detail,
            ],
            '_ui/_footer_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}/edit', name: 'app_type_diplome_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        TypeDiplome $typeDiplome,
        TypeDiplomeRepository        $typeDiplomeRepository,
        TypeDiplomePlateformeService $typeDiplomePlateformeService
    ): Response
    {
        $form = $this->createForm(TypeDiplomeType::class, $typeDiplome, [
            'action' => $this->generateUrl('app_type_diplome_edit', ['id' => $typeDiplome->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
            // Le logo n'est pas dans ce formulaire en modification : il est géré par la
            // carte dédiée (upload/suppression en AJAX). On enregistre simplement le reste.
            $typeDiplomeRepository->save($typeDiplome, true);

            // Gérer les plateformes d'admission avec leurs années
            $plateformesData = $form->get('plateformesAdmission')->getData();
            $typeDiplomePlateformeService->syncPlateformes($typeDiplome, $plateformesData ?? [], $this->getCampagneCollecte());

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
