<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Classes/Export/ExportConseil.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 16/07/2023 10:48
 */

namespace App\Classes\Export;

use App\Entity\CampagneCollecte;
use DateTimeInterface;
use ZipArchive;

//todo: a reprendre ? pour exporter quoi ?
class ExportConseil
{
    /**
     * @param list<mixed> $formations
     */
    public function __construct(
        private string $dir,
        private array $formations = [],
        private ?CampagneCollecte $annee = null,
        private ?DateTimeInterface $date = null
    ) {
    }

    public function exportZip(): string
    {
        $zip = new ZipArchive();
        $fileName = 'export_conseil_' . date('YmdHis') . '.zip';
        $zipName = $this->dir . '/zip/' . $fileName;
        $zip->open($zipName, ZipArchive::CREATE);

        if ($this->annee !== null || $this->date !== null || $this->formations !== []) {
            // todo: migrer vers Gotenberg pour la génération des PDF de conseil
        }

        $zip->close();

        return $fileName;
    }
}
