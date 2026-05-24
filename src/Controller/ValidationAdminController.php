<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/ValidationAdminController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/09/2025 20:58
 */

namespace App\Controller;

use App\Classes\Excel\ExcelWriter;
use App\Classes\ValidationProcess;
use App\Classes\ValidationProcessChangeRf;
use App\Classes\ValidationProcessFicheMatiere;
use App\Entity\DpeParcours;
use App\Repository\ChangeRfRepository;
use App\Repository\ComposanteRepository;
use App\Repository\DpeParcoursRepository;
use App\Repository\FicheMatiereRepository;
use App\Service\DataTableBuilder;
use App\Utils\Tools;
use DateTime;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/validation/administration/', name: 'app_validation_')]
#[IsGranted('ROLE_ADMIN')]
class ValidationAdminController extends BaseController
{
    #[Route('/validation/fiche/export', name: 'verification_fiche_export')]
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

    #[Route('dpe', name: 'dpe_index')]
    public function dpe(
        DpeParcoursRepository $dpeParcoursRepository,
        ComposanteRepository  $composanteRepository,
        ValidationProcess     $validationProcess,
    ): Response
    {
        $steps = $validationProcess->getProcessAll();

        //statstics
        // parcourir tous les parcours et compter les états
        $statistiques = [];
        $allParcours = $dpeParcoursRepository->findByCampagneCollecte($this->getCampagneCollecte());
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


        return $this->render('validation/dpe.html.twig', [
            'composantes' => $composanteRepository->findPorteuse(),
            'steps' => $steps,
            'statistiques' => $statistiques,
        ]);
    }

    //    #[Route('dpe', name: 'dpe_index')]
    //    public function dpe(
    //        ComposanteRepository $composanteRepository,
    //        Request              $request,
    //        ValidationProcess    $validationProcess,
    //    ): Response
    //    {
    //
    //        $typeValidation = $request->query->get('typeValidation');
    //
    //        return $this->render('dpe_old.html.twig', [
    //            'types_validation' => $validationProcess->getProcessAll(),
    //            'typeValidation' => $typeValidation,
    //            'composantes' => $composanteRepository->findPorteuse(),
    //        ]);
    //    }

    #[Route('change-rf', name: 'change_rf_index')]
    public function changeRf(
        ChangeRfRepository $changeRfRepository,
        Request                   $request,
        ValidationProcessChangeRf $validationProcessChangeRf,
    ): Response
    {
        $typeValidation = $request->query->get('typeValidation');

        $statistiques = [];

        $fiches = $changeRfRepository->findByCampagneCollecteForStats($this->getCampagneCollecte());
        foreach ($fiches as $fiche) {
            $keys = array_keys($fiche['etat'] ?? []);
            $etat = $keys[0] ?? null;
            $statistiques[$etat] = $fiche['nb'];
        }

        return $this->render('validation/change_rf.html.twig', [
            'steps' => $validationProcessChangeRf->getProcessAll(),//faire un getProcesssComposante pour filtrer par niveau composante,
            'typeValidation' => $typeValidation,
            'statistiques' => $statistiques
        ]);
    }

    #[Route('fiche-matiere', name: 'fiche_index')]
    public function ficheMatiere(
        FicheMatiereRepository $ficheMatiereRepository,
        Request                       $request,
        ValidationProcessFicheMatiere $validationProcessFicheMatiere,
    ): Response
    {

        $statistiques = [];
        foreach ($validationProcessFicheMatiere->getProcess() as $key => $etape) {
            $statistiques[$key] = 0;
        }

        $fiches = $ficheMatiereRepository->findByCampagneCollecteForStats($this->getCampagneCollecte());
        //dump($fiches);
        foreach ($fiches as $fiche) {
            $keys = array_keys($fiche['etat'] ?? []);
            $etat = $keys[0] ?? null;
            $statistiques[$etat] = $fiche['nb'];
        }

        return $this->render('validation/fiche_matiere.html.twig', [
            'steps' => $validationProcessFicheMatiere->getProcessAll(),
            'statistiques' => $statistiques,
        ]);
    }

    #[Route('dpe/liste', name: 'dpe_liste')]
    public function dpeListe(
        ComposanteRepository $composanteRepository,
        ValidationProcess     $validationProcess,
        DpeParcoursRepository $dpeParcoursRepository,
        DataTableBuilder $builder,
        Request               $request
    ): Response
    {
        $typeValidation = $request->query->get('typeValidation', $request->query->get('value'));
        if (!is_string($typeValidation) || $typeValidation === '') {
            return $this->render('validation/_liste_datatable.html.twig', [
                'table' => null,
                'nbFormations' => 0,
                'nbParcours' => 0,
                'etape' => null,
                'process' => null,
            ]);
        }

        $idComposante = $request->query->get('composante', null);
        $process = $validationProcess->getEtape($typeValidation);

        if ($idComposante) {
            $composante = $composanteRepository->find($idComposante);
        } else {
            $composante = null;
        }

        $allparcours = $dpeParcoursRepository->findByCampagneAndTypeValidation($this->getCampagneCollecte(), $typeValidation, $composante);

        $nbParcours = count($allparcours);

        $tFormations = [];
        foreach ($allparcours as $parcours) {
            $tFormations[] = $parcours->getParcours()?->getFormation()?->getId();
        }

        // valeurs uniques dans tFormations
        $nbFormations = count(array_unique($tFormations));

        $campagneCollecte = $this->getCampagneCollecte();
        if ($campagneCollecte === null) {
            $builder->addBaseWhere('1 = 0');
        } else {
            $builder
                ->addBaseWhere('IDENTITY(e.campagneCollecte) = :campagneCollecteId')
                ->addBaseParameter('campagneCollecteId', $campagneCollecte->getId())
                ->addBaseWhere('JSON_CONTAINS(e.etatValidation, :etatDpe) = 1')
                ->addBaseParameter('etatDpe', json_encode([$typeValidation => 1]));
        }

        if ($composante !== null) {
            $builder
                ->addBaseWhere('IDENTITY(formation_0.composantePorteuse) = :composanteId')
                ->addBaseParameter('composanteId', $composante->getId());
        }

        $table = $builder
            ->setEntity(DpeParcours::class)
            ->setPerPage(20)
            ->setDefaultSort('formation.composantePorteuse.libelle')
            ->enableCheckboxSelection([
                'name' => 'parcours[]',
                'valueField' => 'id',
                'inputClass' => 'validation-dpe-item',
                'allId' => 'validation-dpe-all',
            ])
            ->addColumn('formation.composantePorteuse.libelle', [
                'label' => 'Composante',
                'sortable' => true,
                'filterable' => true,
                'sort_expression' => 'composantePorteuse_1.libelle',
                'filter_expression' => 'composantePorteuse_1.libelle',
                'class' => 'min-w-[12rem]',
            ])
            ->addColumn('parcours.libelle', [
                'label' => 'Formation / Parcours',
                'sortable' => true,
                'filterable' => true,
                'searchable' => true,
                'template' => 'validation/datatable/_formation_compact.html.twig',
                'class' => 'min-w-[20rem]',
            ])
            ->addColumn('etatValidation', [
                'label' => 'Suivi',
                'sortable' => false,
                'filterable' => true,
                'searchable' => false,
                'template' => 'validation/datatable/_suivi_compact.html.twig',
                'class' => 'min-w-[15rem]',
            ])
            ->addColumn('parcours.id', [
                'label' => 'BCC',
                'sortable' => false,
                'filterable' => false,
                'searchable' => false,
                'template' => 'validation/datatable/_bcc.html.twig',
                'class' => 'min-w-[8rem]',
            ])
            ->addColumn('formation.id', [
                'label' => 'MCCC',
                'sortable' => false,
                'filterable' => true,
                'searchable' => false,
                'template' => 'validation/datatable/_maquette.html.twig',
                'class' => 'min-w-[8rem]',
            ])
            ->addColumn('parcours.remplissage', [
                'label' => 'Remplissage',
                'sortable' => true,
                'filterable' => true,
                'searchable' => false,
                'template' => 'validation/datatable/_remplissage.html.twig',
            ])
            ->addColumn('id', [
                'label' => 'Actions',
                'sortable' => false,
                'filterable' => false,
                'searchable' => false,
                'template' => 'validation/datatable/_actions.html.twig',
                'class' => 'text-right min-w-[11rem]',
            ])
            ->build();

        return $this->render('validation/_liste_datatable.html.twig', [
            'table' => $table,
            'nbFormations' => $nbFormations,
            'nbParcours' => $nbParcours,
            'process' => $process,
            'etape' => $typeValidation ?? null,
            'oneParcours' => $allparcours[0] ?? null,
        ]);
    }

    #[Route('change-rf/liste', name: 'change_rf_liste')]
    public function changeRfListe(
        ValidationProcessChangeRf $validationProcess,
        ChangeRfRepository        $changeRfRepository,
        Request                   $request
    ): Response
    {
        $typeValidation = $request->query->get('typeValidation');

        $demandes = $changeRfRepository->findByTypeValidation(
            $typeValidation,
            $this->getCampagneCollecte()
        );

        return $this->render('validation/_listeChangeRf.html.twig', [
            'demandes' => $demandes,
            'etape' => $typeValidation ?? null,
        ]);
    }

    #[Route('/fiche-matiere/liste', name: 'fiche_liste')]
    public function ficheListe(
        ValidationProcessFicheMatiere $validationProcessFicheMatiere,
        FicheMatiereRepository        $ficheMatiereRepository,
        Request                       $request,
    ): Response
    {
        $typeValidation = $request->query->get('typeValidation');
        $process = $validationProcessFicheMatiere->getEtape($typeValidation);

        $fiches = $ficheMatiereRepository->findByTypeValidation($this->getCampagneCollecte(), $typeValidation);

        return $this->render('validation/_listeFiches.html.twig', [
            'process' => $process,
            'fiches' => $fiches,
            'etape' => $typeValidation ?? null,
        ]);
    }
}
