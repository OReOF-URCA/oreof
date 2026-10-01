<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Classes/Export/ExportMccc.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 16/07/2023 10:49
 */

namespace App\Classes\Export;

use App\Entity\CampagneCollecte;
use App\Repository\DpeParcoursRepository;
use App\Repository\FormationRepository;
use App\TypeDiplome\TypeDiplomeResolver;
use App\Utils\Tools;
use DateTimeInterface;
use ZipArchive;

class ExportMccc
{
    private string $dir;
    private TypeDiplomeResolver $typeDiplomeResolver;
    private array $formations;
    private CampagneCollecte $annee;
    private ?DateTimeInterface $date = null;
    private string $format = 'xlsx';
    private bool $isLight = false;

    public function __construct(
        protected FormationRepository $formationRepository,
        protected DpeParcoursRepository $dpeParcoursRepository,
    ) {
    }

    public function exportZip(): string
    {
        $zip = new ZipArchive();
        $fileName = 'export_mccc_' . date('YmdHis') . '.zip';
        $zipName = $this->dir . '/zip/' . $fileName;
        $zip->open($zipName, ZipArchive::CREATE);

        $tabFiles = [];

        $dir = $this->dir;
        if ($this->format === 'xlsx') {
            $dir = $this->dir . '/mccc/';
        } elseif ($this->format === 'pdf') {
            $dir = $this->dir  . '/pdf/';
        }

        foreach ($this->formations as $formationId) {
            $formation = $this->formationRepository->findOneBy(['id' => $formationId, 'anneeUniversitaire' => $this->annee->getId()]);
            if ($formation !== null) {
                $typeDiplome = $this->typeDiplomeResolver->fromFormation($formation);
                foreach ($formation->getParcours() as $parcours)
                {
                        if ($this->format === 'xlsx') {
                            $fichier = $typeDiplome->exportAndSaveExcelMccc(
                                $dir,
                                $this->annee,
                                $parcours,
                                $this->date,
                                null,
                                $this->isLight
                            );
                        } elseif ($this->format === 'pdf') {
                            $fichier = $typeDiplome->exportAndSavePdfMccc(
                                $dir,
                                $this->annee,
                                $parcours,
                                $this->date,
                                null,
                                $this->isLight
                            );
                        }
                        if (!isset($fichier)) {
                            continue;
                        }
                        $tabFiles[] = $fichier;
                        $zip->addFile(
                            $dir . $fichier,
                            $formation->getDisplay() . '/' . $fichier
                        );
                    }
            }
        }

        $zip->close();
        // suppression des fichiers temporaires
        foreach ($tabFiles as $file) {
            if (file_exists($dir . $file)) {
                unlink($dir . $file);
            }
        }

        return $fileName;
    }

    public function exportVersionZip(): string
    {
        $zip = new ZipArchive();
        $fileName = 'export_mccc_versionnees_' . date('YmdHis') . '.zip';
        $zipName = $this->dir . '/zip/' . $fileName;
        $zip->open($zipName, ZipArchive::CREATE);

        $tabFiles = [];
        $dir = $this->dir . '/mccc/';


        foreach ($this->formations as $formationId) {
            $dpeParcours = $this->dpeParcoursRepository->findOneBy(['id' => $formationId, 'campagneCollecte' => $this->annee->getId()]);
            if ($dpeParcours === null) {
                continue;
            }

            $parcours = $dpeParcours->getParcours();
            if ($parcours === null) {
                continue;
            }
            $formation = $parcours->getFormation();

            if ($formation !== null) {
                $typeDiplome = $this->typeDiplomeResolver->fromFormation($formation);
                if ($formation->isHasParcours() === true) {
                        $texte = $formation->gettypeDiplome()?->getLibelleCourt() . ' ' . $formation->getSigle() . ' ' . $parcours->getSigle();
                    } else {
                        $texte = $formation->gettypeDiplome()?->getLibelleCourt() . ' ' . $formation->getSigle();
                    }

                    $fichierXlsx = Tools::FileName('MCCC - ' . $this->annee->getLibelle() . ' - ' . $texte);
                    $fichier = $typeDiplome->exportExcelAndSaveVersionMccc(
                        $this->annee,
                        $parcours,
                        $dir,
                        $fichierXlsx
                    );

                    $tabFiles[] = $fichier;
                    $zip->addFile(
                        $dir . $fichier,
                        $fichier
                    );
            }
        }

        $zip->close();
        // suppression des fichiers temporaires
        foreach ($tabFiles as $file) {
            if (file_exists($dir . $file)) {
                unlink($dir . $file);
            }
        }

        return $fileName;
    }

    public function export(
        string              $dir,
        TypeDiplomeResolver $typeDiplomeResolver,
        array               $formations,
        CampagneCollecte    $annee,
        DateTimeInterface   $date,
        string              $format = 'xlsx',
        bool                $isLight = false
    ): void {
        $this->dir = $dir;
        $this->typeDiplomeResolver = $typeDiplomeResolver;
        $this->formations = $formations;
        $this->annee = $annee;
        $this->date = $date;
        $this->format = $format;
        $this->isLight = $isLight;
    }

    public function exportVersion(string $dir, TypeDiplomeResolver $typeDiplomeResolver, array $formations, CampagneCollecte $campagneCollecte): void
    {
        $this->dir = $dir;
        $this->typeDiplomeResolver = $typeDiplomeResolver;
        $this->formations = $formations;
        $this->annee = $campagneCollecte;
        $this->format = 'xlsx';
        $this->isLight = false;
    }
}
