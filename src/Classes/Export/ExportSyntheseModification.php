<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Classes/Export/ExportSynthese.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 11/11/2023 13:07
 */

namespace App\Classes\Export;

use App\Classes\MyGotenbergPdf;
use App\Entity\CampagneCollecte;
use App\Repository\FormationRepository;
use App\Repository\ParcoursRepository;
use App\Repository\TypeEpreuveRepository;
use App\Service\ProjectDirProvider;
use App\Service\VersioningParcours;
use App\Service\VersioningStructureExtractDiff;
use App\TypeDiplome\Dto\OptionsCalculStructure;
use App\TypeDiplome\TypeDiplomeResolver;
use App\Utils\Tools;
use ZipArchive;

class ExportSyntheseModification
{
    private string $dir;

    public function __construct(
        protected ParcoursRepository $parcoursRepository,
        protected TypeEpreuveRepository $typeEpreuveRepository,
        protected TypeDiplomeResolver $typeDiplomeResolver,
        protected VersioningParcours $versioningParcours,
        protected MyGotenbergPdf      $myGotenbergPdf,
        ProjectDirProvider $projectDirProvider,
        protected FormationRepository $formationRepository,
    ) {
        $this->dir = $projectDirProvider->getProjectDir() . '/public/';
    }

    public function exportLink(array $formations, CampagneCollecte $campagneCollecte): string
    {
        $typeEpreuves = [];
        $epreuves = $this->typeEpreuveRepository->findAll();
        foreach ($epreuves as $epreuve) {
            $typeEpreuves[$epreuve->getId()] = $epreuve;
        }
        $fichiers = [];
        foreach ($formations as $formation) {
            $tDemandes = [];

            $form = $formation['formation'];
            if ($formation['hasModif'] === true) {
                foreach ($formation['parcours'] as $parc) {
                    $diffStructure = null;
                    $parco = $this->parcoursRepository->find($parc['parcours']->getId());
                    if ($parc['parcours']->getParcoursOrigineCopie() === null) {
                        $dto = null;
                    } else {
                        $typeD = $this->typeDiplomeResolver->fromFormation($form);
                        $parco = $this->parcoursRepository->find($parc['parcours']->getId());
                        if ($parco === null) {
                            continue;
                        }
                        $dto = $typeD->calcul($parco, new OptionsCalculStructure(withEcts: true, withBcc: false));
                        $structureDifferencesParcours = $this->versioningParcours->getStructureDifferencesBetweenParcoursAndLastVersion($parco);
                        if ($structureDifferencesParcours !== null) {
                            $diffStructure = new VersioningStructureExtractDiff($structureDifferencesParcours, $dto, $typeEpreuves);
                            $diffStructure->extractDiff();
                        } else {
                            $diffStructure = null;
                        }
                    }

                    $tDemandes[] = [
                        'formation' => $form,
                        'composante' => $formation['composante'],
                        'dpeDemandeFormation' => $formation['dpeDemande'],
                        'dpeDemandeParcours' => $parc['dpeDemande'],
                        'parcours' => $parco,
                        'diffStructure' => $diffStructure,
                        'dto' => $dto
                    ];
                }

                $fichiers[] = $this->myGotenbergPdf->renderAndSave(
                    'pdf/synthese_modifications.html.twig',
                    'pdftests/',
                    [
                        'titre' => 'Liste des demandes de changement MCCC et maquettes',
                        'demandes' => $tDemandes,
                        'dpe' => $campagneCollecte,
                    ],
                    Tools::FileName($form->getSlug())
                );
            }
        }

        $zip = new ZipArchive();
        $fileName = 'synthese_modification_cfvu_' . date('YmdHis') . '.zip';
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
            if (file_exists($this->dir . 'pdftests/' . $fichier)) {
                unlink($this->dir . 'pdftests/' . $fichier);
            }
        }

        return $fileName;
    }
}
