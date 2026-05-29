<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DocumentController extends AbstractController
{
    #[Route('/documents', name: 'app_document')]
    public function index(): Response
    {
        return $this->render('document/index.html.twig');
    }
}
