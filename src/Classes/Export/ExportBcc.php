<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Classes/Export/ExportSynthese.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 11/11/2023 13:07
 */

namespace App\Classes\Export;

use App\Classes\Excel\ExcelWriter;
use App\Classes\GetElementConstitutif;
use App\Entity\CampagneCollecte;
use App\Entity\Parcours;
use App\Repository\FormationRepository;
use App\Service\ProjectDirProvider;
use App\TypeDiplome\TypeDiplomeResolver;
use App\TypeDiplome\Dto\OptionsCalculStructure;
use App\Utils\Tools;
use DateTime;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportBcc implements ExportInterface
{
    //todo: a revoir complétement
    private string $fileName;

    public function __construct(
        protected TypeDiplomeResolver $typeDiplomeResolver,
        protected ExcelWriter         $excelWriter,
        protected FormationRepository $formationRepository,
    ) {
    }

    private function prepareExport(
        Parcours $parcours
    ): void {
        $formation = $parcours->getFormation();

        if (null === $formation) {
            return;
        }

        $this->excelWriter->nouveauFichier('Export BCC');
        $this->excelWriter->setActiveSheetIndex(0);
        $col = 3;

        $this->excelWriter->writeCellName('C1', 'Type Diplôme', [
            'style' => 'HORIZONTAL_RIGHT',
        ]);
        $this->excelWriter->writeCellName('D1', $formation->getTypeDiplome()?->getLibelle());
        $this->excelWriter->writeCellName('C2', 'Formation', [
            'style' => 'HORIZONTAL_RIGHT',
        ]);
        $this->excelWriter->writeCellName('D2', $formation->getDisplay());
        $this->excelWriter->writeCellName('C3', 'Parcours', [
            'style' => 'HORIZONTAL_RIGHT',
        ]);
        $this->excelWriter->writeCellName('D3', $parcours->isParcoursDefaut() === false ? $parcours->getDisplay() : '');
        $this->excelWriter->writeCellName('C4', 'Composante', [
            'style' => 'HORIZONTAL_RIGHT',
        ]);
        $this->excelWriter->writeCellName('D4', $formation->getComposantePorteuse()?->getLibelle());
        $this->excelWriter->writeCellName('C5', 'Localisation', [
            'style' => 'HORIZONTAL_RIGHT',
        ]);
        $this->excelWriter->writeCellName('D5', $parcours->getLocalisation()?->getLibelle());
        $this->excelWriter->getColumnDimension('A', 30);
        $this->excelWriter->getColumnDimension('B', 50);

        $typeD = $this->typeDiplomeResolver->fromParcours($parcours);
        $dto = $typeD->calcul($parcours, new OptionsCalculStructure(dataFromFicheMatiere: true));

        $ligne = 7;
        foreach ($dto->semestres as $semestre) {
            $ligne = 7;
            $debutCol = $col;
            $ueGroups = $this->buildUeGroups($semestre);
            $nbCols = 0;
            foreach ($ueGroups as $group) {
                $nbCols += $group['width'];
            }

            if ($nbCols === 0) {
                continue;
            }

            $this->excelWriter->writeCellXY($col, $ligne, 'Semestre ' . $semestre->semestre->display(), [
                'style' => 'HORIZONTAL_CENTER',
            ]);
            $this->excelWriter->mergeCellsCaR($col, $ligne, $col + $nbCols - 1, $ligne);

            // Ligne 2: UE / UE enfant
            $ligne++;
            $col = $debutCol;
            foreach ($ueGroups as $group) {
                $this->excelWriter->writeCellXY($col, $ligne, $group['label'], [
                    'style' => 'HORIZONTAL_CENTER',
                ]);
                $this->excelWriter->mergeCellsCaR($col, $ligne, $col + $group['width'] - 1, $ligne);
                $col += $group['width'];
            }

            // Ligne 3: EC (avec fusion quand EC choix)
            $ligne++;
            $col = $debutCol;
            foreach ($ueGroups as $group) {
                foreach ($group['ecs'] as $ec) {
                    $ecWidth = $this->getEcDisplayWidth($ec);
                    $this->excelWriter->writeCellXY($col, $ligne, $ec->elementConstitutif->getCode(), [
                        'style' => 'HORIZONTAL_CENTER',
                    ]);
                    if ($ecWidth > 1) {
                        $this->excelWriter->mergeCellsCaR($col, $ligne, $col + $ecWidth - 1, $ligne);
                    }
                    $col += $ecWidth;
                }
            }

                $ligne++;
                $col = $debutCol;
                foreach ($semestre->ues as $ue) {
                    if ($ue->ue->getUeParent() === null) {
                        if (count($ue->uesEnfants()) == 0) {
                            foreach ($ue->elementConstitutifs as $ec) {
                                $this->excelWriter->writeCellXY($col, $ligne, $ec->elementConstitutif->getCode(), [
                                    'style' => 'HORIZONTAL_CENTER',
                                ]);
                                $col++;
                            }
                        } else {
                            foreach ($ue->uesEnfants() as $uee) {
                                foreach ($uee->elementConstitutifs as $ec) {
                                    $this->excelWriter->writeCellXY($col, $ligne, $ec->elementConstitutif->getCode(), [
                                        'style' => 'HORIZONTAL_CENTER',
                                    ]);
                                    $col++;
                                }
                            }
                        }
                    }
                }

            // Lignes compétences
            $ligne++;
            foreach ($parcours->getBlocCompetences() as $bcc) {
                foreach ($bcc->getCompetences() as $competence) {
                    $this->excelWriter->writeCellXY(1, $ligne, $bcc->display(), ['wrap' => true]);
                    $this->excelWriter->writeCellXY(2, $ligne, $competence->display(), ['wrap' => true]);
                    $col = $debutCol;

                    foreach ($ueGroups as $group) {
                        foreach ($group['ecs'] as $ec) {
                            $ecChildren = $this->getEcChildren($ec);
                            if ($this->isChoixEc($ec) && count($ecChildren) > 0) {
                                foreach ($ecChildren as $ecChild) {
                                    $this->excelWriter->writeCellXY($col, $ligne, $this->isCompetenceMobilisee($ecChild, $competence->getCode()) ? 'X' : '', [
                                        'style' => 'HORIZONTAL_CENTER',
                                        'valign' => 'VERTICAL_CENTER',
                                    ]);
                                    $col++;
                                }
                            } else {
                                $this->excelWriter->writeCellXY($col, $ligne, $this->isCompetenceMobilisee($ec, $competence->getCode()) ? 'X' : '', [
                                    'style' => 'HORIZONTAL_CENTER',
                                    'valign' => 'VERTICAL_CENTER',
                                ]);
                                $col++;
                            }
                        }
                    }
                    $ligne++;
                }
            }

            $col = $debutCol + $nbCols;

        }

        $this->excelWriter->borderOutsiteInside(1, 7, $col-1, $ligne-1);
        $formation = $parcours->getFormation();
        if ($parcours->isParcoursDefaut() === false) {
            $texte = $formation->gettypeDiplome()?->getLibelleCourt(). ' ' . $formation->getSigle() . ' ' . $parcours->getSigle();
        } else {
            $texte = $formation->gettypeDiplome()?->getLibelleCourt() . ' ' . $formation->getSigle();
        }

        $this->fileName = Tools::FileName('BCC Croisé Global - ' . $texte . ' ' . (new DateTime())->format('d-m-Y-H-i'), 30);
    }

    public function export(Parcours $parcours): StreamedResponse
    {
        $this->prepareExport($parcours);
        return $this->excelWriter->genereFichier($this->fileName);
    }

    public function exportLink(CampagneCollecte $campagneCollecte): string
    {
        return '';
    }

    private function buildUeGroups(object $semestre): array
    {
        $groups = [];

        foreach ($semestre->ues as $ue) {
            if ($ue->ue->getUeParent() !== null) {
                continue;
            }

            $uesEnfants = $this->getUesEnfants($ue);
            if (count($uesEnfants) === 0) {
                $groups[] = [
                    'label' => $ue->display,
                    'ecs' => $ue->elementConstitutifs,
                    'width' => $this->getEcListDisplayWidth($ue->elementConstitutifs),
                ];
                continue;
            }

            foreach ($uesEnfants as $ueEnfant) {
                $groups[] = [
                    'label' => $ueEnfant->display,
                    'ecs' => $ueEnfant->elementConstitutifs,
                    'width' => $this->getEcListDisplayWidth($ueEnfant->elementConstitutifs),
                ];
            }
        }

        return $groups;
    }

    private function getUesEnfants(object $ue): array
    {
        if (property_exists($ue, 'uesEnfants')) {
            return is_array($ue->uesEnfants) ? $ue->uesEnfants : iterator_to_array($ue->uesEnfants);
        }

        if (method_exists($ue, 'uesEnfants')) {
            $children = $ue->uesEnfants();
            return is_array($children) ? $children : iterator_to_array($children);
        }

        return [];
    }

    private function getEcChildren(object $ec): array
    {
        if (!property_exists($ec, 'elementsConstitutifsEnfants')) {
            return [];
        }

        return is_array($ec->elementsConstitutifsEnfants)
            ? $ec->elementsConstitutifsEnfants
            : iterator_to_array($ec->elementsConstitutifsEnfants);
    }

    private function isChoixEc(object $ec): bool
    {
        return $ec->elementConstitutif->getNatureUeEc()?->isChoix() === true;
    }

    private function getEcDisplayWidth(object $ec): int
    {
        $ecChildren = $this->getEcChildren($ec);
        if ($this->isChoixEc($ec) && count($ecChildren) > 0) {
            return count($ecChildren);
        }

        return 1;
    }

    private function getEcListDisplayWidth(iterable $ecs): int
    {
        $width = 0;
        foreach ($ecs as $ec) {
            $width += $this->getEcDisplayWidth($ec);
        }

        return $width;
    }

    private function isCompetenceMobilisee(object $ecStructure, string $competenceCode): bool
    {
        if (!property_exists($ecStructure, 'bccs')) {
            return false;
        }

        $bccs = $ecStructure->bccs;
        if (is_array($bccs)) {
            if (array_key_exists($competenceCode, $bccs)) {
                return true;
            }

            foreach ($bccs as $bcc) {
                if (is_object($bcc) && method_exists($bcc, 'getCode') && $bcc->getCode() === $competenceCode) {
                    return true;
                }

                if (is_string($bcc) && $bcc === $competenceCode) {
                    return true;
                }
            }

            return false;
        }

        foreach ($bccs as $bcc) {
            if (is_object($bcc) && method_exists($bcc, 'getCode') && $bcc->getCode() === $competenceCode) {
                return true;
            }

            if (is_string($bcc) && $bcc === $competenceCode) {
                return true;
            }
        }

        return false;
    }
}
