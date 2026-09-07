<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Controller/Section/SectionController.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 30/05/2026 22:41
 */

namespace App\Controller\Section;

use App\Controller\BaseController;
use App\Navigation\Breadcrumb\Attribute\Breadcrumb;
use App\Navigation\MenuResolver;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/section', name: 'app_section_')]
class SectionController extends BaseController
{
    #[Route('/pilotage', name: 'pilotage')]
    #[Breadcrumb(menuKey: 'pilotage')]
    public function pilotage(
        MenuResolver $menuResolver
    ): Response
    {
        $menuItem = $menuResolver->findByKey('pilotage');
        if ($menuItem === null) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('default/section.html.twig', [
            'menuItem' => $menuItem,
        ]);
    }

    #[Route('/droits', name: 'droits')]
    public function droits(
        MenuResolver $menuResolver
    ): Response
    {
        $menuItem = $menuResolver->findByKey('droits');
        if ($menuItem === null) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('default/section.html.twig', [
            'menuItem' => $menuItem,
        ]);
    }

    #[Route('/administration', name: 'administration')]
    public function administration(
        MenuResolver $menuResolver
    ): Response
    {
        $menuItem = $menuResolver->findByKey('administration');
        if ($menuItem === null) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('default/section.html.twig', [
            'menuItem' => $menuItem,
        ]);
    }

    #[Route('/offre-formation', name: 'offre_formation')]
    public function offreFormation(
        MenuResolver $menuResolver
    ): Response
    {
        $menuItem = $menuResolver->findByKey('offre');
        if ($menuItem === null) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('default/section.html.twig', [
            'menuItem' => $menuItem,
        ]);
    }

    #[Route('/conseils', name: 'conseils')]
    public function conseils(
        MenuResolver $menuResolver
    ): Response
    {
        $menuItem = $menuResolver->findByKey('conseils');
        if ($menuItem === null) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('default/section.html.twig', [
            'menuItem' => $menuItem,
        ]);
    }
}
