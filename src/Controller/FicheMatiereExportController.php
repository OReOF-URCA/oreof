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
use App\Entity\FicheMatiere;
use App\Entity\Parcours;
use App\Message\Export;
use App\Repository\TypeDiplomeRepository;
use App\Repository\TypeEpreuveRepository;
use App\TypeDiplome\Exceptions\TypeDiplomeNotFoundException;
use App\TypeDiplome\TypeDiplomeResolver;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
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
                'typeEpreuves' => $typeD !== null ? $typeD->getTypeEpreuves() : $typeEpreuveRepository->findAll(),
                'templateForm' => $typeD !== null ? $typeD::TEMPLATE_FORM_MCCC : 'licence.html.twig',
                'bccs' => $bccs,
                'titre' => 'Fiche EC/matière ' . $ficheMatiere->getLibelle(),
                'mcccs' => $typeD !== null ? $typeD->getMcccs($ficheMatiere) : [],
                'typeMccc' => $ficheMatiere->getTypeMccc(),
            ],
            'dpe_fiche_matiere_' . $ficheMatiere->getLibelle()
        );
    }

    #[Route('/fiche-matiere/export/all/{parcours}', name: 'fiche_matiere_export_all')]
    public function exportFichesMatieres(Parcours $parcours): Response
    {
        return $this->myPdf->render(
            'pdf/ficheMatiereAll.html.twig',
            [
                'formation' => $parcours->getFormation(),
                'parcours' => $parcours,
                'fiches' => $parcours->getFicheMatieres(),
                'typeDiplome' => $parcours->getFormation()?->getTypeDiplome(),
                'titre' => 'Fiches EC/matières ',
            ],
            'FichesMatieres' . $parcours->getDisplay()
        );
    }

    #[Route('/fiche-matiere/export/zip/{parcours}', name: 'fiche_matiere_export_zip')]
    public function exportFichesMatieresZip(
        MessageBusInterface          $messageBus,
        Parcours $parcours): Response
    {
        $messageBus->dispatch(new Export(
            $this->getUser()?->getId(),
            'zip-fiches_matieres',
            [$parcours->getId()]
        ));

        return JsonReponse::success('Les documents sont en cours de génération, vous recevrez un mail lorsque les documents seront prêts');
    }
}
