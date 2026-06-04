<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/ComposanteExportController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 22/02/2023 09:18
 */

namespace App\Controller;

use App\Classes\MyGotenbergPdf;
use App\Entity\Composante;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class ComposanteExportController extends AbstractController
{
    public function __construct(
        protected MyGotenbergPdf $myPdf
    )
    {
    }

    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     */
    #[Route('/composante/export/{composante}', name: 'app_composante_export')]
    public function export(Composante $composante): Response
    {
        //todo: a revoir
        return $this->myPdf->render('pdf/composante.html.twig', ['composante' => $composante], 'dpe_composante_' . $composante->getLibelle());
    }
}
