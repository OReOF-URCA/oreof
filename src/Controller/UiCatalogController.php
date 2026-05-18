<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\UiCatalogProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/prototype/ui')]
final class UiCatalogController extends AbstractController
{
    #[Route('', name: 'app_ui_catalog', methods: ['GET'])]
    public function index(UiCatalogProvider $provider): Response
    {
        return $this->render('prototype/ui_catalog.html.twig', $provider->build($this->getUser()));
    }
}
