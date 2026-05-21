<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file //wsl.localhost/Ubuntu/home/louca/oreof-stack/oreofv2/src/Controller/Admin/StyleguideController.php
 * @author louca
 * @project oreofv2
 * @lastUpdate 19/05/2026 14:01
 */


namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/administration/styleguide')]
#[IsGranted('ROLE_ADMIN')] // À adapter selon vos profils de droits
class StyleguideController extends AbstractController
{
    #[Route('', name: 'admin_styleguide_index')]
    public function index(): Response
    {
        return $this->render('admin/styleguide/index.html.twig', [
            'title_card' => 'Design System & Guidelines UI',
            'description_card' => 'Catalogue des composants Tailwind et bonnes pratiques.'
        ]);
    }

    #[Route('/templates/index-type', name: 'admin_styleguide_template_index')]
    public function templateIndex(): Response
    {
        return $this->render('admin/styleguide/templates/index_type.html.twig', [
            'title_card' => 'Gestion des Entités (Page Type)',
            'description_card' => 'Modèle d\'assemblage standard pour une vue de type "Liste/Datatable".',
            'dummy_items' => [['id' => 1, 'label' => 'Exemple A'], ['id' => 2, 'label' => 'Exemple B']]
        ]);
    }
}