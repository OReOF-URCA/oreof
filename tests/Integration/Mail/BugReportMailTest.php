<?php

declare(strict_types=1);

namespace App\Tests\Integration\Mail;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

class BugReportMailTest extends KernelTestCase
{
    public function testPageDuSignalementEstUnLien(): void
    {
        $reporter = new User();
        $reporter->setNom('Caruana');
        $reporter->setPrenom('Sylvain');
        $reporter->setEmail('sylvain.caruana@univ-test.fr');

        $html = static::getContainer()->get(Environment::class)->render('mails/bug_report/report.html.twig', [
            'reporter' => $reporter,
            'title' => 'Export incomplet',
            'message' => 'Bonjour',
            'context' => ['formationLabel' => 'Non définie', 'parcoursLabel' => 'Non défini', 'page' => 'https://oreof.univ-reims.fr/offre/composante/3'],
            'submittedAt' => new \DateTimeImmutable('2026-11-02 14:03'),
        ]);

        self::assertStringContainsString('<a href="https://oreof.univ-reims.fr/offre/composante/3">https://oreof.univ-reims.fr/offre/composante/3</a>', $html);
    }
}
