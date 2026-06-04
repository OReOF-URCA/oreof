<?php

namespace App\TypeDiplome\But;

use App\DTO\StructureParcours;
use App\Entity\CampagneCollecte;
use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\Mccc;
use App\Entity\Parcours;
use App\Repository\ButCompetenceRepository;
use App\TypeDiplome\But\Services\ButMccc;
use App\TypeDiplome\But\Services\ButMcccVersion;
use App\TypeDiplome\TypeDiplomeHandlerInterface;
use App\TypeDiplome\TypeDiplomeMcccInterface;
use App\Utils\Tools;
use DateTimeInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ButHandler implements TypeDiplomeHandlerInterface, TypeDiplomeMcccInterface
{

    public const TEMPLATE_FOLDER = 'but';
    public const SOURCE = 'but';
    public const TEMPLATE_FORM_MCCC = 'but.html.twig';

    private array $typeEpreuves = [
        'sae' => [
            'iut_portfolio', 'iut_livrable', 'iut_rapport', 'iut_soutenance',
            'hors_iut_entreprise', 'hors_iut_rapport', 'hors_iut_soutenance'
        ],
        'ressource' => [
            'td_tp_oral', 'td_tp_ecrit', 'td_tp_rapport', 'td_tp_autre',
            'cm_ecrit', 'cm_rapport'
        ]
    ];

    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected ButMccc                 $butMccc,
        protected ButMcccVersion        $butMcccVersion,
        private ButCompetenceRepository $butCompetenceRepository,
        private StructureParcoursBut    $structureParcoursBut)
    {
    }

    public function supports(string $type): bool
    {
        return $type === $this->getLibelleCourt();
    }

    public function calculStructureParcours(Parcours $parcours, bool $withEcts = true, bool $withBcc = true): StructureParcours
    {
        return $this->structureParcoursBut->calcul($parcours);
    }

    public function showStructure(Parcours $parcours): array
    {
        //utile ?
        $structure = $this->structureParcoursBut->calcul($parcours);
        return [
            'parcours' => $parcours,
            'structure' => $structure
        ];
    }

    public function getStructureCompetences(Parcours $parcours): array
    {
        return $this->butCompetenceRepository->findBy([
            'formation' => $parcours->getFormation(),
        ]);
    }

    public function exportExcelMccc(
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true
    ): StreamedResponse {
        return $this->butMccc->exportExcelButMccc($anneeUniversitaire, $parcours, $dateCfvu, $dateConseil, $versionFull);
    }

    public function exportExcelVersionMccc(
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true
    ): StreamedResponse {
        return $this->butMcccVersion->exportExcelButMccc($anneeUniversitaire, $parcours, $dateCfvu, $dateConseil, $versionFull);
    }

    public function exportPdfMccc(
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true
    ): Response {
        return $this->butMccc->exportPdfButMccc($anneeUniversitaire, $parcours, $dateCfvu, $dateConseil, $versionFull);
    }

    public function exportAndSaveExcelMccc(
        string             $dir,
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true
    ): string {
        return $this->butMccc->exportAndSaveExcelbutMccc($anneeUniversitaire, $parcours, $dir, $dateCfvu, $dateConseil, $versionFull);
    }

    public function exportExcelAndSaveVersionMccc(
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        string $dir,
        string $fichier,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
    ): string {
        return $this->butMcccVersion->exportAndSaveExcelbutMccc($anneeUniversitaire, $parcours, $dir, $dateCfvu, $dateConseil, false);
    }

    public function exportAndSavePdfMccc(string $dir, CampagneCollecte $anneeUniversitaire, Parcours $parcours, ?DateTimeInterface $dateCfvu = null, ?DateTimeInterface $dateConseil = null, bool $versionFull = true): string
    {
        // TODO: Implement exportAndSavePdfMccc() method.
        return '';
    }

    public function saveMcccs(FicheMatiere|ElementConstitutif $elementConstitutif, InputBag $request): void
    {
        if ($request->has('sansNote') && $request->get('sansNote') === 'on') {
            $elementConstitutif->setSansNote(true);
        } else {
            $elementConstitutif->setSansNote(false);
            $type = $elementConstitutif->getTypeMatiere();
            $total = 0.0;
            $mcccs = $this->getMcccs($elementConstitutif);
            foreach ($this->typeEpreuves[$type] as $ep) {
                if ($request->has('pourcentage_' . $ep) && $request->has('nombre_' . $ep)) {
                    $pourcentage = $request->get('pourcentage_' . $ep);
                    $nombre = $request->get('nombre_' . $ep);
                    if (array_key_exists($ep, $mcccs)) {
                        $mcccs[$ep]->setPourcentage(Tools::convertToFloat($pourcentage));
                        $mcccs[$ep]->setNbEpreuves((int)$nombre);
                        $mcccs[$ep]->setLibelle($ep);
                        $mcccs[$ep]->setNumeroSession(1);
                        $mcccs[$ep]->setControleContinu(true);
                        $mcccs[$ep]->setExamenTerminal(false);
                        $total += $mcccs[$ep]->getPourcentage() * $mcccs[$ep]->getNbEpreuves();
                    } else {
                        $mccc = new Mccc();
                        $mccc->setTypeEpreuve([$ep]);
                        $mccc->setPourcentage(Tools::convertToFloat($pourcentage));
                        $mccc->setNbEpreuves((int)$nombre);
                        $mccc->setLibelle($ep);
                        $mccc->setControleContinu(true);
                        $mccc->setNumeroSession(1);
                        $mccc->setExamenTerminal(false);
                        $this->entityManager->persist($mccc);
                        $elementConstitutif->addMccc($mccc);
                        $total += $mccc->getPourcentage() * $mccc->getNbEpreuves();
                    }
                }
            }
        }

        $this->entityManager->flush();
    }

    public function getMcccs(ElementConstitutif|FicheMatiere $elementConstitutif): array|Collection
    {
        $mcccs = $elementConstitutif->getMcccs();
        $tab = [];
        foreach ($mcccs as $mccc) {
            $tab[$mccc->getTypeEpreuve()[0]] = $mccc;
        }

        return $tab;
    }

    public function clearMcccs(ElementConstitutif|FicheMatiere $objet): void
    {
        // TODO: Implement clearMcccs() method.
    }

    public function getTypeEpreuves(): array
    {
        return $this->typeEpreuves;
    }

    public function getLibelleCourt(): string
    {
        return 'BUT';
    }

    public function checkIfMcccValide(ElementConstitutif|FicheMatiere $owner): bool
    {
        if ($owner->isSansNote()) {
            return true;
        }

        $mccc = $owner->getMcccs();
        $somme = 0;
        foreach ($mccc as $m) {
            $somme += $m->getPourcentage() * $m->getNbEpreuves();
        }

        return $somme > 99 && $somme <= 100;
    }

    public function getDisplayMccc(array $mcccs, string $typeMccc): array
    {
        return $mcccs;
    }
}
