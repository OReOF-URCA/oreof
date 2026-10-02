<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file //wsl.localhost/Ubuntu/home/louca/oreof-stack/oreofv2/src/Controller/HelpAdminController.php
 * @author louca
 * @project oreofv2
 * @lastUpdate 29/04/2026 15:18
 */


namespace App\Controller;

use App\Entity\Help;
use App\Enums\CentreGestionEnum;
use App\Form\HelpType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouterInterface;

#[Route('/administration/help')]
#[IsGranted('ROLE_ADMIN')]
class HelpAdminController extends AbstractController
{
    #[Route('/', name: 'app_help_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em, RouterInterface $router): Response
    {
        $helps = $em->getRepository(Help::class)->findBy([], ['routeSlug' => 'ASC']);
        $previewUrls = [];

        foreach ($helps as $help) {
            $routeName = $help->getRouteSlug();
            $previewUrls[$help->getId()] = null;

            if (!$routeName) {
                continue;
            }

            $route = $router->getRouteCollection()->get($routeName);
            if (!$route) {
                continue;
            }

            if (count($route->compile()->getPathVariables()) > 0) {
                continue;
            }

            try {
                $previewUrls[$help->getId()] = $router->generate($routeName);
            } catch (RouteNotFoundException) {
                $previewUrls[$help->getId()] = null;
            }
        }

        return $this->render('help_admin/index.html.twig', [
            'helps' => $helps,
            'previewUrls' => $previewUrls,
        ]);
    }

    #[Route('/new', name: 'app_help_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $help = new Help();
        $help->setIsActive(true);

        $route = $request->query->getString('route');
        if ($route) {
            $help->setRouteSlug($route);
            $centre = $this->inferCentreFromRoute($route);
            if ($centre !== null) {
                $help->setCentresShow([$centre]);
            }
        }

        $form = $this->createForm(HelpType::class, $help);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($help);
            $em->flush();
            $this->addFlash('success', 'Aide créée avec succès !');
            return $this->redirectToRoute('app_help_index');
        }

        return $this->render('help_admin/form.html.twig', [
            'help' => $help,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_help_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Help $help, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(HelpType::class, $help);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Aide mise à jour !');
            return $this->redirectToRoute('app_help_index');
        }

        return $this->render('help_admin/form.html.twig', [
            'help' => $help,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_help_delete', methods: ['POST'])]
    public function delete(Request $request, Help $help, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$help->getId(), $request->request->getString('_token'))) {
            $em->remove($help);
            $em->flush();
            $this->addFlash('success', 'Aide supprimée.');
        }
        return $this->redirectToRoute('app_help_index');
    }

    /**
     * Déduit un centre à partir de la ressource métier de la page.
     *
     * On suit les mêmes ressources que les contrôles de droits basés sur le voter.
     */
    private function inferCentreFromRoute(string $routeName): ?string
    {
        return match (true) {
            str_starts_with($routeName, 'app_etablissement') => CentreGestionEnum::CENTRE_GESTION_ETABLISSEMENT->value,
            str_starts_with($routeName, 'app_composante') => CentreGestionEnum::CENTRE_GESTION_COMPOSANTE->value,
            str_starts_with($routeName, 'app_formation') => CentreGestionEnum::CENTRE_GESTION_FORMATION->value,
            str_starts_with($routeName, 'app_parcours') => CentreGestionEnum::CENTRE_GESTION_PARCOURS->value,
            default => null,
        };
    }
}
