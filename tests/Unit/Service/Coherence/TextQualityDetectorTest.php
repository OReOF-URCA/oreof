<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Coherence;

use App\DTO\Coherence\CoherenceIssue;
use App\Service\Coherence\TextQualityDetector;
use PHPUnit\Framework\TestCase;

final class TextQualityDetectorTest extends TestCase
{
    private TextQualityDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new TextQualityDetector();
    }

    public function testHtmlToPlainTextCleansTagsAndEntities(): void
    {
        $raw = '<p>Objectifs&nbsp;: ma&icirc;triser les concepts.</p><p>Deuxi&egrave;me paragraphe.</p>';

        $plain = $this->detector->htmlToPlainText($raw);

        self::assertStringContainsString('Objectifs', $plain);
        self::assertStringContainsString('maîtriser les concepts.', $plain);
        self::assertStringContainsString('Deuxième paragraphe.', $plain);
        self::assertStringNotContainsString('<p>', $plain);
    }

    public function testDetectsPlaceholderAndFictionalContent(): void
    {
        $loremIssues = $this->detector->analyzeField(
            field: 'objectifsParcours',
            fieldLabel: 'Objectifs du parcours',
            rawValue: '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>',
        );

        self::assertCount(1, $loremIssues);
        self::assertSame(CoherenceIssue::SEVERITY_ALERT, $loremIssues[0]->severity);
        self::assertSame(CoherenceIssue::CATEGORY_FICTIF, $loremIssues[0]->category);

        $todoIssues = $this->detector->analyzeField(
            field: 'contenuFormation',
            fieldLabel: 'Contenu de la formation',
            rawValue: 'Texte à compléter ultérieurement par le responsable pédagogique.',
        );

        self::assertCount(1, $todoIssues);
        self::assertSame(CoherenceIssue::SEVERITY_ALERT, $todoIssues[0]->severity);
        self::assertSame(CoherenceIssue::CATEGORY_FICTIF, $todoIssues[0]->category);

        $testIssues = $this->detector->analyzeField(
            field: 'motsCles',
            fieldLabel: 'Mots-clés',
            rawValue: 'test',
            expectSubstantialText: false,
        );

        self::assertCount(1, $testIssues);
        self::assertSame(CoherenceIssue::SEVERITY_ALERT, $testIssues[0]->severity);
    }

    public function testDetectsRepeatedCharactersAndLowLetterRatio(): void
    {
        $repeated = $this->detector->analyzeField(
            field: 'debouches',
            fieldLabel: 'Débouchés',
            rawValue: 'aaaaaaaaaaaaaaaaaaaa',
        );

        self::assertCount(1, $repeated);
        self::assertSame(CoherenceIssue::SEVERITY_ALERT, $repeated[0]->severity);
        self::assertSame(CoherenceIssue::CATEGORY_REMPLISSAGE, $repeated[0]->category);

        $digitsOnly = $this->detector->analyzeField(
            field: 'debouches',
            fieldLabel: 'Débouchés',
            rawValue: '123456 789012 345678 / --- 99',
        );

        self::assertCount(1, $digitsOnly);
        self::assertSame(CoherenceIssue::SEVERITY_ALERT, $digitsOnly[0]->severity);
    }

    public function testDetectsTitleCopyAndTooShortDescriptions(): void
    {
        $titleCopy = $this->detector->analyzeField(
            field: 'objectifsParcours',
            fieldLabel: 'Objectifs du parcours',
            rawValue: 'Informatique et Systèmes Distribués',
            expectSubstantialText: true,
            referenceLabels: ['Informatique et Systèmes Distribués'],
        );

        self::assertCount(1, $titleCopy);
        self::assertSame(CoherenceIssue::SEVERITY_WARNING, $titleCopy[0]->severity);
        self::assertSame(CoherenceIssue::CATEGORY_REMPLISSAGE, $titleCopy[0]->category);

        $shortText = $this->detector->analyzeField(
            field: 'resultatsAttendus',
            fieldLabel: 'Résultats attendus',
            rawValue: 'Savoir coder.',
            expectSubstantialText: true,
            minChars: 25,
            minWords: 5,
        );

        self::assertCount(1, $shortText);
        self::assertSame(CoherenceIssue::SEVERITY_WARNING, $shortText[0]->severity);
    }

    public function testDetectsLowLexicalDiversity(): void
    {
        $issues = $this->detector->analyzeField(
            field: 'contenuFormation',
            fieldLabel: 'Contenu',
            rawValue: 'formation formation formation formation formation formation formation formation formation formation',
            expectSubstantialText: true,
            minChars: 20,
            minWords: 5,
        );

        self::assertCount(1, $issues);
        self::assertSame(CoherenceIssue::SEVERITY_ALERT, $issues[0]->severity);
        self::assertSame(CoherenceIssue::CATEGORY_REMPLISSAGE, $issues[0]->category);
    }

    public function testDetectsDuplicateFieldsAcrossForm(): void
    {
        $duplicates = $this->detector->detectDuplicateFields([
            'objectifsParcours' => [
                'label' => 'Objectifs du parcours',
                'text' => 'Former des spécialistes capables de concevoir des architectures logicielles distribuées.',
            ],
            'contenuFormation' => [
                'label' => 'Contenu de la formation',
                'text' => '<p>Former des spécialistes capables de concevoir des architectures logicielles distribuées.</p>',
            ],
        ]);

        self::assertCount(0, $duplicates);

        // When passed raw HTML already normalized to plain text (as done by PayloadBuilder)
        $plain = $this->detector->htmlToPlainText('<p>Former des spécialistes capables de concevoir des architectures logicielles distribuées.</p>');
        $duplicatesPlain = $this->detector->detectDuplicateFields([
            'objectifsParcours' => [
                'label' => 'Objectifs du parcours',
                'text' => $plain,
            ],
            'contenuFormation' => [
                'label' => 'Contenu de la formation',
                'text' => $plain,
            ],
        ]);

        self::assertCount(1, $duplicatesPlain);
        self::assertSame('contenuFormation', $duplicatesPlain[0]->field);
        self::assertSame(CoherenceIssue::SEVERITY_WARNING, $duplicatesPlain[0]->severity);
    }

    public function testValidAcademicTextPassesWithoutIssues(): void
    {
        $issues = $this->detector->analyzeField(
            field: 'objectifsParcours',
            fieldLabel: 'Objectifs du parcours',
            rawValue: '<p>Ce parcours vise à former des ingénieurs capables de concevoir, sécuriser et exploiter des systèmes d\'information distribués à haute disponibilité.</p>',
            expectSubstantialText: true,
            minChars: 30,
            minWords: 6,
            referenceLabels: ['Systèmes Distribués'],
        );

        self::assertSame([], $issues);
    }
}
