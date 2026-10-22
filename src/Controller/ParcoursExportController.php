<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/FormationExportController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller;

use App\Classes\DataUserSession;
use App\Classes\MyGotenbergPdf;
use App\Entity\CampagneCollecte;
use App\Entity\Composante;
use App\Entity\Parcours;
use App\Message\ExportGenerique;
use App\Repository\ParcoursVersioningRepository;
use App\Service\ExportGeneriqueParcours;
use App\Service\ParcoursExport;
use App\Service\VersioningParcours;
use App\TypeDiplome\Exceptions\TypeDiplomeNotFoundException;
use App\TypeDiplome\TypeDiplomeResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class ParcoursExportController extends AbstractController
{
    public function __construct(
        private readonly MyGotenbergPdf $myPdf
    ) {
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     * @throws TypeDiplomeNotFoundException
     */
    #[Route('/parcours/{parcours}/export-pdf', name: 'app_parcours_export')]
    public function export(
        Parcours $parcours,
        TypeDiplomeResolver $typeDiplomeResolver,
    ): Response {
        $typeDiplome = $parcours->getFormation()?->getTypeDiplome();

        if (null === $typeDiplome) {
            throw new TypeDiplomeNotFoundException();
        }

        $typeD = $typeDiplomeResolver->fromTypeDiplome($typeDiplome);

        return $this->myPdf->render(
            'pdf/parcours.html.twig',
            [
                'formation' => $parcours->getFormation(),
                'typeDiplome' => $typeDiplome,
                'typeDiplomeHandler' => $typeD,
                'parcours' => $parcours,
                'hasParcours' => $parcours->getFormation()?->isHasParcours(),
                'titre' => 'Parcours ' . $parcours->getDisplay(),
                'dto' => $typeD->calcul($parcours),
            ],
            'dpe_parcours_' . $parcours->getDisplay()
        );
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     * @throws TypeDiplomeNotFoundException
     */
    #[Route('/parcours/{parcours}/versioning/export-pdf', name: 'app_parcours_export_pdf_versioning')]
    public function exportPdfVersioning(
        Parcours $parcours,
        ParcoursVersioningRepository $parcoursVersioningRepository,
        VersioningParcours $versioningParcours,
        TypeDiplomeResolver $typeDiplomeResolver,
    ): Response {
        $lastCfvuVersion = $parcoursVersioningRepository->findLastCfvuVersion($parcours);
        $lastCfvuVersion = count($lastCfvuVersion) > 0 ? $lastCfvuVersion[0] : null;

        if ($lastCfvuVersion !== null) {
            $versionData = $versioningParcours->loadParcoursFromVersion($lastCfvuVersion);
            $parcoursVersionData = $versionData['parcours'];
            $dtoVersionData = $versionData['dto'];
            $typeDiplome = $parcoursVersionData->getTypeDiplome();

            $typeD = $typeDiplome !== null ? $typeDiplomeResolver->fromTypeDiplome($typeDiplome) : null;

            return $this->myPdf->render(
                'pdf/parcours.html.twig',
                [
                    'formation' => $parcoursVersionData->getFormation(),
                    'typeDiplome' => $typeDiplome,
                    'typeDiplomeHandler' => $typeD,
                    'parcours' => $parcoursVersionData,
                    'hasParcours' => $parcoursVersionData->getFormation()?->isHasParcours(),
                    'titre' => 'Parcours ' . $parcoursVersionData->getLibelle(),
                    'dto' => $dtoVersionData,
                    'isVersioning' => true,
                ],
                'dpe_parcours_' . $parcours->getLibelle()
            );
        }

        return $this->json(['error' => 'no valid version available'], Response::HTTP_NOT_FOUND);
    }

    #[Route('/parcours/{parcours}/maquette/validee_cfvu/export-json', name: 'app_parcours_export_maquette_json_validee_cfvu')]
    public function exportMaquetteValideeJson(
        Parcours $parcours,
        ParcoursExport $parcoursExport,
        VersioningParcours $versioningParcours,
        ParcoursVersioningRepository $parcoursVersioningRepository
    ) : Response {
        $lastCfvuVersion = $parcoursVersioningRepository->findLastCfvuVersion($parcours);

        if(count($lastCfvuVersion) === 0){
            return $this->json(["error" => "no valid version available"]);
        }

        $versionData = $versioningParcours->loadParcoursFromVersion($lastCfvuVersion[0]);
        $parcours_id = $lastCfvuVersion[0]->getParcours()->getId();
        $formation_id = $lastCfvuVersion[0]->getParcours()->getFormation()->getId();

        $json = $parcoursExport->exportLastValidVersionMaquetteJson(
            $versionData['dto'],
            $versionData['parcours'],
            $parcours_id,
            $formation_id,
            fermetureEmpty: true
        );

        return $this->json($json);
    }

    #[Route('/parcours/{parcours}/maquette-minimum/export-json', name: 'app_parcours_export_maquette_json_minimum')]
    public function exportMaquetteJsonMinimum(Parcours $parcours) : Response {
        $volumesVide = ['presentiel' => 0, 'distanciel' => 0];

        $jsonData = [
            'path' => $this->generateUrl(
                'app_parcours_export_maquette_json_minimum', 
                ['parcours' => $parcours->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'id' => $parcours->getId(),
            'formationId' => $parcours->getFormation()?->getId(),
            'formation' => $parcours->getFormation()?->getDisplay() ?? '',
            'parcours' => $parcours->isParcoursDefaut() ? '' : $parcours->getLibelle() ?? '',
            'typeDiplome' => $parcours->getFormation()?->getTypeDiplome()?->getLibelle() ?? '',
            'composante' => $parcours->getFormation()?->getComposantePorteuse()?->getLibelle() ?? '',
            'volumes' => [
                'CM' => $volumesVide,
                'TD' => $volumesVide,
                'TP' => $volumesVide,
                'autonomie' => 0
            ],
            'ects' => 0,
            'semestres' => []
        ];

        return $this->json($jsonData);
    }

    #[Route('/parcours/{parcours}/maquette/export-json', name: 'app_parcours_export_maquette_json')]
    public function exportMaquetteJson(
        Parcours $parcours,
        ParcoursExport $parcoursExport,
    ): Response {
        return $this->json($parcoursExport->exportMaquetteJson($parcours));
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/parcours/export/generique', name: 'app_parcours_export_generique')]
    public function exportGenerique(
        DataUserSession $dataUserSession
    ){
        return $this->render('export/parcours_generique.html.twig', [
            'campagneCollecte' => $dataUserSession->getCampagneCollecte()->getId(),
            'periodeAnnee' => $dataUserSession->getCampagneCollecte()->getLibelle()
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/parcours/export/generique/search_by_name', name: 'app_parcours_export_generique_search_by_name')]
    public function searchByName(
        EntityManagerInterface $em
    ) : Response {
        $request = Request::createFromGlobals();
        $inputText = $request->query->get('inputText', '');
        $campagne = $request->query->get(
            'campagneCollecte', 
            $em->getRepository(CampagneCollecte::class)->findOneBy(['defaut' => 1])->getId()
        );
        // Minimum de caractères pour la recherche
        if(mb_strlen($inputText) >= 4){
            $searchResults = $em->getRepository(Parcours::class)->findAllByDisplayName($inputText, $campagne);
            $composanteResult = $em->getRepository(Composante::class)->findParcoursByComposanteName($inputText, $campagne);

            return new JsonResponse(array_merge($composanteResult, $searchResults));
        }

        else {
            return new JsonResponse([]);
        }
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/parcours/export/generique/pdf', name: 'app_parcours_export_generique_download_pdf')]
    public function getExportGeneriquePdf(
        ExportGeneriqueParcours $exportParcoursGenerique,
        Request $request,
        MessageBusInterface $bus
    ) : Response {
        $parcoursIdArray = $request->query->all()['id'] ?? [];
        if(count($parcoursIdArray) > 50 || (($parcoursIdArray[0] ?? 'none') === 'all')){
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $bus->dispatch(new ExportGenerique(
                ['type' => 'parcours', 'format' => 'pdf'],
                $request->query->all()['id'] ?? [],
                $request->query->all()['val'] ?? [],
                $request->query->get('campagne', 2),
                $request->query->get('withFieldSorting', "true"),
                $user->getEmail(),
                $request->query->get('withHeader', 'true'),
                $request->query->get('predefinedTemplate', 'false'),
                $request->query->get('templateName', null)
            ));

            $this->addFlash('toast', [
                'type' => 'success',
                'text' => 'Votre demande a bien été prise en compte. Le fichier vous sera envoyé par email.',
                'title' => 'Succès'
            ]);
            return $this->redirectToRoute('app_homepage');
        }

        [$response, $name] = $exportParcoursGenerique->generatePdf($request);

        return new Response(
            $response,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment;filename="' . $name . '.pdf"',
            ]
        );
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/parcours/export/generique/xlsx', name: 'app_parcours_export_generique_xlsx')]
    public function getExportGeneriqueXlsx(
        ExportGeneriqueParcours $exportParcoursGenerique,
        Request $request,
        MessageBusInterface $bus
    ){
        $parcoursIdArray = $request->query->all()['id'] ?? [];

        // S'il y a plus de 50 parcours, on envoie par email
        if(count($parcoursIdArray) > 50 || (($parcoursIdArray[0] ?? 'none') === 'all')){
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $bus->dispatch(
                new ExportGenerique(
                    ['type' => 'parcours', 'format' => 'xlsx'],
                    $request->query->all()['id'] ?? [],
                    $request->query->all()['val'] ?? [],
                    $request->query->get('campagne', 2),
                    $request->query->get('withFieldSorting', "true"),
                    $user->getEmail(),
                    $request->query->get('withHeader', 'true'),
                    $request->query->get('predefinedTemplate', 'false'),
                    $request->query->get('templateName', null)
                )
            );

            $this->addFlash('toast', [
                'type' => 'success',
                'text' => 'Votre demande a bien été prise en compte. Le fichier vous sera envoyé par email.',
                'title' => 'Succès'
            ]);
            return $this->redirectToRoute('app_homepage');
        }

        [$file, $filename] = $exportParcoursGenerique->generateXlsxSpreadsheet($request);

        return new Response(
            $file,
            200,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment;filename="' . $filename . '.xlsx"',
            ]
        );
    }
}
