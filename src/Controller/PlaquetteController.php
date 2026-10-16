<?php

namespace App\Controller;

use App\Classes\MyGotenbergPdf;
use App\Entity\Composante;
use App\Entity\Formation;
use App\Entity\User;
use App\Navigation\Breadcrumb\Attribute\Breadcrumb;
use App\Repository\ComposanteRepository;
use App\Service\SecureUploadService;
use App\TypeDiplome\TypeDiplomeResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PlaquetteController extends BaseController
{
    #[Route('/communication/plaquette/{composante<\d+>?}', name: 'app_plaquette')]
    #[Breadcrumb(menuKey: 'pilotage')]
    public function index(
        \App\Navigation\Breadcrumb\Breadcrumb $breadcrumb,
        ComposanteRepository $composanteRepository,
        ?Composante $composante = null,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($composante === null) {
            $userProfil = $user->getUserProfils()->first();
            $composante = $userProfil ? $userProfil->getComposante() : null;
        }

        if ($composante === null) {
            $composante = $composanteRepository->findOneBy([]);
            if ($composante === null) {
                throw $this->createNotFoundException('Aucune composante disponible.');
            }
        }

        $breadcrumb->add('Plaquette de communication : ' . $composante->getLibelle());

        $allComposantes = [];
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_DPE')) {
            $allComposantes = $composanteRepository->findBy([], ['libelle' => 'ASC']);
        }

        return $this->render('plaquette/index.html.twig', [
            'composante'          => $composante,
            'allComposantes'      => $allComposantes,
            'formations'          => $composante->getFormations(),
            'rubriquesFormation'  => $composante->getPlaquetteRubriquesFormation(),
            'rubriquesParcours'   => $composante->getPlaquetteRubriquesParcours(),
        ]);
    }

    #[Route('/communication/plaquette/rubriques/{composante<\d+>}', name: 'app_plaquette_rubriques', methods: ['GET'])]
    public function rubriques(Composante $composante): Response
    {
        return $this->render('plaquette/_listeRubriques.html.twig', [
            'composante'         => $composante,
            'rubriquesFormation' => $composante->getPlaquetteRubriquesFormation(),
            'rubriquesParcours'  => $composante->getPlaquetteRubriquesParcours(),
        ]);
    }

    #[Route('/communication/plaquette/rubriques/{composante<\d+>}/reorder', name: 'app_plaquette_rubriques_reorder', methods: ['POST'])]
    public function reorder(
        Request $request,
        Composante $composante,
        EntityManagerInterface $em,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            // Supporte aussi les requêtes multipart/form-data
            $data = $request->request->all();
        }

        $section = $data['section'] ?? null; // 'formation' ou 'parcours'
        $action = $data['action'] ?? 'reorder';

        if (!in_array($section, ['formation', 'parcours'], true)) {
            return new JsonResponse(['success' => false, 'message' => 'Section invalide'], Response::HTTP_BAD_REQUEST);
        }

        if ($action === 'reset') {
            if ($section === 'formation') {
                $composante->setPlaquetteRubriquesFormation(Composante::RUBRIQUES_FORMATION);
            } else {
                $composante->setPlaquetteRubriquesParcours(Composante::RUBRIQUES_PARCOURS);
            }
            $em->flush();

            return new JsonResponse(['success' => true]);
        }

        if ($action === 'toggle') {
            $rubrique = $data['rubrique'] ?? null;
            if (!$rubrique) {
                return new JsonResponse(['success' => false, 'message' => 'Rubrique manquante'], Response::HTTP_BAD_REQUEST);
            }

            if ($section === 'formation') {
                $current = $composante->getPlaquetteRubriquesFormation();
                if (!array_key_exists($rubrique, Composante::RUBRIQUES_FORMATION)) {
                    return new JsonResponse(['success' => false, 'message' => 'Rubrique inconnue'], Response::HTTP_BAD_REQUEST);
                }
                $current[$rubrique] = ($current[$rubrique] === null) ? count(array_filter($current)) + 1 : null;
                $composante->setPlaquetteRubriquesFormation($this->compactPositions($current));
            } else {
                $current = $composante->getPlaquetteRubriquesParcours();
                if (!array_key_exists($rubrique, Composante::RUBRIQUES_PARCOURS)) {
                    return new JsonResponse(['success' => false, 'message' => 'Rubrique inconnue'], Response::HTTP_BAD_REQUEST);
                }
                $current[$rubrique] = ($current[$rubrique] === null) ? count(array_filter($current)) + 1 : null;
                $composante->setPlaquetteRubriquesParcours($this->compactPositions($current));
            }

            $em->flush();
            return new JsonResponse(['success' => true]);
        }

        // Action reorder (SortableJS drag-and-drop)
        $items = $data['items'] ?? [];
        if (!is_array($items)) {
            return new JsonResponse(['success' => false, 'message' => 'Format des items invalide'], Response::HTTP_BAD_REQUEST);
        }

        $rubriquesUpdated = [];
        $position = 1;
        foreach ($items as $item) {
            $key = is_array($item) ? ($item['id'] ?? null) : $item;
            $visible = is_array($item) ? ($item['visible'] ?? true) : true;
            if ($key) {
                $rubriquesUpdated[$key] = $visible ? $position++ : null;
            }
        }

        if ($section === 'formation') {
            // S'assurer que toutes les rubriques déclarées sont présentes
            foreach (Composante::RUBRIQUES_FORMATION as $key => $defaultPos) {
                if (!array_key_exists($key, $rubriquesUpdated)) {
                    $rubriquesUpdated[$key] = null;
                }
            }
            $composante->setPlaquetteRubriquesFormation($rubriquesUpdated);
        } else {
            foreach (Composante::RUBRIQUES_PARCOURS as $key => $defaultPos) {
                if (!array_key_exists($key, $rubriquesUpdated)) {
                    $rubriquesUpdated[$key] = null;
                }
            }
            $composante->setPlaquetteRubriquesParcours($rubriquesUpdated);
        }

        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/communication/plaquette/charte/{composante<\d+>}/upload', name: 'app_plaquette_charte_upload', methods: ['POST'])]
    public function uploadCharte(
        Request $request,
        Composante $composante,
        SecureUploadService $secureUploadService,
        EntityManagerInterface $em,
    ): RedirectResponse {
        $type = $request->request->get('type');
        $file = $request->files->get('file');

        if (!$file instanceof UploadedFile || !in_array($type, ['header', 'footer', 'logo'], true)) {
            $this->addFlash('toast', [
                'type' => 'danger',
                'text' => 'Fichier ou type de visuel manquant.',
            ]);
            return $this->redirectToRoute('app_plaquette', ['composante' => $composante->getId()]);
        }

        try {
            $context = ($type === 'logo') ? 'logos' : 'plaquettes';
            $meta = $secureUploadService->upload($file, $context);

            match ($type) {
                'header' => $composante->setHeaderPlaquette($meta->getStoredFilename()),
                'footer' => $composante->setFooterPlaquette($meta->getStoredFilename()),
                'logo'   => $composante->setLogo($meta->getStoredFilename()),
            };


            $em->flush();
            $this->addFlash('toast', [
                'type' => 'success',
                'text' => sprintf('Le visuel (%s) a été mis à jour avec succès.', ucfirst($type)),
            ]);
        } catch (\Throwable $e) {
            $this->addFlash('toast', [
                'type' => 'danger',
                'text' => 'Erreur lors du téléversement : ' . $e->getMessage(),
            ]);
        }

        return $this->redirectToRoute('app_plaquette', ['composante' => $composante->getId()]);
    }

    #[Route('/communication/plaquette/charte/{composante<\d+>}/reset/{type}', name: 'app_plaquette_charte_reset', methods: ['POST'])]
    public function resetCharte(
        Composante $composante,
        string $type,
        EntityManagerInterface $em,
        Filesystem $filesystem,
    ): RedirectResponse {
        if (!in_array($type, ['header', 'footer', 'logo'], true)) {
            throw $this->createNotFoundException('Type de visuel invalide.');
        }

        $kernelDir = $this->getParameter('kernel.project_dir');

        if ($type === 'header' && $composante->getHeaderPlaquette()) {
            $path = $kernelDir . '/public/uploads/plaquettes/' . $composante->getHeaderPlaquette();
            if ($filesystem->exists($path)) {
                $filesystem->remove($path);
            }
            $composante->setHeaderPlaquette(null);
        } elseif ($type === 'footer' && $composante->getFooterPlaquette()) {
            $path = $kernelDir . '/public/uploads/plaquettes/' . $composante->getFooterPlaquette();
            if ($filesystem->exists($path)) {
                $filesystem->remove($path);
            }
            $composante->setFooterPlaquette(null);
        } elseif ($type === 'logo' && $composante->getLogo()) {
            $path = $kernelDir . '/public/uploads/logos/' . $composante->getLogo();
            if ($filesystem->exists($path)) {
                $filesystem->remove($path);
            }
            $composante->setLogo(null);
        }

        $em->flush();
        $this->addFlash('toast', [
            'type' => 'success',
            'text' => sprintf('Le visuel (%s) a été réinitialisé à sa valeur par défaut.', ucfirst($type)),
        ]);

        return $this->redirectToRoute('app_plaquette', ['composante' => $composante->getId()]);
    }

    #[Route('/communication/plaquette/apercu/{formation}', name: 'app_plaquette_apercu', methods: ['GET'])]
    public function apercu(

        Formation $formation,
        TypeDiplomeResolver $typeDiplomeResolver,
        MyGotenbergPdf $myPdf,
    ): Response {
        $composante = $formation->getComposantePorteuse();
        $rubriques = $composante?->getPlaquetteRubriques();
        $typeDiplome = $formation->getTypeDiplome();

        $tParcours = [];
        $typeD = $typeDiplomeResolver->get($typeDiplome);
        foreach ($formation->getParcours() as $parcours) {
            $tParcours[$parcours->getId()] = $typeD->calcul($parcours);
        }

        return $myPdf->render(
            template: 'pdf/formation_plaquette.html.twig',
            context: [
                'composante'   => $composante,
                'formation'    => $formation,
                'typeDiplome'  => $typeDiplome,
                'titre'        => 'Plaquette de la formation ' . $formation->getDisplay(),
                'tParcours'    => $tParcours,
                'rubriques'    => $rubriques,
            ],
            name: 'Apercu_plaquette_' . $formation->getDisplay(),
            options: [
                'withTemplate' => true,
                'attachment'   => false, // Affichage inline dans l'iframe
            ]
        );
    }

    /**
     * Recompacte les index de position (1, 2, 3...) pour les éléments non nuls
     */
    private function compactPositions(array $rubriques): array
    {
        $nonNull = [];
        $nulls = [];

        foreach ($rubriques as $k => $v) {
            if ($v !== null) {
                $nonNull[$k] = $v;
            } else {
                $nulls[$k] = null;
            }
        }

        asort($nonNull);
        $pos = 1;
        $result = [];
        foreach ($nonNull as $k => $v) {
            $result[$k] = $pos++;
        }

        return array_merge($result, $nulls);
    }
}
