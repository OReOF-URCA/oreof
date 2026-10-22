<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/FicheMatiereExportController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller;

use App\Classes\JsonReponse;
use App\Classes\MyGotenbergPdf;
use App\Entity\Constantes;
use App\Entity\FicheMatiere;
use App\Entity\Parcours;
use App\Message\Export;
use App\Message\ExportGenerique;
use App\Repository\TypeDiplomeRepository;
use App\Repository\TypeEpreuveRepository;
use App\Service\ExportGeneriqueFicheMatiere;
use App\TypeDiplome\Exceptions\TypeDiplomeNotFoundException;
use App\TypeDiplome\TypeDiplomeResolver;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class FicheMatiereExportController extends AbstractController
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
    #[Route('/fiche-matiere/export/{id}', name: 'app_fiche_matiere_export')]
    public function export(
        TypeEpreuveRepository $typeEpreuveRepository,
        TypeDiplomeRepository $typeDiplomeRepository,
        TypeDiplomeResolver $typeDiplomeResolver,
        FicheMatiere        $ficheMatiere): Response
    {
        if ($ficheMatiere->isHorsDiplome() === false) {
            $formation = $ficheMatiere->getParcours()?->getFormation();
            if ($formation === null) {
                throw new RuntimeException('Formation non trouvée');
            }

            $typeDiplome = $formation->getTypeDiplome();
            if ($typeDiplome === null) {
                throw new TypeDiplomeNotFoundException();
            }


        } else {
            $formation = null;
            $typeDiplome = $typeDiplomeRepository->findOneBy(['libelle_court' => 'L']);
        }

        $typeD = $typeDiplomeResolver->fromTypeDiplome($typeDiplome);

        $bccs = [];
        foreach ($ficheMatiere->getCompetences() as $competence) {
            if (!array_key_exists($competence->getBlocCompetence()?->getId(), $bccs)) {
                $bccs[$competence->getBlocCompetence()?->getId()]['bcc'] = $competence->getBlocCompetence();
                $bccs[$competence->getBlocCompetence()?->getId()]['competences'] = [];
            }
            $bccs[$competence->getBlocCompetence()?->getId()]['competences'][] = $competence;
        }


        return $this->myPdf->render(
            'pdf/ec.html.twig',
            [
                'ficheMatiere' => $ficheMatiere,
                'formation' => $formation,
                'typeDiplome' => $typeDiplome,
                'typeEpreuves' => $typeD->getTypeEpreuves(),
                'templateForm' => $typeD->getMcccTemplate(),
                'bccs' => $bccs,
                'titre' => 'Fiche EC/matière ' . $ficheMatiere->getLibelle(),
                'mcccs' => $typeD->getMcccs($ficheMatiere),
                'typeMccc' => $ficheMatiere->getTypeMccc(),
            ],
            'dpe_fiche_matiere_' . $ficheMatiere->getLibelle()
        );
    }

    #[Route('/fiche-matiere/export/all/{parcours}', name: 'fiche_matiere_export_all')]
    public function exportFichesMatieres(
        Parcours $parcours,
        TypeDiplomeResolver $typeDResolver
    ): Response
    {
        $typeDHandler = $typeDResolver->get($parcours->getFormation()?->getTypeDiplome());

        return $this->myPdf->render(
            'pdf/ficheMatiereAll.html.twig',
            [
                'formation' => $parcours->getFormation(),
                'parcours' => $parcours,
                'fiches' => $parcours->getFicheMatieres(),
                'typeDiplome' => $parcours->getFormation()?->getTypeDiplome(),
                'titre' => 'Fiches EC/matières ',
                'typeEpreuves' => $typeDHandler->getTypeEpreuves(),
                'typeDiplomeHandler' => $typeDHandler,
                'templateFormMccc' => $typeDHandler->getMcccTemplate(),
            ],
            'FichesMatieres' . $parcours->getDisplay()
        );
    }

    #[Route('/fiche-matiere/export/zip/{parcours}', name: 'fiche_matiere_export_zip')]
    public function exportFichesMatieresZip(
        MessageBusInterface          $messageBus,
        Parcours $parcours): Response
    {
        $user = $this->getUser();
        $messageBus->dispatch(new Export(
            $user instanceof \App\Entity\User ? $user->getId() : null,
            'zip-fiches_matieres',
            [$parcours->getId()]
        ));

        return JsonReponse::success('Les documents sont en cours de génération, vous recevrez un mail lorsque les documents seront prêts');
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/fiche-matiere/export/generique/xlsx', name: 'fiche_matiere_export_generique_xlsx')]
    public function getExportGeneriqueXlsx(
        ExportGeneriqueFicheMatiere $exportFMGenerique,
        Request $request,
        MessageBusInterface $bus
    ){
        $parcoursIdArray = $request->query->all()['id'] ?? [];

        if(count($parcoursIdArray) > 50 || (($parcoursIdArray[0] ?? 'none') === 'all')){
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $bus->dispatch(new ExportGenerique(
                ['type' => 'fiche_matiere', 'format' => 'xlsx'],
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

        [$file, $filename] = $exportFMGenerique->generateXlsxSpreadsheet($request);

        return new Response(
                $file,
                200,
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Content-Disposition' => "attachment;filename=\"{$filename}\"",
                ]
            );
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/fiche-matiere/export/generique/pdf', name: 'fiche_matiere_export_generique_pdf')]
    public function getExportGeneriquePdf(
        ExportGeneriqueFicheMatiere $exportFMGenerique,
        Request $request,
        MessageBusInterface $bus
    ){
        $parcoursIdArray = $request->query->all()['id'] ?? [];
        if(count($parcoursIdArray) > 50 || (($parcoursIdArray[0] ?? 'none') === 'all')){
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $bus->dispatch(new ExportGenerique(
                ['type' => 'fiche_matiere', 'format' => 'pdf'],
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

        [$response, $name] = $exportFMGenerique->generatePdf($request);

        return new Response(
            $response,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment;filename="' . $name . '.pdf"'
            ]
        );
    }
}
