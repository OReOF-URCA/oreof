<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Classes/Export/ExportFicheMatiere.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 26/11/2023 14:53
 */

namespace App\Classes\Export;

use App\Classes\GetElementConstitutif;
use App\Classes\MyGotenbergPdf;
use App\Repository\ParcoursRepository;
use App\Service\ProjectDirProvider;
use App\Service\TypeDiplomeResolver;
use App\Utils\Tools;
use Exception;
use Symfony\Component\HttpKernel\KernelInterface;
use ZipArchive;

class ExportFicheMatiere
{
    private string $dir;
    public function __construct(
        ProjectDirProvider $projectDirProvider,
        protected MyGotenbergPdf     $myPdf,
        protected ParcoursRepository $parcoursRepository,
        protected TypeDiplomeResolver $typeDResolver
    )
    {
        $this->dir = $projectDirProvider->getProjectDir() . '/public/';
    }

    public function exportLink(array $idParcours): string
    {
        $parcours = $this->parcoursRepository->find($idParcours[0]);
        if ($parcours === null) {
            throw new Exception('Parcours non trouvé');
        }
        // $ecs = $parcours->getElementConstitutifs();
        $formation = $parcours->getFormation();
        if ($formation === null) {
            throw new Exception('Formation non trouvée');
        }
        $typeDiplome = $formation?->getTypeDiplome();
        $typeDHandler = $this->typeDResolver->get($typeDiplome);

        /**
         * Mise à plat de toutes les UE de tous les semestres, avec :
         *  - UE Enfants de deuxième niveau
         *  - UE Raccrochées
         */
        $dataFmArray = $typeDHandler->calculStructureParcours($parcours, false, false);
        $dataFmArray = array_merge(
            ...array_map(function($sem) {
            return 
                [   
                    ...$sem->ues,
                    ...array_merge(...array_map(fn($a) => $a->uesEnfants(), $sem->ues)),
                    ...array_merge(
                        ...array_map(
                            fn($b) => array_merge(
                                ...array_map(
                                    fn($c) => $c->uesEnfants(), 
                                    $b->uesEnfants()
                            )
                        ), $sem->ues)
                    )
                ];
        }, $dataFmArray->semestres));
        
        /**
         * Mise à plat des EC depuis les UE
         * 
         */
        $dataFmArray = array_merge(
            ...array_map(
                function($ue) {
                    return [
                        ...$ue->elementConstitutifs, 
                        ...array_merge(
                            ...array_map(
                                fn($ec) => $ec->elementsConstitutifsEnfants, 
                                $ue->elementConstitutifs
                        )
                        )
                    ];
                }
        , $dataFmArray));

        $dataFmArray = array_map(fn($elt) => $elt->elementConstitutif, $dataFmArray);

        $fichiers = [];
        foreach ($dataFmArray as $ec) {
            $getElement = new GetElementConstitutif($ec, $parcours);
            $ficheMatieres = $ec->getFicheMatiere();
            if ($ficheMatieres !== null) {
                $fichiers[] = $this->myPdf->renderAndSave(
                    'pdf/ficheMatiere.html.twig',
                    'pdftests/',
                    [
                        'ec' => $ec,
                        'formation' => $formation,
                        'semestre' => $ec->getUe()?->getSemestre(),
                        'parcours' => $parcours,
                        'typeDiplome' => $typeDiplome,
                        'titre' => 'Fiche EC/matière ' . $ficheMatieres->getLibelle(),
                        'heures' => $getElement->getFicheMatiereHeures(),
                        'templateFormMccc' => $typeDHandler::TEMPLATE_FORM_MCCC,
                        'mcccPdf' => $typeDHandler->getDisplayMccc(
                            $getElement->getMcccsFromFicheMatiere($typeDHandler) ?? [],
                            $getElement->getTypeMcccFromFicheMatiere() ?? ''
                        ),
                        'typeEpreuves' => $typeDHandler->getTypeEpreuves()

                    ],
                    Tools::FileName($ficheMatieres->getSlug())
                );
            }
        }

        $zip = new ZipArchive();
        $fileName = 'export_fiches_matieres_' . date('YmdHis') . '.zip';
        $zipName = $this->dir . 'temp/zip/' . $fileName;
        $zip->open($zipName, ZipArchive::CREATE);

        foreach ($fichiers as $fichier) {
            $zip->addFile(
                $this->dir . 'pdftests/' . $fichier,
                $fichier
            );
        }

        $zip->close();

        foreach ($fichiers as $fichier) {
            if (file_exists($this->dir. 'pdftests/' . $fichier)) {
                unlink($this->dir . 'pdftests/'. $fichier);
            }
        }

        return $fileName;
    }
}
