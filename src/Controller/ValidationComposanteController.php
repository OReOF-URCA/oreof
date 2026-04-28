<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/ValidationComposanteController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 26/05/2025 16:32
 */

namespace App\Controller;

use App\Classes\Excel\ExcelWriter;
use App\Classes\ValidationProcess;
use App\Classes\ValidationProcessChangeRf;
use App\Classes\ValidationProcessFicheMatiere;
use App\Entity\Composante;
use App\Entity\CampagneCollecte;
use App\Repository\CampagneCollecteRepository;
use App\Repository\ChangeRfRepository;
use App\Repository\ComposanteRepository;
use App\Repository\DpeParcoursRepository;
use App\Repository\FicheMatiereRepository;
use App\Utils\Tools;
use DateTime;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

#[Route('/validation/composante/', name: 'app_validation_composante_')]
class ValidationComposanteController extends BaseController
{

    private const ETAPES_SANS_ACTIONS = [
        'soumis_cfvu',
        'refuse_central',
        'soumis_cfvu',
        'refuse_definitif_cfvu',
        'valide_cfvu',
        'valide_a_publier',
        'publie',
        'soumis_central_sans_cfvu',
        'soumis_central_reserve_cfvu',
        'non_ouverture_ses',
        'non_ouverture',
    ];

    #[Route('/validation/fiche/export', name: 'app_validation_verification_fiche_export')]
    //todo: gérer la composante
    public function ficheExportVerification(
        ExcelWriter            $excelWriter,
        FicheMatiereRepository $ficheMatiereRepository,
    ): Response
    {
        $fiches[] = $ficheMatiereRepository->findByTypeValidation($this->getCampagneCollecte(), 'fiche_matiere');
        $fiches[] = $ficheMatiereRepository->findByTypeValidationNull($this->getCampagneCollecte());
        $fiches = array_merge(...$fiches);
        $excelWriter->nouveauFichier('Export Vérification');
        $excelWriter->setActiveSheetIndex(0);

        $excelWriter->writeCellXY(1, 1, 'Composante');
        $excelWriter->writeCellXY(2, 1, 'Type Diplôme');
        $excelWriter->writeCellXY(3, 1, 'Mention');
        $excelWriter->writeCellXY(4, 1, 'Parcours');
        $excelWriter->writeCellXY(5, 1, 'Fiche');
        $excelWriter->writeCellXY(6, 1, 'Responsable');

        $ligne = 2;

        foreach ($fiches as $fiche) {
            if ($fiche->getParcours() !== null && $fiche->getElementConstitutifs()->count() > 0) {

                $parcours = $fiche->getParcours();
                $formation = $parcours?->getFormation();
                $composante = $formation?->getComposantePorteuse();
                $responsable = $fiche->getResponsableFicheMatiere();

                $excelWriter->writeCellXY(1, $ligne, $composante ? $composante?->getLibelle() : 'Pas de composante');
                $excelWriter->writeCellXY(2, $ligne, $formation ? $formation?->getTypeDiplome()?->getLibelle() : 'Pas de formation');
                $excelWriter->writeCellXY(3, $ligne, $formation ? $formation?->getDisplay() : 'Pas de formation');
                $excelWriter->writeCellXY(4, $ligne, $parcours ? $parcours->getLibelle() : 'Pas de parcours');
                $excelWriter->writeCellXY(5, $ligne, $fiche->getLibelle());
                $excelWriter->writeCellXY(6, $ligne, $responsable ? $responsable->getNom() . ' ' . $responsable->getPrenom() : 'Pas de responsable');

                $ligne++;
            }
        }


        $fileName = Tools::FileName('Verif-fiche-' . (new DateTime())->format('d-m-Y-H-i'));
        return $excelWriter->genereFichier($fileName, true);
    }

    #[Route('{composante}/dpe', name: 'dpe_index')]
    public function composante(
        DpeParcoursRepository $dpeParcoursRepository,
        Request           $request,
        Composante        $composante,
        ValidationProcess $validationProcess,
    ): Response
    {

        $steps = $validationProcess->getProcessAll();

        //statstics
        // parcourir tous les parcours et compter les états
        $statistiques = [];
        $allParcours = $dpeParcoursRepository->findByComposanteAndCampagne($composante, $this->getCampagneCollecte());
        foreach ($allParcours as $parcours) {
            $etat = array_keys($parcours->getEtatValidation())[0];
            if (!isset($statistiques[$etat])) {
                $statistiques[$etat]['nbParcours'] = 0;
                $statistiques[$etat]['formations'] = [];
            }
            $statistiques[$etat]['nbParcours']++;
            if (!in_array($parcours->getParcours()?->getFormation()?->getId(), $statistiques[$etat]['formations'], true)) {
                $statistiques[$etat]['formations'][] = $parcours->getParcours()?->getFormation()?->getId();
            }
        }

        return $this->render('validation-composante/dpe.html.twig', [
            'composante' => $composante,
            'steps' => $steps,
            'statistiques' => $statistiques,
        ]);
    }

    #[Route('{composante}/pilotage', name: 'pilotage')]
    #[Route('{composante}/pilotage/{campagneCollecte}', name: 'pilotage_campagne')]
    public function pilotage(
        Composante                 $composante,
        DpeParcoursRepository      $dpeParcoursRepository,
        CampagneCollecteRepository $campagneCollecteRepository,
        ChartBuilderInterface      $chartBuilder,
        ?CampagneCollecte          $campagneCollecte = null,
    ): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->denyAccessUnlessGranted('SHOW', [
                'route' => 'app_composante',
                'subject' => $composante,
            ]);
        }

        $campagne = $campagneCollecte ?? $this->getCampagneCollecte();
        $stats = $dpeParcoursRepository->getPilotageStatsByComposanteAndCampagne($composante, $campagne);

        $chartRepartition = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $chartRepartition->setData([
            'labels' => ['Parcours ouverts', 'Parcours validés', 'Parcours non ouverts', 'Autres statuts'],
            'datasets' => [[
                'label' => 'Repartition des parcours',
                'backgroundColor' => ['#3B82F6', '#10B981', '#EF4444', '#94A3B8'],
                'data' => [
                    $stats['nbParcoursOuverts'],
                    $stats['nbParcoursValides'],
                    $stats['nbParcoursNonOuverts'],
                    $stats['nbParcoursAutres'],
                ],
            ]],
        ]);

        $workflowLabels = array_keys($stats['workflowCounts']);
        $workflowData = array_values($stats['workflowCounts']);
        if (count($workflowLabels) === 0) {
            $workflowLabels = ['Aucune donnee'];
            $workflowData = [0];
        }

        $chartWorkflow = $chartBuilder->createChart(Chart::TYPE_BAR);
        $chartWorkflow->setData([
            'labels' => $workflowLabels,
            'datasets' => [[
                'label' => 'Parcours par etape workflow',
                'backgroundColor' => '#6366F1',
                'data' => $workflowData,
            ]],
        ]);

        $chartWorkflow->setOptions([
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => ['display' => false],
            ],
        ]);

        return $this->render('validation-composante/pilotage.html.twig', [
            'composante' => $composante,
            'campagne' => $campagne,
            'campagnes' => $campagneCollecteRepository->findBy([], ['annee' => 'DESC']),
            'stats' => $stats,
            'chartRepartition' => $chartRepartition,
            'chartWorkflow' => $chartWorkflow,
        ]);
    }

    #[Route('{composante}/change-rf', name: 'change_rf_index')]
    public function changeRf(
        Request                   $request,
        Composante                $composante,
        ValidationProcessChangeRf $validationProcessChangeRf,
    ): Response
    {
        $typeValidation = $request->query->get('typeValidation');

        return $this->render('validation-composante/change_rf.html.twig', [
            'composante' => $composante,
            'types_validation' => $validationProcessChangeRf->getProcess(),//faire un getProcesssComposante pour filtrer par niveau composante,
            'typeValidation' => $typeValidation,

        ]);
    }

    #[Route('{composante}/fiche-matiere', name: 'fiche_index')]
    public function ficheMatiere(
        Request                       $request,
        Composante                    $composante,
        ValidationProcessFicheMatiere $validationProcessFicheMatiere,
    ): Response
    {
        $typeValidation = $request->query->get('typeValidation');

        return $this->render('validation-composante/fiche_matiere.html.twig', [
            'composante' => $composante,
            'types_validation' => $validationProcessFicheMatiere->getProcess(),
            'typeValidation' => $typeValidation,

        ]);
    }

    #[Route('{composante}/dpe/liste', name: 'dpe_liste')]
    public function dpeListe(
        ValidationProcess     $validationProcess,
        DpeParcoursRepository $dpeParcoursRepository,
        Composante            $composante,
        Request               $request
    ): Response
    {
        $typeValidation = $request->query->get('typeValidation');
        $process = $validationProcess->getEtape($typeValidation);

        if (!$composante) {
            throw $this->createNotFoundException('La composante n\'existe pas');
        }
        $allparcours = $dpeParcoursRepository->findByComposanteAndCampagneAndTypeValidation($composante, $this->getCampagneCollecte(), $typeValidation);

        $nbParcours = count($allparcours);

        $tFormations = [];
        foreach ($allparcours as $parcours) {
            $tFormations[] = $parcours->getParcours()?->getFormation()?->getId();
        }

        $hideLotButtons = !$this->isGranted('ROLE_ADMIN') && in_array($typeValidation, self::ETAPES_SANS_ACTIONS, true);

        // valeurs uniques dans tFormations
        $nbFormations = count(array_unique($tFormations));

        return $this->render('validation/_liste.html.twig', [
            'nbFormations' => $nbFormations,
            'nbParcours' => $nbParcours,
            'process' => $process,
            'allparcours' => $allparcours,
            'etape' => $typeValidation ?? null,
            'hideLotButtons' => $hideLotButtons,
        ]);
    }

    #[Route('{composante}/change-rf/liste', name: 'change_rf_liste')]
    public function changeRfListe(
        ValidationProcessChangeRf $validationProcess,
        ChangeRfRepository        $changeRfRepository,
        ComposanteRepository      $composanteRepository,
        Composante                $composante,
        Request                   $request
    ): Response
    {
        $typeValidation = $request->query->get('typeValidation');
        if (!$composante) {
            throw $this->createNotFoundException('La composante n\'existe pas');
        }

        $demandes = $changeRfRepository->findByComposanteTypeValidation(
            $composante,
            $this->getCampagneCollecte(),
            $typeValidation
        );


        return $this->render('validation/_listeChangeRf.html.twig', [
            'demandes' => $demandes,
            'etape' => $typeValidation ?? null,
        ]);
    }

    #[Route('/{composante}/fiche-matiere/liste', name: 'fiche_liste')]
    public function ficheListe(
        ValidationProcessFicheMatiere $validationProcessFicheMatiere,
        ComposanteRepository          $composanteRepository,
        FicheMatiereRepository        $ficheMatiereRepository,
        Request                       $request,
        COmposante                    $composante,
    ): Response
    {
        $typeValidation = $request->query->get('typeValidation');
        $process = $validationProcessFicheMatiere->getEtape($typeValidation);

        if (!$composante) {
            throw $this->createNotFoundException('La composante n\'existe pas');
        }
        $fiches = $ficheMatiereRepository->findByComposanteTypeValidation($composante, $this->getCampagneCollecte(), $typeValidation);

        return $this->render('validation/_listeFiches.html.twig', [
            'process' => $process,
            'fiches' => $fiches,
            'etape' => $typeValidation ?? null,
        ]);
    }
}
