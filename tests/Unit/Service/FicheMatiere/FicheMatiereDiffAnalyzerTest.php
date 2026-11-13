<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\FicheMatiere;

use App\DTO\Comparison\FicheMatiereChangeItem;
use App\DTO\Comparison\FicheMatiereChangeReport;
use App\Entity\CampagneCollecte;
use App\Entity\FicheMatiere;
use App\Entity\Mccc;
use App\Service\FicheMatiere\FicheMatiereDiffAnalyzer;
use App\Service\VersioningFicheMatiere;
use PHPUnit\Framework\TestCase;

final class FicheMatiereDiffAnalyzerTest extends TestCase
{
    public function testAnalyzeReturnsNoReferenceWhenNeitherSnapshotNorOrigineExists(): void
    {
        $versioning = $this->createStub(VersioningFicheMatiere::class);
        $analyzer = new FicheMatiereDiffAnalyzer($versioning, sys_get_temp_dir());

        $fiche = (new FicheMatiere())
            ->setLibelle('Algorithmique avancée')
            ->setSlug('algorithmique-avancee');

        $report = $analyzer->analyze($fiche);

        self::assertSame(FicheMatiereChangeReport::SOURCE_NONE, $report->sourceType);
        self::assertFalse($report->hasReference());
        self::assertFalse($report->hasChanges());
    }

    public function testAnalyzeDetectsChangesAgainstOrigineN1(): void
    {
        $versioning = $this->createStub(VersioningFicheMatiere::class);
        $analyzer = new FicheMatiereDiffAnalyzer($versioning, sys_get_temp_dir());

        $campagneN1 = (new CampagneCollecte())->setLibelle('Campagne 2025-2026');

        $origineN1 = (new FicheMatiere())
            ->setLibelle('Algorithmique 1')
            ->setSlug('algorithmique-1-2025')
            ->setDescription('<p>Introduction aux algorithmes et structures de données.</p>')
            ->setObjectifs('<p>Maîtriser les tableaux et listes.</p>')
            ->setVolumeCmPresentiel(12.0)
            ->setVolumeTdPresentiel(12.0)
            ->setVolumeTpPresentiel(6.0)
            ->setEcts(3.0)
            ->setTypeMccc('ct')
            ->setCampagneCollecte($campagneN1);

        $mcccN1 = (new Mccc())
            ->setLibelle('Examen écrit')
            ->setNumeroSession(1)
            ->setNumeroEpreuve(1)
            ->setExamenTerminal(true)
            ->setControleContinu(false)
            ->setPourcentage(100.0);
        $origineN1->addMccc($mcccN1);

        $current = (new FicheMatiere())
            ->setLibelle('Algorithmique et complexité')
            ->setSlug('algorithmique-et-complexite')
            ->setDescription('<p>Introduction aux algorithmes, structures de données et complexité asymptotique.</p>')
            ->setObjectifs('<p>Maîtriser les tableaux et listes.</p>')
            ->setVolumeCmPresentiel(14.0)
            ->setVolumeTdPresentiel(12.0)
            ->setVolumeTpPresentiel(8.0)
            ->setEcts(4.0)
            ->setTypeMccc('cci')
            ->setFicheMatiereOrigineCopie($origineN1);

        $mcccCur = (new Mccc())
            ->setLibelle('Contrôle continu')
            ->setNumeroSession(1)
            ->setNumeroEpreuve(1)
            ->setExamenTerminal(false)
            ->setControleContinu(true)
            ->setPourcentage(100.0);
        $current->addMccc($mcccCur);

        $report = $analyzer->analyze($current);

        self::assertSame(FicheMatiereChangeReport::SOURCE_ORIGINE_N1, $report->sourceType);
        self::assertSame('Fiche d\'origine N-1 (Campagne 2025-2026)', $report->sourceLabel);
        self::assertTrue($report->hasChanges());

        $byCategory = $report->getChangesByCategory();
        self::assertArrayHasKey(FicheMatiereChangeItem::CATEGORY_IDENTITE, $byCategory);
        self::assertArrayHasKey(FicheMatiereChangeItem::CATEGORY_DESCRIPTIFS, $byCategory);
        self::assertArrayHasKey(FicheMatiereChangeItem::CATEGORY_VOLUMES, $byCategory);
        self::assertArrayHasKey(FicheMatiereChangeItem::CATEGORY_MCCC, $byCategory);
        self::assertSame('Identité & organisation', $byCategory[FicheMatiereChangeItem::CATEGORY_IDENTITE][0]->getCategoryLabel());
        self::assertContains('Identité & organisation', $report->getImpactedCategoriesLabels());
    }
}
