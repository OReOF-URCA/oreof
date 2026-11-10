<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Annee;
use App\Entity\CampagneCollecte;
use App\Entity\Composante;
use App\Entity\DpeParcours;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\PlateformeAdmission;
use App\Entity\PlateformeAdmissionParametre;
use App\Entity\Portail;
use App\Entity\TypeDiplome;
use App\Entity\Ville;
use App\Enums\TypeModificationDpeEnum;
use App\Enums\TypeParcoursEnum;
use App\Service\Offre\OffreAnnexesExportService;
use PHPUnit\Framework\TestCase;

final class OffreAnnexesExportServiceTest extends TestCase
{
    private OffreAnnexesExportService $service;

    protected function setUp(): void
    {
        // Les helpers testés n'utilisent aucune dépendance (SpreadsheetRenderer est final, non doublable).
        $this->service = (new \ReflectionClass(OffreAnnexesExportService::class))->newInstanceWithoutConstructor();
    }

    private function ville(string $libelle): Ville
    {
        return (new Ville())->setLibelle($libelle);
    }

    public function testResolveLieuPrivilegieLaLocalisationDuParcours(): void
    {
        $formation = new Formation(null);
        $formation->addLocalisationMention($this->ville('Reims'));
        $parcours = new Parcours($formation);
        $parcours->setLocalisation($this->ville('Troyes'));

        self::assertSame('Troyes', $this->service->resolveLieu($parcours));
    }

    public function testResolveLieuUtiliseLAncienChampVilleSansLocalisation(): void
    {
        $formation = new Formation(null);
        $formation->addLocalisationMention($this->ville('Reims'));
        $parcours = new Parcours($formation);
        $parcours->setVille($this->ville('Charleville-Mézières'));

        self::assertSame('Charleville-Mézières', $this->service->resolveLieu($parcours));
    }

    public function testResolveLieuRetombeSurLaMention(): void
    {
        $formation = new Formation(null);
        $formation->addLocalisationMention($this->ville('Châlons-en-Champagne'));

        self::assertSame('Châlons-en-Champagne', $this->service->resolveLieu(new Parcours($formation)));
    }

    public function testResolveLieuParDefautReims(): void
    {
        self::assertSame('Reims', $this->service->resolveLieu(new Parcours(new Formation(null))));
    }

    public function testIntegrationPlateformeSuitLaCaseQuandElleExiste(): void
    {
        $param = (new PlateformeAdmissionParametre())->setActive(false);

        self::assertFalse($this->service->isIntegrationPlateforme($param, true));
        self::assertTrue($this->service->isIntegrationPlateforme($param->setActive(true), true));
    }

    public function testIntegrationPlateformeImpossibleSiAnneeFermee(): void
    {
        $param = (new PlateformeAdmissionParametre())->setActive(true);

        self::assertFalse($this->service->isIntegrationPlateforme($param, false));
    }

    public function testIntegrationPlateformeSansParametreSuitLOuverture(): void
    {
        self::assertTrue($this->service->isIntegrationPlateforme(null, true));
        self::assertFalse($this->service->isIntegrationPlateforme(null, false));
    }

    public function testParcoursupComporteStatutsOuvertureEtPlateformeProposee(): void
    {
        $campagne = new CampagneCollecte();
        $psup = $this->psup();
        $formation = $this->licence(1, 'Psychologie');

        [$dpeOuvert, $l1Ouvert] = $this->parcoursL1($formation, 10, 'Psychologie', 150);
        [$dpeFerme, $l1Ferme] = $this->parcoursL1($formation, 11, 'LAS', 20);
        $dpeFerme->setEtatReconduction(TypeModificationDpeEnum::NON_OUVERTURE);
        [$dpeInactif, $l1Inactif] = $this->parcoursL1($formation, 12, 'Préprofessionnalisation', 30);
        $paramInactif = (new PlateformeAdmissionParametre())->setPlateforme($psup)->setCampagne($campagne)->setActive(false);

        $rows = $this->buildParcoursupRows(
            [$dpeOuvert, $dpeFerme, $dpeInactif],
            [10 => [$l1Ouvert], 11 => [$l1Ferme], 12 => [$l1Inactif]],
            [$l1Inactif->getId() => [$paramInactif]],
            $psup,
            $campagne
        );

        self::assertCount(3, $rows);

        // Les lignes sont triées par libellé de parcours
        // 1. LAS : fermé
        self::assertSame('LAS', (string) $rows[0]['parcours']);
        self::assertSame('NON', $rows[0]['annee_ouverte_n']);
        self::assertSame(0, $rows[0]['capacite_n']);

        // 2. Préprofessionnalisation : ouvert mais plateforme inactive
        self::assertSame('Préprofessionnalisation', (string) $rows[1]['parcours']);
        self::assertSame('OUI', $rows[1]['annee_ouverte_n']);
        self::assertSame('Non active', $rows[1]['plateforme_proposee_n']);
        self::assertSame(0, $rows[1]['capacite_n']);

        // 3. Psychologie : ouvert et actif
        self::assertSame('Psychologie', (string) $rows[2]['parcours']);
        self::assertSame('OUI', $rows[2]['annee_ouverte_n']);
        self::assertSame('Active', $rows[2]['plateforme_proposee_n']);
        self::assertSame(150, $rows[2]['capacite_n']);
    }

    public function testParcoursDefautLaisseLaCelluleVide(): void
    {
        $campagne = new CampagneCollecte();
        $psup = $this->psup();
        $formation = $this->licence(1, 'Droit');

        [$dpe, $l1] = $this->parcoursL1($formation, 10, Parcours::PARCOURS_DEFAUT, 100);

        $rows = $this->buildParcoursupRows([$dpe], [10 => [$l1]], [], $psup, $campagne);

        self::assertCount(1, $rows);
        self::assertSame('', (string) $rows[0]['parcours']);
    }

    public function testFormatParcoursLabelAjouteTypeParcoursSiNonClassique(): void
    {
        $formation = $this->licence(1, 'Chimie');

        // null
        self::assertSame('', $this->service->formatParcoursLabel(null));

        // Parcours par défaut classique
        $defautClassique = new Parcours($formation);
        $defautClassique->setLibelle(Parcours::PARCOURS_DEFAUT);
        $defautClassique->setTypeParcours(TypeParcoursEnum::TYPE_PARCOURS_CLASSIQUE);
        self::assertSame('', $this->service->formatParcoursLabel($defautClassique));

        // Parcours par défaut LAS1 -> affiche le type
        $defautLas = new Parcours($formation);
        $defautLas->setLibelle(Parcours::PARCOURS_DEFAUT);
        $defautLas->setTypeParcours(TypeParcoursEnum::TYPE_PARCOURS_LAS1);
        self::assertSame('LAS1', $this->service->formatParcoursLabel($defautLas));

        // Parcours classique
        $parcoursClassique = new Parcours($formation);
        $parcoursClassique->setLibelle('Chimie Moléculaire');
        $parcoursClassique->setTypeParcours(TypeParcoursEnum::TYPE_PARCOURS_CLASSIQUE);
        self::assertSame('Chimie Moléculaire', $this->service->formatParcoursLabel($parcoursClassique));

        // Parcours LAS1
        $parcoursLas1 = new Parcours($formation);
        $parcoursLas1->setLibelle('Chimie Moléculaire');
        $parcoursLas1->setTypeParcours(TypeParcoursEnum::TYPE_PARCOURS_LAS1);
        self::assertSame('Chimie Moléculaire - LAS1', $this->service->formatParcoursLabel($parcoursLas1));

        // Parcours en alternance
        $parcoursAlt = new Parcours($formation);
        $parcoursAlt->setLibelle('Chimie Industrielle');
        $parcoursAlt->setTypeParcours(TypeParcoursEnum::TYPE_PARCOURS_ALTERNANCE);
        self::assertSame('Chimie Industrielle - En alternance', $this->service->formatParcoursLabel($parcoursAlt));
    }

    public function testResolveEntityDiffPrendEnCompteTypeParcours(): void
    {
        $formation = $this->licence(1, 'Chimie');

        $parcoursN1 = new Parcours($formation);
        $parcoursN1->setLibelle('Chimie Moléculaire');
        $parcoursN1->setTypeParcours(TypeParcoursEnum::TYPE_PARCOURS_CLASSIQUE);
        $dpeN1 = (new DpeParcours())->setParcours($parcoursN1);

        $parcoursN = new Parcours($formation);
        $parcoursN->setLibelle('Chimie Moléculaire');
        $parcoursN->setTypeParcours(TypeParcoursEnum::TYPE_PARCOURS_LAS1);

        $diffs = $this->service->resolveEntityDiff($parcoursN, $dpeN1);

        self::assertTrue($diffs['parcours']->isDifferent());
        self::assertSame('Chimie Moléculaire', $diffs['parcours']->original);
        self::assertSame('Chimie Moléculaire - LAS1', $diffs['parcours']->new);
    }

    public function testResolveRemarquesFermetureDefinitive(): void
    {
        $formation = $this->licence(1, 'Chimie');
        $parcours = new Parcours($formation);
        $dpePar = (new DpeParcours())->setParcours($parcours);

        // Cas 1: Ouvert, sans remarques
        self::assertSame('', $this->service->resolveRemarques($formation, $dpePar, null));

        // Cas 2: Paramètre plateforme avec remarques
        $param = (new PlateformeAdmissionParametre())->setRemarques('Attention effectifs limités');
        self::assertSame('Attention effectifs limités', $this->service->resolveRemarques($formation, $dpePar, $param));

        // Cas 3: DpeParcours en fermeture définitive
        $dpePar->setEtatReconduction(TypeModificationDpeEnum::FERMETURE_DEFINITIVE);
        self::assertSame('Fermeture définitive', $this->service->resolveRemarques($formation, $dpePar, null));
        self::assertSame('Fermeture définitive | Attention effectifs limités', $this->service->resolveRemarques($formation, $dpePar, $param));

        // Cas 4: Formation en fermeture définitive (même si dpePar est OUVERT)
        $dpePar->setEtatReconduction(TypeModificationDpeEnum::OUVERT);
        $formation->setEtatReconduction(TypeModificationDpeEnum::FERMETURE_DEFINITIVE);
        self::assertSame('Fermeture définitive', $this->service->resolveRemarques($formation, $dpePar, null));
        self::assertSame('Fermeture définitive | Attention effectifs limités', $this->service->resolveRemarques($formation, $dpePar, $param));
    }

    public function testExportParcoursupIndiqueFermetureDefinitive(): void
    {
        $campagne = new CampagneCollecte();
        $psup = $this->psup();
        $formation = $this->licence(1, 'Droit');

        [$dpe, $l1] = $this->parcoursL1($formation, 10, 'Parcours Droit', 100);
        $dpe->setEtatReconduction(TypeModificationDpeEnum::FERMETURE_DEFINITIVE);

        $rows = $this->buildParcoursupRows([$dpe], [10 => [$l1]], [], $psup, $campagne);

        self::assertCount(1, $rows);
        self::assertSame('Fermeture définitive', $rows[0]['remarques']);
        self::assertSame('Fermeture définitive', $rows[0]['commentaire']);
    }

    public function testTypeParcoursEstLeRegimeDInscription(): void
    {
        $campagne = new CampagneCollecte();
        $psup = $this->psup();
        $formation = $this->licence(1, 'Informatique');

        [$dpe, $l1] = $this->parcoursL1($formation, 10, 'Parcours Info', 80);
        $dpe->getParcours()->setRegimeInscription([\App\Referentiel\RegimeInscription::FI, \App\Referentiel\RegimeInscription::FC]);

        $rows = $this->buildParcoursupRows([$dpe], [10 => [$l1]], [], $psup, $campagne);

        self::assertCount(1, $rows);
        self::assertSame('Formation Initiale, Formation Continue', (string) $rows[0]['type_parcours']);
        self::assertSame('Formation Initiale, Formation Continue', (string) $rows[0]['regime_inscription']);
    }

    public function testNouvelleCreationEnNPasMiseEnDiff(): void
    {
        $campagne = new CampagneCollecte();
        $psup = $this->psup();
        $formation = $this->licence(1, 'Chimie');

        [$dpe, $l1] = $this->parcoursL1($formation, 10, 'Chimie Appliquée', 60);

        // Pas de N-1 fourni
        $rows = $this->buildParcoursupRows([$dpe], [10 => [$l1]], [], $psup, $campagne);

        self::assertCount(1, $rows);
        /** @var \App\DTO\DiffObject $diffParcours */
        $diffParcours = $rows[0]['parcours'];
        self::assertNull($diffParcours->original);
        self::assertSame('Chimie Appliquée', $diffParcours->new);

        /** @var \App\DTO\DiffObject $diffOuvert */
        $diffOuvert = $rows[0]['annee_ouverte'];
        self::assertNull($diffOuvert->original);
        self::assertSame('OUI', $diffOuvert->new);

        /** @var \App\DTO\DiffObject $diffPlat */
        $diffPlat = $rows[0]['plateforme_proposee'];
        self::assertNull($diffPlat->original);
        self::assertSame('Active', $diffPlat->new);
    }

    public function testDiffAppliqueSiChangementParRapportAN1(): void
    {
        $campagne = new CampagneCollecte();
        $psup = $this->psup();
        $formation = $this->licence(1, 'Physique');

        [$dpeN, $l1N] = $this->parcoursL1($formation, 10, 'Physique Fondamentale', 50);
        $dpeN->getParcours()->setRegimeInscription([\App\Referentiel\RegimeInscription::FI]);

        [$dpeN1, $l1N1] = $this->parcoursL1($formation, 20, 'Physique Générale', 40);
        $dpeN1->getParcours()->setRegimeInscription([\App\Referentiel\RegimeInscription::FC]);
        $l1N1->setIsOuvert(false);
        $dpeN->getParcours()->setParcoursOrigineCopie($dpeN1->getParcours());

        $paramN1 = (new PlateformeAdmissionParametre())->setPlateforme($psup)->setActive(false);

        $method = new \ReflectionMethod($this->service, 'buildParcoursupRows');
        /** @var list<array<string, mixed>> $rows */
        $rows = $method->invoke(
            $this->service,
            [$dpeN],
            [10 => [$l1N]],
            [],
            [],
            $psup,
            $campagne,
            null,
            false,
            [20 => $dpeN1],
            [20 => [$l1N1]],
            [$l1N1->getId() => [$paramN1]]
        );

        self::assertCount(1, $rows);
        self::assertSame(1, $rows[0]['niveau']);

        // 1. Parcours en diff
        /** @var \App\DTO\DiffObject $diffParcours */
        $diffParcours = $rows[0]['parcours'];
        self::assertTrue($diffParcours->isDifferent());
        self::assertSame('Physique Générale', $diffParcours->original);
        self::assertSame('Physique Fondamentale', $diffParcours->new);

        // 2. Année ouverte en diff (NON -> OUI)
        /** @var \App\DTO\DiffObject $diffOuvert */
        $diffOuvert = $rows[0]['annee_ouverte'];
        self::assertTrue($diffOuvert->isDifferent());
        self::assertSame('NON', $diffOuvert->original);
        self::assertSame('OUI', $diffOuvert->new);

        // 3. Plateforme proposée en diff (Non active -> Active)
        /** @var \App\DTO\DiffObject $diffPlat */
        $diffPlat = $rows[0]['plateforme_proposee'];
        self::assertTrue($diffPlat->isDifferent());
        self::assertSame('Non active', $diffPlat->original);
        self::assertSame('Active', $diffPlat->new);

        // 4. Régime d'inscription en diff (Formation Continue -> Formation Initiale)
        /** @var \App\DTO\DiffObject $diffType */
        $diffType = $rows[0]['type_parcours'];
        self::assertTrue($diffType->isDifferent());
        self::assertSame('Formation Continue', $diffType->original);
        self::assertSame('Formation Initiale', $diffType->new);
    }

    public function testParcoursupRegroupeLAnneeEnTroncCommun(): void
    {
        $campagne = new CampagneCollecte();
        $psup = $this->psup();
        $reims = $this->licence(1, 'Histoire');
        $reims->setAnneeTroncCommun(1, true);

        $dpes = [];
        $annees = [];
        foreach ([10 => 'Archéologie', 11 => 'Histoire', 12 => 'Géographie'] as $id => $libelle) {
            [$dpes[], $annees[$id]] = $this->parcoursL1($reims, $id, $libelle, 200);
            $annees[$id] = [$annees[$id]];
        }

        $rows = $this->buildParcoursupRows($dpes, $annees, [], $psup, $campagne);

        self::assertCount(1, $rows);
        self::assertSame('Tronc commun : Archéologie / Histoire / Géographie', $rows[0]['parcours']);
        self::assertSame(200, $rows[0]['capacite_n']);
    }

    public function testParcoursupTroncCommunPartielConserveParcoursHorsTroncCommunEtCapaciteSpecifique(): void
    {
        $campagne = new CampagneCollecte();
        $psup = $this->psup();
        $licence = $this->licence(1, 'Sciences de la Vie');

        // Configuration d'un tronc commun partiel couvrant uniquement SVT et Chimie
        $licence->setTroncsCommuns([
            [
                'id' => 'tc_svt',
                'nom' => 'Tronc commun SVT',
                'annees' => [1],
                'parcours' => [10, 11],
            ],
        ]);

        $dpes = [];
        $annees = [];
        // SVT et Chimie ont une capacité mutualisée de 120
        [$dpes[], $annees[10]] = $this->parcoursL1($licence, 10, 'SVT', 120);
        $annees[10] = [$annees[10]];
        [$dpes[], $annees[11]] = $this->parcoursL1($licence, 11, 'Chimie', 120);
        $annees[11] = [$annees[11]];
        // Accès Santé est hors tronc commun avec sa capacité propre de 30
        [$dpes[], $annees[12]] = $this->parcoursL1($licence, 12, 'Accès Santé (LAS)', 30);
        $annees[12] = [$annees[12]];

        $rows = $this->buildParcoursupRows($dpes, $annees, [], $psup, $campagne);

        // Doit produire 2 lignes distinctes : le Tronc Commun SVT (120 places) et Accès Santé (30 places)
        self::assertCount(2, $rows);

        // Première ligne triée : Accès Santé
        self::assertSame('Accès Santé (LAS)', (string) $rows[0]['parcours']);
        self::assertSame(30, $rows[0]['capacite_n']);

        // Seconde ligne triée : Tronc Commun SVT regroupant SVT et Chimie
        self::assertSame('Tronc commun SVT : SVT / Chimie', (string) $rows[1]['parcours']);
        self::assertSame(120, $rows[1]['capacite_n']);
    }

    public function testFormatGroupParcoursLabel(): void
    {
        self::assertSame(
            'Tronc commun : Parcours A / Parcours B',
            $this->service->formatGroupParcoursLabel('Tronc commun', ['Parcours A', 'Parcours B'])
        );

        self::assertSame(
            'Tronc commun SVT : Parcours A / Parcours B',
            $this->service->formatGroupParcoursLabel('Tronc commun SVT', ['Parcours A', 'Parcours B'])
        );

        self::assertSame(
            'Tronc commun Sciences : Parcours A / Parcours B',
            $this->service->formatGroupParcoursLabel('Sciences', ['Parcours A', 'Parcours B'])
        );

        self::assertSame(
            'Portail Pasteur : Parcours A / Parcours B',
            $this->service->formatGroupParcoursLabel('Portail Pasteur', ['Parcours A', 'Parcours B'], 'portail')
        );

        self::assertSame(
            'Tronc commun : Parcours Unique',
            $this->service->formatGroupParcoursLabel('Tronc commun', ['Parcours Unique'])
        );
    }

    private function psup(): PlateformeAdmission
    {
        $psup = (new PlateformeAdmission())->setCode('parcoursup')->setLibelle('Parcoursup');
        $this->setId($psup, 1);

        return $psup;
    }

    public function testBuildExportTitleAvecDiplomesMultiples(): void
    {
        $campagne = (new CampagneCollecte())->setAnnee(2027);
        $plat = (new PlateformeAdmission())->setLibelle('eCandidat');

        $rows = [
            ['diplome' => 'M2'],
            ['diplome' => 'DNO'],
            ['diplome' => 'M2'], // doublon
            ['diplome' => 'DE'],
            ['diplome' => 'DAEU'],
        ];

        $titre = $this->service->buildExportTitle($plat, $campagne, null, null, $rows);
        self::assertSame('Annexe – eCandidat – formations proposées (M2, DNO, DE, DAEU) pour 2027-2028', $titre);
    }

    public function testBuildExportTitleAvecComposanteEtDiplomeUnique(): void
    {
        $campagne = (new CampagneCollecte())->setAnnee(2027);
        $plat = (new PlateformeAdmission())->setLibelle('eCandidat');
        $compo = (new Composante())->setSigle('IUT');
        $diplome = (new TypeDiplome())->setLibelleCourt('BUT');

        $titre = $this->service->buildExportTitle($plat, $campagne, $compo, $diplome, []);
        self::assertSame('Annexe – eCandidat – IUT – formations proposées (BUT) pour 2027-2028', $titre);
    }

    public function testSanitizeFilenameNettoieCaracteresInterdits(): void
    {
        $titre = 'Annexe – eCandidat – DU/DIU: Santé * ? <test>';
        $safe = $this->service->sanitizeFilename($titre);

        self::assertSame('Annexe – eCandidat – DU-DIU- Santé - - -test-', $safe);
    }

    public function testBuildSheetNameFormatEtLimite31Caracteres(): void
    {
        $plat = (new PlateformeAdmission())->setLibelle('eCandidat');
        $date = new \DateTimeImmutable('2026-11-04 17:30:00');

        $sheet = $this->service->buildSheetName($plat, null, $date);
        self::assertSame('eCandidat (04-11 17h30)', $sheet);
        self::assertLessThanOrEqual(31, mb_strlen($sheet));

        $platTresLong = (new PlateformeAdmission())->setLibelle('Plateforme nationale extrêmement longue et verbeuse');
        $sheetLong = $this->service->buildSheetName($platTresLong, null, $date);
        self::assertLessThanOrEqual(31, mb_strlen($sheetLong));
        self::assertStringEndsWith('(04-11 17h30)', $sheetLong);
    }

    public function testResolveEntityDiffPourParcoursDefautLaisseLeParcoursVide(): void
    {
        $formation = $this->licence(1, 'Informatique');
        $parcours = (new Parcours($formation))->setLibelle(Parcours::PARCOURS_DEFAUT);

        $diffs = $this->service->resolveEntityDiff($parcours, null);

        self::assertSame('', (string) $diffs['parcours']->new);
        self::assertNull($diffs['parcours']->original);
    }

    public function testResolveEntityDiffPourNouvelleCreationMetOriginalANullEtIsDifferentATrue(): void
    {
        $formation = $this->licence(1, 'Informatique');
        $parcours = (new Parcours($formation))->setLibelle('Génie Logiciel');

        $diffs = $this->service->resolveEntityDiff($parcours, null);

        self::assertNull($diffs['parcours']->original);
        self::assertSame('Génie Logiciel', $diffs['parcours']->new);
        self::assertTrue($diffs['parcours']->isDifferent());
        self::assertNull($diffs['mention']->original);
        self::assertSame('Informatique', $diffs['mention']->new);
        self::assertTrue($diffs['mention']->isDifferent());
    }

    public function testResolveRegimeInscriptionParcoursEtAnnee(): void
    {
        $formation = $this->licence(1, 'Droit');
        $parcours = (new Parcours($formation))->setRegimeInscription(['FI']);
        self::assertSame('FI', $this->service->resolveRegimeInscription($parcours));

        $annee = (new Annee())->setRegimeInscription(['FI', 'FC']);
        self::assertSame('FI, FC', $this->service->resolveRegimeInscription($parcours, $annee));
    }

    public function testResolveRowStatusAndDiffNouvelleCreation(): void
    {
        $formation = $this->licence(1, 'Histoire');
        $parcours = (new Parcours($formation))->setLibelle('Histoire moderne')->setRegimeInscription(['FI']);
        $annee = (new Annee())->setOrdre(1)->setIsOuvert(true)->setCapaciteAccueil(50);

        $status = $this->service->resolveRowStatusAndDiff(
            $parcours,
            $annee,
            true,
            null,
            ['hasN1' => false, 'anneeN1' => null, 'paramN1' => null, 'isOuvertN1' => false, 'isPsupN1' => false, 'isEcN1' => false, 'isMmN1' => false, 'dpeN1' => null]
        );

        self::assertTrue($status['diff_type_parcours']->isDifferent());
        self::assertNull($status['diff_type_parcours']->original);
        self::assertSame('FI', $status['diff_type_parcours']->new);
        self::assertTrue($status['diff_annee_ouverte']->isDifferent());
        self::assertNull($status['diff_annee_ouverte']->original);
        self::assertSame('OUI', $status['diff_annee_ouverte']->new);
    }

    public function testParcoursupPortailMemeFormationAfficheLignePortailSansParcoursEtComposante(): void
    {
        $campagne = new CampagneCollecte();
        $psup = $this->psup();
        $ufrSciences = (new Composante())->setSigle('UFR Sciences');
        $licence = $this->licence(1, 'Informatique');
        $licence->setComposantePorteuse($ufrSciences);

        $portail = (new Portail())->setLibelle('Portail Math-Info')->setCapaciteAccueil(150);
        $this->setId($portail, 99);

        [$dpe1, $annee1] = $this->parcoursL1($licence, 10, 'Parcours Info', 80);
        $annee1->setPortail($portail);
        [$dpe2, $annee2] = $this->parcoursL1($licence, 11, 'Parcours Math-Info', 70);
        $annee2->setPortail($portail);

        $dpes = [$dpe1, $dpe2];
        $annees = [10 => [$annee1], 11 => [$annee2]];

        $rows = $this->buildParcoursupRows($dpes, $annees, [], $psup, $campagne);

        self::assertCount(1, $rows);
        self::assertSame('UFR Sciences', $rows[0]['composante']);
        self::assertSame(1, $rows[0]['niveau']);
        self::assertSame('Portail Math-Info', (string) $rows[0]['mention']);
        self::assertSame('', (string) $rows[0]['parcours']);
        self::assertSame(150, $rows[0]['capacite_n']);
    }

    public function testParcoursupPortailInterFormationsComposantesDifferentesLaisseComposanteVide(): void
    {
        $campagne = new CampagneCollecte();
        $psup = $this->psup();

        $ufrSciences = (new Composante())->setSigle('UFR Sciences');
        $ufrLettres = (new Composante())->setSigle('UFR Lettres');

        $licenceSciences = $this->licence(1, 'Sciences et Humanités');
        $licenceSciences->setComposantePorteuse($ufrSciences);

        $licenceLettres = $this->licence(2, 'Humanités');
        $licenceLettres->setComposantePorteuse($ufrLettres);

        $portail = (new Portail())->setLibelle('Portail Sciences & Humanités');
        $this->setId($portail, 101);

        [$dpe1, $annee1] = $this->parcoursL1($licenceSciences, 20, 'Parcours Sciences', 60);
        $annee1->setPortail($portail);

        [$dpe2, $annee2] = $this->parcoursL1($licenceLettres, 21, 'Parcours Lettres', 60);
        $annee2->setPortail($portail);

        // Parcours 3 en L1 hors portail pour licenceSciences
        [$dpe3, $annee3] = $this->parcoursL1($licenceSciences, 22, 'Parcours Hors Portail', 50);

        $dpes = [$dpe1, $dpe2, $dpe3];
        $annees = [20 => [$annee1], 21 => [$annee2], 22 => [$annee3]];

        $rows = $this->buildParcoursupRows($dpes, $annees, [], $psup, $campagne);

        // Doit produire 2 lignes : 1 pour le portail et 1 pour le parcours hors portail
        self::assertCount(2, $rows);

        // Ligne du portail et Ligne hors portail
        $portailRow = null;
        $horsPortailRow = null;
        foreach ($rows as $r) {
            if ($r['mention'] === 'Portail Sciences & Humanités') {
                $portailRow = $r;
            } else {
                $horsPortailRow = $r;
            }
        }

        self::assertNotNull($portailRow);
        self::assertSame('', $portailRow['composante'], 'Composantes différentes -> composante vide');
        self::assertSame('Portail Sciences & Humanités', (string) $portailRow['mention']);
        self::assertSame('', (string) $portailRow['parcours'], 'Parcours doit être vide');
        self::assertSame(60, $portailRow['capacite_n'], 'La capacité du portail ne doit pas être cumulée : elle affiche la capacité mutualisée saisie');

        self::assertNotNull($horsPortailRow);
        self::assertSame('UFR Sciences', $horsPortailRow['composante']);
        self::assertSame(1, $horsPortailRow['niveau']);
        self::assertSame('Sciences et Humanités', (string) $horsPortailRow['mention']);
        self::assertSame('Parcours Hors Portail', (string) $horsPortailRow['parcours']);
    }

    public function testEefPortailAfficheLignePortailSansParcours(): void
    {
        $campagne = new CampagneCollecte();
        $ufrSciences = (new Composante())->setSigle('UFR Sciences');

        $licence1 = $this->licence(1, 'Maths');
        $licence1->setComposantePorteuse($ufrSciences);
        $licence2 = $this->licence(2, 'Physique');
        $licence2->setComposantePorteuse($ufrSciences);

        $portail = (new Portail())->setLibelle('Portail Math-Physique');
        $this->setId($portail, 202);

        [$dpe1, $annee1] = $this->parcoursL1($licence1, 30, 'Parcours Maths', 50);
        $annee1->setPortail($portail);

        [$dpe2, $annee2] = $this->parcoursL1($licence2, 31, 'Parcours Physique', 50);
        $annee2->setPortail($portail);

        $dpes = [$dpe1, $dpe2];
        $annees = [30 => [$annee1], 31 => [$annee2]];

        $method = new \ReflectionMethod($this->service, 'buildEefRows');
        /** @var list<array<string, mixed>> $rows */
        $rows = $method->invoke($this->service, $dpes, $annees, $campagne, null, []);

        self::assertCount(1, $rows);
        self::assertSame('UFR Sciences', $rows[0]['composante']);
        self::assertSame('Portail Math-Physique', (string) $rows[0]['mention']);
        self::assertSame('', (string) $rows[0]['parcours']);
        self::assertSame('L1', $rows[0]['niveau']);
    }

    private function licence(int $id, string $mention): Formation
    {
        $formation = (new Formation(null))
            ->setMentionTexte($mention)
            ->setTypeDiplome((new TypeDiplome())->setLibelleCourt('L'));
        $this->setId($formation, $id);

        return $formation;
    }

    /**
     * @return array{DpeParcours, Annee}
     */
    private function parcoursL1(Formation $formation, int $id, string $libelle, int $capacite): array
    {
        $parcours = new Parcours($formation);
        $parcours->setLibelle($libelle);
        $this->setId($parcours, $id);
        $formation->addParcour($parcours);

        $annee = (new Annee())->setOrdre(1)->setIsOuvert(true)->setCapaciteAccueil($capacite)->setParcours($parcours);
        $this->setId($annee, $id * 100);

        return [(new DpeParcours())->setParcours($parcours), $annee];
    }

    private function setId(object $entity, int $id): void
    {
        (new \ReflectionProperty($entity, 'id'))->setValue($entity, $id);
    }

    /**
     * @param list<DpeParcours> $dpes
     * @param array<int, list<Annee>> $annees
     * @param array<int, list<PlateformeAdmissionParametre>> $params
     * @return list<array<string, mixed>>
     */
    private function buildParcoursupRows(array $dpes, array $annees, array $params, PlateformeAdmission $psup, CampagneCollecte $campagne): array
    {
        $method = new \ReflectionMethod($this->service, 'buildParcoursupRows');

        return $method->invoke($this->service, $dpes, $annees, [], $params, $psup, $campagne, null, false, [], [], []);
    }
}
