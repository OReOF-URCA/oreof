<?php
/*
 * Copyright (c) 2024. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Classes/Export/ExportCodification.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 18/01/2024 17:23
 */

namespace App\Classes\Export;

use App\Classes\Excel\ExcelWriter;
use App\DTO\StructureSemestre;
use App\DTO\StructureUe;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\TypeDiplome\Dto\OptionsCalculStructure;
use App\TypeDiplome\TypeDiplomeResolver;
use App\Utils\Tools;
use DateTime;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportCodification
{
    const BLUE = '#4472C4';
    const GREEN = '#00B050';

    public function __construct(
        protected TypeDiplomeResolver $typeDiplomeResolver,
        protected ExcelWriter         $excelWriter
    ) {
    }

    public function exportFormations(array $formations): StreamedResponse
    {
        $this->excelWriter->nouveauFichier('Export Codification');
        $this->excelWriter->setActiveSheetIndex(0);

        $this->excelWriter->writeCellXY(1, 1, 'Composante');
        $this->excelWriter->writeCellXY(2, 1, 'Mention');
        $this->excelWriter->writeCellXY(3, 1, 'Parcours');
        $this->excelWriter->writeCellXY(4, 1, 'Domaine');
        $this->excelWriter->writeCellXY(5, 1, 'Ville');
        $this->excelWriter->writeCellXY(6, 1, 'Code Diplôme');
        $this->excelWriter->writeCellXY(7, 1, 'Version Diplôme');
        $this->excelWriter->writeCellXY(8, 1, 'Année d\'étude');
        $this->excelWriter->writeCellXY(9, 1, 'Code Etape');
        $this->excelWriter->writeCellXY(10, 1, 'Version Etape');

        $ligne = 2;


        foreach ($formations as $formation) {
            if ($formation->isHasParcours()) {
                foreach ($formation->getParcours() as $parcours) {
                    if (null !== $parcours) {
                        foreach ($parcours->getAnnees() as $annee) {
                            $this->excelWriter->writeCellXY(1, $ligne, $formation->getComposantePorteuse()?->getLibelle());
                            $this->excelWriter->writeCellXY(2, $ligne, $formation->getDisplayLong());
                            $this->excelWriter->writeCellXY(3, $ligne, $parcours->getLibelle());
                            $this->excelWriter->writeCellXY(4, $ligne, $formation->getMention()?->getDomaine()?->getLibelle());
                            $this->excelWriter->writeCellXY(5, $ligne, $parcours->getLocalisation()?->getLibelle());
                            $this->excelWriter->writeCellXY(6, $ligne, $parcours->getCodeDiplome($annee));
                            $this->excelWriter->writeCellXY(7, $ligne, $parcours->getCodeVersionDiplome($annee));
                            $this->excelWriter->writeCellXY(8, $ligne, 'Année ' . $annee);
                            $this->excelWriter->writeCellXY(9, $ligne, $parcours->getCodeEtape($annee));
                            $this->excelWriter->writeCellXY(10, $ligne, $parcours->getCodeVersionEtape($annee));
                            $ligne++;
                        }
                    }
                }
            } else {
                $parcours = $formation->defaultParcours();
                if (null !== $parcours) {
                    foreach ($parcours->getAnnees() as $annee) {
                        $this->excelWriter->writeCellXY(1, $ligne, $formation->getComposantePorteuse()?->getLibelle());
                        $this->excelWriter->writeCellXY(2, $ligne, $formation->getDisplayLong());
                        $this->excelWriter->writeCellXY(3, $ligne, $parcours->getLibelle());
                        $this->excelWriter->writeCellXY(4, $ligne, $formation->getMention()?->getDomaine()?->getLibelle());
                        $this->excelWriter->writeCellXY(5, $ligne, $parcours->getLocalisation()?->getLibelle());
                        $this->excelWriter->writeCellXY(6, $ligne, $parcours->getCodeDiplome($annee));
                        $this->excelWriter->writeCellXY(7, $ligne, $parcours->getCodeVersionDiplome($annee));
                        $this->excelWriter->writeCellXY(8, $ligne, 'Année ' . $annee);
                        $this->excelWriter->writeCellXY(9, $ligne, $parcours->getCodeEtape($annee));
                        $this->excelWriter->writeCellXY(10, $ligne, $parcours->getCodeVersionEtape($annee));
                        $ligne++;
                    }
                }
            }
        }

        $this->excelWriter->getColumnsAutoSize('A', 'M');



        $fileName = Tools::FileName('Codification-OReOF' . (new DateTime())->format('d-m-Y-H-i'), 30);
        return $this->excelWriter->genereFichier($fileName, true);
    }

    public function exportParcours(Parcours $parcours): StreamedResponse
    {
        $this->excelWriter->nouveauFichier('Export Codification');
        $this->excelWriter->setActiveSheetIndex(0);

        $this->writeParcours($parcours);

        $fileName = Tools::FileName('Codification-Parcours-' . $parcours->getLibelle() . '-' . (new DateTime())->format('d-m-Y-H-i'));
        return $this->excelWriter->genereFichier($fileName, true);
    }

    private function writeParcours(Parcours $parcours): void
    {
        $dto = $this->typeDiplomeResolver->fromParcours($parcours)->calcul(
            $parcours,
            new OptionsCalculStructure(withEcts: true, withBcc: false)
        );

        $this->excelWriter->writeCellXY(1, 1, 'Diplôme ' . $parcours->getFormation()->getDisplayLong());
        $this->excelWriter->writeCellXY(1, 2, 'Parcours ' . $parcours->getLibelle());

        // fusion des cellules
        $this->excelWriter->mergeCells('A1:J1');
        $this->excelWriter->mergeCells('A2:J2');

        $this->excelWriter->writeCellXY(1, 3, 'Dip ');
        $this->excelWriter->writeCellXY(2, 3, $parcours->getCodeDiplome(null));
        $this->excelWriter->writeCellXY(3, 3, 'VDI ');
        $this->excelWriter->writeCellXY(4, 3, $parcours->getCodeVersionDiplome(null));

        //todo: ajouter étape année
        $ligne = 4;
        /** @var StructureSemestre $semestre */
        foreach ($dto->semestres as $semestre) {
            if ($semestre->semestre->isNonDispense() === false) {
                // gérer les années
                if ($semestre->ordre % 2 === 1) {
                    $ligne++;
                    $this->excelWriter->writeCellXY(1, $ligne, 'Année ' . $semestre->getAnnee());
                    $this->excelWriter->writeCellXY(2, $ligne, $semestre->semestreParcours->getCodeApogeeEtapeAnnee(), ['bold' => true]);
                    $this->excelWriter->writeCellXY(3, $ligne, 'VET');
                    $this->excelWriter->writeCellXY(4, $ligne, $semestre->semestreParcours->getCodeApogeeEtapeVersion(), ['bold' => true]);

                }


                $ligne++;
                $this->excelWriter->writeCellXY(2, $ligne, 'Semestre ' . $semestre->ordre);
                $this->excelWriter->writeCellXY(3, $ligne, $semestre->semestre->getCodeApogee(), ['bold' => true]);
                $this->excelWriter->writeCellXY(4, $ligne, 'SEMESTRE', ['color' => self::BLUE]);
                $this->excelWriter->writeCellXY(5, $ligne, $semestre->heuresEctsSemestre->sommeSemestreEcts, ['color' => self::GREEN]);
                foreach ($semestre->ues as $ue) {
                    $ligne++;
                    if ($ue->ue->getUeParent() === null) {
                        $this->excelWriter->writeCellXY(3, $ligne, $ue->display);
                        $this->excelWriter->writeCellXY(4, $ligne, $ue->getCodeApogee(), ['bold' => true]);
                        if ($ue->ue->getNatureUeEc()?->isChoix()) {
                            $this->excelWriter->writeCellXY(5, $ligne, 'CHOIX', ['color' => self::BLUE]);
                            //todo: ajouter lib court, long, ...
                            foreach ($ue->uesEnfants() as $uesEnfant) {
                                $ligne++;
                                $this->excelWriter->writeCellXY(4, $ligne, $uesEnfant->display);
                                $this->excelWriter->writeCellXY(5, $ligne, $uesEnfant->getCodeApogee(), ['bold' => true]);
                                if ($uesEnfant->ue->getNatureUeEc()?->isLibre()) {
                                    $this->excelWriter->writeCellXY(6, $ligne, 'CHOIX', ['color' => self::BLUE]);
                                } else {
                                    $this->excelWriter->writeCellXY(6, $ligne, 'UE', ['color' => self::BLUE]);
                                }
                                $this->excelWriter->writeCellXY(7, $ligne, $uesEnfant->heuresEctsUe->sommeUeEcts, ['color' => self::GREEN]);
                                $ligne = $this->writeEcs($uesEnfant, $ligne, 5);
                            }
                        } else {
                            if ($ue->ue->getNatureUeEc()?->isLibre()) {
                                $this->excelWriter->writeCellXY(5, $ligne, 'CHOIX', ['color' => self::BLUE]);
                            } else {
                                $this->excelWriter->writeCellXY(5, $ligne, 'UE', ['color' => self::BLUE]);
                            }

                            $this->excelWriter->writeCellXY(6, $ligne, $ue->heuresEctsUe->sommeUeEcts, ['color' => self::GREEN]);
                            $ligne = $this->writeEcs($ue, $ligne, 5);
                        }
                    }
                }
            }
        }


        $this->excelWriter->getColumnsAutoSize('A', 'N');
    }

    private function writeEcs(StructureUe $ue, int $ligne, int $col): int
    {
        foreach ($ue->elementConstitutifs as $ec) {
            if ($ec->elementConstitutif->getEcParent() === null) {
                $ligne++;
                $this->excelWriter->writeCellXY($col, $ligne, $ec->elementConstitutif->getCode());
                $this->excelWriter->writeCellXY($col + 1, $ligne, $ec->elementConstitutif->displayCodeApogee(), ['bold' => true]);
                if ($ec->elementConstitutif->getNatureUeEc()?->isChoix()) {
                    $this->excelWriter->writeCellXY($col +2, $ligne, 'CHOIX', ['color' => self::BLUE]);
                    foreach ($ec->elementsConstitutifsEnfants as $ecsEnfant) {
                        $ligne++;
                        $this->excelWriter->writeCellXY($col + 1, $ligne, $ecsEnfant->elementConstitutif->getCode());
                        $this->excelWriter->writeCellXY($col + 2, $ligne, $ecsEnfant->elementConstitutif->displayCodeApogee(), ['bold' => true]);
                        if ($ecsEnfant->elementConstitutif->getNatureUeEc()?->isLibre()) {
                            $this->excelWriter->writeCellXY($col + 3, $ligne, 'CHOIX', ['color' => self::BLUE]);
                        } else {
                            $this->excelWriter->writeCellXY($col + 3, $ligne, 'EC', ['color' => self::BLUE]);
                        }
                        $this->excelWriter->writeCellXY($col + 4, $ligne, $ecsEnfant->heuresEctsEc->ects, ['color' => self::GREEN]);
                        $this->excelWriter->writeCellXY($col + 5, $ligne, $ecsEnfant->elementConstitutif->getTypeApogee());
                    }
                } else {
                    if ($ec->elementConstitutif->getNatureUeEc()?->isLibre()) {
                        $this->excelWriter->writeCellXY($col + 3, $ligne, 'CHOIX', ['color' => self::BLUE]);
                    } else {
                        $this->excelWriter->writeCellXY($col +2, $ligne, 'EC', ['color' => self::BLUE]);
                    }
                    $this->excelWriter->writeCellXY($col + 3, $ligne, $ec->heuresEctsEc->ects, ['color' => self::GREEN]);
                    $this->excelWriter->writeCellXY($col + 4, $ligne, $ec->elementConstitutif->getTypeApogee());
                }
            }

        }

        return $ligne;
    }

    public function exportFormation(Formation $formation): StreamedResponse
    {
        $this->excelWriter->nouveauFichier();
        $i = 0;
        foreach ($formation->getParcours() as $parcours) {
            $this->excelWriter->createSheet(substr($parcours->getLibelle(), 0, 31));
            $this->excelWriter->setActiveSheetIndex($i);
            $this->writeParcours($parcours);
            $i++;
        }


        $fileName = Tools::FileName('Codification-Formation-' . $formation->getDisplay() . '-' . (new DateTime())->format('d-m-Y-H-i'));
        return $this->excelWriter->genereFichier($fileName, true);
    }
}
