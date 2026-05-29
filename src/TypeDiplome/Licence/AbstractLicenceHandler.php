<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/TypeDiplome/Licence/LicenceHandler.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 28/05/2025 15:25
 */

namespace App\TypeDiplome\Licence;

use App\DTO\StructureParcours;
use App\Entity\CampagneCollecte;
use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\Mccc;
use App\Entity\Parcours;
use App\Entity\TypeEpreuve;
use App\Repository\BlocCompetenceRepository;
use App\Repository\TypeDiplomeRepository;
use App\TypeDiplome\Exceptions\TypeDiplomeNotFoundException;
use App\TypeDiplome\Licence\Services\LicenceMccc;
use App\TypeDiplome\Licence\Services\LicenceMcccVersion;
use App\TypeDiplome\TypeDiplomeHandlerInterface;
use App\TypeDiplome\TypeDiplomeMcccInterface;
use App\Utils\Tools;
use DateTimeInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class AbstractLicenceHandler implements TypeDiplomeHandlerInterface, TypeDiplomeMcccInterface
{
    public const TEMPLATE_FOLDER = 'licence';
    public const SOURCE = 'licence';
    public const TEMPLATE_FORM_MCCC = 'licence.html.twig';
    public const NB_ANNEE = 3;

    /**
     * @var TypeEpreuve[]|array
     */
    private array $typeEpreuves;

    public function __construct(
        TypeDiplomeRepository            $typeDiplomeRepository,
        protected EntityManagerInterface $entityManager,
        protected LicenceMccc            $licenceMccc,
        protected LicenceMcccVersion     $licenceMcccVersion,
        private BlocCompetenceRepository $blocCompetenceRepository,
        private StructureParcoursLicence $structureParcoursLicence)
    {
        $typeD = $typeDiplomeRepository->findOneBy(['libelle_court' => $this->getLibelleCourt()]);

        if ($typeD === null) {
            throw new TypeDiplomeNotFoundException();
        }

        $this->typeEpreuves = $this->entityManager->getRepository(TypeEpreuve::class)->findByTypeDiplome($typeD);
    }

    public function getTypeEpreuves(): array
    {
        return $this->typeEpreuves;
    }

    public function supports(string $type): bool
    {
        return $type === $this->getLibelleCourt();
    }

    public function calculStructureParcours(Parcours $parcours, bool $withEcts = true, bool $withBcc = true): StructureParcours
    {
        return $this->structureParcoursLicence->calcul($parcours, $withEcts, $withBcc, true);
    }

    public function showStructure(Parcours $parcours): array
    {
        $structure = $this->structureParcoursLicence->calcul($parcours);
        return [
            'parcours' => $parcours,
            'structure' => $structure
        ];
    }

    public function getStructureCompetences(Parcours $parcours): array
    {
        return $this->blocCompetenceRepository->findByParcours($parcours);
    }

    public function exportExcelMccc(
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true
    ): StreamedResponse
    {
        return $this->licenceMccc->exportExcelLicenceMccc($anneeUniversitaire, $parcours, $dateCfvu, $dateConseil, $versionFull);
    }

    public function exportExcelVersionMccc(
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true
    ): StreamedResponse
    {
        return $this->licenceMcccVersion->exportExcelLicenceMccc($anneeUniversitaire, $parcours, $dateCfvu, $dateConseil, $versionFull);
    }

    public function exportExcelAndSaveVersionMccc(
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        string             $dir,
        string             $fichier,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null
    ): string
    {
        return $this->licenceMcccVersion->exportAndSaveExcelLicenceMccc($anneeUniversitaire, $parcours, $dir, $fichier, $dateCfvu, $dateConseil);
    }

    public function exportPdfMccc(
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true
    ): Response
    {
        return $this->licenceMccc->exportPdfLicenceMccc($anneeUniversitaire, $parcours, $dateCfvu, $dateConseil, $versionFull);
    }

    public function exportAndSaveExcelMccc(
        string             $dir,
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true
    ): string
    {
        return $this->licenceMccc->exportAndSaveExcelLicenceMccc($anneeUniversitaire, $parcours, $dir, $dateCfvu, $dateConseil, $versionFull);
    }

    public function exportAndSavePdfMccc(
        string             $dir,
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true
    ): string
    {
        return $this->licenceMccc->exportAndSavePdfLicenceMccc($anneeUniversitaire, $parcours, $dir, $dateCfvu, $dateConseil, $versionFull);
    }

    public function saveMcccs(ElementConstitutif|FicheMatiere $elementConstitutif, InputBag $request): void
    {
        $mcccs = $this->getMcccs($elementConstitutif);
        switch ($request->get('choix_type_mccc')) {
            case 'cc':
                if ($request->get('type_cc') === 'cc') {
                    if (isset($mcccs[1]) && isset($mcccs[1]['cc']) && isset($mcccs[1]['cc'][1])) {
                        $mcccs[1]['cc'][1]->setPourcentage(50);
                        $mcccs[1]['cc'][1]->setNbEpreuves(2);
                    } else {
                        $mccc = new Mccc();
                        if ($elementConstitutif instanceof FicheMatiere) {
                            $mccc->setFicheMatiere($elementConstitutif);
                        } else {
                            $mccc->setEc($elementConstitutif);
                        }
                        $mccc->setLibelle('Contrôle continu');
                        $mccc->setPourcentage(50);
                        $mccc->setNbEpreuves(2);
                        $mccc->setNumeroSession(1);
                        $mccc->setControleContinu(true);
                        $mccc->setExamenTerminal(false);
                        $mccc->setNumeroEpreuve(1);
                        $mcccs[1]['cc'][1] = $mccc;
                        $this->entityManager->persist($mccc);
                    }

                    if ($request->has('cc_has_tp') && $request->get('cc_has_tp') === 'on') {
                        $tab = [
                            'pourcentage' => 50,
                            'cc_has_tp' => 'on'
                        ];
                    } else {
                        $tab = [
                            'pourcentage' => 0,
                            'cc_has_tp' => false
                        ];
                    }
                    $mcccs[1]['cc'][1]->setOptions($tab);
                } else {
                    $typeD = 'NOT-L';
                    // CC des autres diplômes //todo: a déplacer dans les autres types...
                    $mcccs = $this->sauvegardeCc($elementConstitutif, $mcccs, $request->all(), 1, 's1_cc', 'NOT-L');
                }
                $mcccs = $this->sauvegardeCts($elementConstitutif, $mcccs, $request->all(), 2, 's2_ct');
                break;
            case 'cci':
                $pourcentages = $request->all()['pourcentage'];
                $totPourcentage = 0.0;
                //supprimer les MCCC en trop
                foreach ($mcccs as $mccc) {
                    if (is_array($pourcentages)) {
                        if (!array_key_exists($mccc->getNumeroSession(), $pourcentages)) {
                            $this->entityManager->remove($mccc);
                        }
                    }
                }

                $this->entityManager->flush();
                foreach ($pourcentages as $key => $pourcentage) {
                    if (array_key_exists($key, $mcccs)) {
                        $mcccs[$key]->setPourcentage((float)$pourcentage);
                        $totPourcentage += (float)$pourcentage;
                    } else {
                        $mccc = new Mccc();
                        if ($elementConstitutif instanceof FicheMatiere) {
                            $mccc->setFicheMatiere($elementConstitutif);
                        } else {
                            $mccc->setEc($elementConstitutif);
                        }
                        $mccc->setLibelle('Contrôle continu intégral ' . $key);
                        $mccc->setPourcentage((float)$pourcentage);
                        $mccc->setNbEpreuves(1);
                        $mccc->setNumeroSession((int)$key);
                        $mccc->setNumeroEpreuve((int)$key);
                        $mccc->setControleContinu(true);
                        $mccc->setExamenTerminal(false);
                        $mcccs[$key] = $mccc;
                        $totPourcentage += (float)$pourcentage;
                        $this->entityManager->persist($mccc);
                    }
                }
                break;
            case 'cc_ct':
                $tab = [
                    'pourcentage' => Tools::convertToFloat($request->get('cc_has_tp_pourcentage', 0)),
                    'cc_has_tp' => $request->get('cc_has_tp', false)
                ];
                //cas classique
                if (array_key_exists(1, $mcccs) && array_key_exists('cc', $mcccs[1])) {
                    $mcccs[1]['cc'][1]->setPourcentage((float)$request->get('pourcentage_s1_cc'));
                    $mcccs[1]['cc'][1]->setNbEpreuves((int)$request->get('nbepreuve_s1_cc'));
                    $mcccs[1]['cc'][1]->setOptions($tab);
                } else {
                    $mccc = new Mccc();
                    if ($elementConstitutif instanceof FicheMatiere) {
                        $mccc->setFicheMatiere($elementConstitutif);
                    } else {
                        $mccc->setEc($elementConstitutif);
                    }
                    $mccc->setLibelle('Contrôle continu');
                    $mccc->setControleContinu(true);
                    $mccc->setExamenTerminal(false);
                    $mccc->setNumeroSession(1);
                    $mccc->setNumeroEpreuve(1);
                    $mccc->setNbEpreuves((int)$request->get('nbepreuve_s1_cc'));
                    $mccc->setPourcentage((float)$request->get('pourcentage_s1_cc'));
                    $mccc->setOptions($tab);
                    $this->entityManager->persist($mccc);
                    $mcccs[1]['cc'][1] = $mccc;
                }

                $data = $request->all();

                $mcccs = $this->sauvegardeCts($elementConstitutif, $mcccs, $data, 1, 's1_ct', false);
                $mcccs = $this->sauvegardeCts($elementConstitutif, $mcccs, $data, 2, 's2_ct');

                //todo: encart sur vérification parcours/formation sur la structure selon le type, cas des licences. Permet de déporter les tests spécifiques dans les type de diplômes ou départer la vérification structure dans les types de diplômes
                break;
            case 'ct':
                //todo: gérer la durée obligatoire dans le contrôle MCCC...
                //gérer la suppression d'une entrée déjà dans la BDD...
                $data = $request->all();
                //récupérer toutes les clés commencant par typeEpreuve_s1_ct
                $mcccs = $this->sauvegardeCts($elementConstitutif, $mcccs, $data, 1, 's1_ct');
                $mcccs = $this->sauvegardeCts($elementConstitutif, $mcccs, $data, 2, 's2_ct');
                break;

        }

        $this->entityManager->flush();
    }

    public function getMcccs(ElementConstitutif|FicheMatiere $elementConstitutif): array|Collection
    {
        // Source canonique: évite les collections inverses potentiellement vides/stales après remove+add.
        if ($elementConstitutif->getId() !== null) {
            $criteria = $elementConstitutif instanceof FicheMatiere
                ? ['ficheMatiere' => $elementConstitutif]
                : ['ec' => $elementConstitutif];

            $mcccs = $this->entityManager
                ->getRepository(Mccc::class)
                ->findBy($criteria, ['numeroSession' => 'ASC', 'numeroEpreuve' => 'ASC', 'id' => 'ASC']);
        } else {
            $mcccs = $elementConstitutif->getMcccs()->toArray();
        }

        $tabMcccs = [];

        if ($elementConstitutif->getTypeMccc() === 'cci') {
            foreach ($mcccs as $mccc) {
                $tabMcccs[$mccc->getNumeroSession()] = $mccc;
            }
        } else {
            foreach ($mcccs as $mccc) {
                if ($mccc->isSecondeChance()) {
                    $tabMcccs[3]['chance'] = $mccc;
                } elseif ($mccc->isControleContinu() === true && $mccc->isExamenTerminal() === false) {
                    $tabMcccs[$mccc->getNumeroSession()]['cc'][$mccc->getNumeroEpreuve() ?? 1] = $mccc;
                } elseif ($mccc->isControleContinu() === false && $mccc->isExamenTerminal() === true) {
                    $tabMcccs[$mccc->getNumeroSession()]['et'][$mccc->getNumeroEpreuve() ?? 1] = $mccc;
                }
            }
        }

        return $tabMcccs;
    }

    private function sauvegardeCc(ElementConstitutif|FicheMatiere $elementConstitutif, array $mcccs, array $data, int $session, string $cle, string $typeD = 'L'): array
    {
        $typeEpreuve_s1_cc = [];
        foreach ($data as $key => $value) {
            if (preg_match('/^pourcentage_' . $cle . '/', $key)) {
                $parts = explode('_', $key);
                $lastPart = $parts[count($parts) - 1];
                //retirer les deux premiers caractères
                $lastPart = substr($lastPart, 2);

                // Récupérer le code et le numéro
                $typeEpreuve_s1_cc[] = (int)$lastPart;
            }
        }

        foreach ($typeEpreuve_s1_cc as $numEp) {
            if (isset($mcccs[$session]['cc'][$numEp])) {
                if ($typeD === 'L') {
                    $mcccs[$session]['cc'][$numEp]->setNbEpreuves(2);
                } else {
                    $mcccs[$session]['cc'][$numEp]->setNbEpreuves(1);
                }

                if (count($typeEpreuve_s1_cc) > 1) {
                    if (array_key_exists('pourcentage_' . $cle . $numEp, $data) && $data['pourcentage_' . $cle . $numEp] !== '') {
                        $mcccs[$session]['cc'][$numEp]->setPourcentage(Tools::convertToFloat($data['pourcentage_' . $cle . $numEp]));
                    } else {
                        $mcccs[$session]['cc'][$numEp]->setPourcentage(null);
                    }
                } else {
                    $mcccs[$session]['cc'][$numEp]->setPourcentage(50);
                }
                $mcccs[$session]['cc'][$numEp]->setNumeroEpreuve($numEp);
            } else {
                $mccc = new Mccc();
                if ($elementConstitutif instanceof FicheMatiere) {
                    $mccc->setFicheMatiere($elementConstitutif);
                } else {
                    $mccc->setEc($elementConstitutif);
                }
                $mccc->setLibelle('Contrôle continu');
                $mccc->setControleContinu(true);
                $mccc->setExamenTerminal(false);
                $mccc->setNumeroSession($session);
                $mccc->setNumeroEpreuve($numEp);
                if ($typeD === 'L') {
                    $mccc->setNbEpreuves(2);
                } else {
                    $mccc->setNbEpreuves(1);
                }


                if (count($typeEpreuve_s1_cc) > 1) {
                    if (array_key_exists('pourcentage_' . $cle . $numEp, $data) && $data['pourcentage_' . $cle . $numEp] !== '') {
                        $mccc->setPourcentage(Tools::convertToFloat($data['pourcentage_' . $cle . $numEp]));
                    } else {
                        $mccc->setPourcentage(0);
                    }
                } else {
                    $mccc->setPourcentage(50);
                }
                $this->entityManager->persist($mccc);
                $mcccs[$session]['cc'][$numEp] = $mccc;
            }
            //todo: faire toutes les mises à jour en dehors du if, une fois l'objet repris ou créé. ideme autre methode de save

            if (isset($data['cc_has_tp_' . $cle . $numEp]) && $data['cc_has_tp_' . $cle . $numEp] === 'on') {
                $tab = [
                    'pourcentage' => $mcccs[$session]['cc'][$numEp]->getPourcentage(),
                    'cc_has_tp' => 'on'
                ];
            } else {
                $tab = [
                    'pourcentage' => 0,
                    'cc_has_tp' => false
                ];
            }
            $mcccs[$session]['cc'][$numEp]->setOptions($tab);
        }
        //suppression des MCCC
        foreach ($mcccs[$session]['cc'] as $mccc) {
            if (!in_array($mccc->getNumeroEpreuve(), $typeEpreuve_s1_cc)) {
                $this->entityManager->remove($mccc);
            }
        }

        return $mcccs;
    }

    private function sauvegardeCts(ElementConstitutif|FicheMatiere $elementConstitutif, array $mcccs, array $data, int $session, string $cle, bool $fixToCent = true): array
    {
        $typeEpreuve_s1_ct = [];
        foreach ($data as $key => $value) {
            if (preg_match('/^typeEpreuve_' . $cle . '/', $key)) {
                $parts = explode('_', $key);
                $lastPart = $parts[count($parts) - 1];
                //retirer les deux premiers caractères
                $lastPart = substr($lastPart, 2);

                // Récupérer le code et le numéro
                $typeEpreuve_s1_ct[] = (int)$lastPart;
            }
        }

        foreach ($typeEpreuve_s1_ct as $numEp) {
            $justificationText = null;
            if (array_key_exists((int)$data["typeEpreuve_{$cle}{$numEp}"], $this->typeEpreuves)) {
                if ($this->typeEpreuves[(int)$data["typeEpreuve_{$cle}{$numEp}"]]->hasJustification()) {
                    $justificationText = $data["justification_{$cle}{$numEp}"] ?? "";
                }
            }

            if (isset($mcccs[$session]['et'][$numEp])) {
                if (array_key_exists('typeEpreuve_' . $cle . $numEp, $data) && $data['typeEpreuve_' . $cle . $numEp] === "") {
                    $mcccs[$session]['et'][$numEp]->setTypeEpreuve(null);
                } else {
                    $mcccs[$session]['et'][$numEp]->setTypeEpreuve([$data['typeEpreuve_' . $cle . $numEp]]);
                }

                $mcccs[$session]['et'][$numEp]->setNumeroEpreuve($numEp);
                $mcccs[$session]['et'][$numEp]->setJustificationText($justificationText);
            } else {
                //n'existe pas à créer.
                $mccc = new Mccc();
                if ($elementConstitutif instanceof FicheMatiere) {
                    $mccc->setFicheMatiere($elementConstitutif);
                } else {
                    $mccc->setEc($elementConstitutif);
                }
                $mccc->setLibelle('Contrôle terminal');
                $mccc->setControleContinu(false);
                $mccc->setExamenTerminal(true);
                $mccc->setNumeroSession($session);
                $mccc->setNbEpreuves(1);
                $mccc->setNumeroEpreuve($numEp);
                $mccc->setTypeEpreuve([$data['typeEpreuve_' . $cle . $numEp]]);
                $mccc->setJustificationText($justificationText);

                $this->entityManager->persist($mccc);
                $mcccs[$session]['et'][$numEp] = $mccc;
            }

            if (count($typeEpreuve_s1_ct) === 1 && $fixToCent === true) {
                $mcccs[$session]['et'][$numEp]->setPourcentage(100);
            } else {
                if (array_key_exists('pourcentage_' . $cle . $numEp, $data) && $data['pourcentage_' . $cle . $numEp] !== '') {
                    $mcccs[$session]['et'][$numEp]->setPourcentage(Tools::convertToFloat($data['pourcentage_' . $cle . $numEp]));
                } else {
                    $mcccs[$session]['et'][$numEp]->setPourcentage(null);
                }
            }

            if (array_key_exists('duree_' . $cle . $numEp, $data) && $data['duree_' . $cle . $numEp] !== '') {
                $mcccs[$session]['et'][$numEp]->setDuree(Tools::convertToTime($data['duree_' . $cle . $numEp]));
            } else {
                $mcccs[$session]['et'][$numEp]->setDuree(null);
            }
        }

        //suppression des MCCC
        foreach ($mcccs[$session]['et'] as $mccc) {
            if (!in_array($mccc->getNumeroEpreuve(), $typeEpreuve_s1_ct)) {
                $this->entityManager->remove($mccc);
            }
        }

        return $mcccs;
    }

    private function verificationEt(array $mcccs): bool
    {
        $totPourcentage = 0.0;

        foreach ($mcccs as $mccc) {
            if ($mccc->getTypeEpreuve() === null || count($mccc->getTypeEpreuve()) === 0) {
                return false;
            }

            if ($mccc->getPourcentage() === null || $mccc->getPourcentage() === 0.0) {
                return false;
            }

            $totPourcentage += $mccc->getPourcentage() * $mccc->getNbEpreuves();

            if ($this->typeEpreuveHasDuree($mccc->getTypeEpreuve()[0]) && $mccc->getDuree() === null) {
                return false;
            }

            if ($totPourcentage === 100.0) {
                return true;
            }
        }

        return false;
    }

    private function typeEpreuveHasDuree(int|string $id): bool
    {
        if (!array_key_exists($id, $this->typeEpreuves)) {
            return false;
        }

        return $this->typeEpreuves[$id]->isHasDuree() ?? false;
    }

    public function clearMcccs(ElementConstitutif|FicheMatiere $objet): void
    {
        $mcccs = $objet->getMcccs();
        foreach ($mcccs as $mccc) {
            $this->entityManager->remove($mccc);
        }
        $this->entityManager->flush();
    }

    public function checkIfMcccValide(ElementConstitutif|FicheMatiere $owner): bool
    {
        $mcccs = $this->getMcccs($owner);

        if (count($mcccs) === 0) {
            return false;
        }

        switch ($owner->getTypeMccc()) {
            case 'cc':

            if (isset($mcccs[2]) && !isset($mcccs[2]['et']) && !is_array($mcccs[2]['et'])) {
                    return false;
                }

                if (!$this->verificationEt($mcccs[2]['et'])) {
                    return false;
                }

                if (isset($mcccs[1]) && !isset($mcccs[1]['cc']) && !is_array($mcccs[1]['cc'])) {
                    return false;
                }

                $totPourcentage = 0.0;
                foreach ($mcccs[1]['cc'] as $mccc) {
                    if ($mccc->getPourcentage() === null || $mccc->getPourcentage() === 0.0) {
                        return false;
                    }
                    $totPourcentage += $mccc->getPourcentage() * $mccc->getNbEpreuves();
                }
                return $totPourcentage === 100.0;
            case 'cci':
                if (count($mcccs) < 3) {
                    return false;
                }

                $totPourcentage = 0.0;
                foreach ($mcccs as $mccc) {
                    if ($mccc->getPourcentage() === null || $mccc->getPourcentage() === 0.0 || $mccc->getPourcentage() > 50) {
                        return false;
                    }
                    $totPourcentage += $mccc->getPourcentage();
                }

                return $totPourcentage === 100.0;
            case 'cc_ct':
                if (isset($mcccs[1]) && !isset($mcccs[1]['cc']) && !is_array($mcccs[1]['cc'])) {
                    return false;
                }

                if ($mcccs[1]['cc'][1]->getPourcentage() === null || $mcccs[1]['cc'][1]->getPourcentage() === 0.0) {
                    return false;
                }

                if ($mcccs[1]['cc'][1]->getNbEpreuves() === null || $mcccs[1]['cc'][1]->getNbEpreuves() === 0) {
                    return false;
                }

                if (isset($mcccs[1]) && !isset($mcccs[1]['et']) && !is_array($mcccs[1]['et'])) {
                    return false;
                }

                $totPourcentage = $mcccs[1]['cc'][1]->getPourcentage();

                foreach ($mcccs[1]['et'] as $mccc) {
                    if ($mccc->getTypeEpreuve() === null || count($mccc->getTypeEpreuve()) === 0) {
                        return false;
                    }

                    if ($mccc->getPourcentage() === null || $mccc->getPourcentage() === 0.0) {
                        return false;
                    }

                    $totPourcentage += $mccc->getPourcentage();

                    if ($this->typeEpreuveHasDuree($mccc->getTypeEpreuve()[0]) && $mccc->getDuree() === null) {
                        return false;
                    }
                }

                if ($totPourcentage !== 100.0) {
                    return false;
                }

                if (isset($mcccs[2]) && !isset($mcccs[2]['et']) && !is_array($mcccs[2]['et'])) {
                    return false;
                }

                if (!$this->verificationEt($mcccs[2]['et'])) {
                    return false;
                }
                return true;
            case 'ct':
                if (isset($mcccs[1]) && !isset($mcccs[1]['et']) && !is_array($mcccs[1]['et'])) {
                    return false;
                }

                if (!$this->verificationEt($mcccs[1]['et'])) {
                    return false;
                }


                if (isset($mcccs[2]) && !isset($mcccs[2]['et']) && !is_array($mcccs[2]['et'])) {
                    return false;
                }

                if (!$this->verificationEt($mcccs[2]['et'])) {
                    return false;
                }
                return true;
        }

        return false;
    }

    public function getDisplayMccc(array $mcccs, string $typeMccc = ''): array
    {
        $mccc = new Dto\Mccc($mcccs, $typeMccc, $this->typeEpreuves);
        $mccc->calculDisplayMccc();

        return $mccc->toArray();
    }
}
