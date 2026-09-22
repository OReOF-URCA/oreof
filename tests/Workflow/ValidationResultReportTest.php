<?php

declare(strict_types=1);

namespace App\Tests\Workflow;

use App\Workflow\Validator\ValidationCheck;
use App\Workflow\Validator\ValidationError;
use App\Workflow\Validator\ValidationResult;
use App\Workflow\Validator\ValidationWarning;
use PHPUnit\Framework\TestCase;

final class ValidationResultReportTest extends TestCase
{
    public function testCheckStatusReflectsTheExecutedGroupResult(): void
    {
        $passed = ValidationCheck::fromResult('passed', 'Contrôle réussi', ['errors' => [], 'warnings' => []]);
        $warning = ValidationCheck::fromResult('warning', 'Contrôle avec alerte', [
            'errors' => [],
            'warnings' => [ValidationWarning::create('warning', 'Avertissement')],
        ]);
        $failed = ValidationCheck::fromResult('failed', 'Contrôle bloquant', [
            'errors' => [ValidationError::create('failed', 'Erreur')],
            'warnings' => [ValidationWarning::create('warning', 'Avertissement')],
        ]);

        self::assertSame(ValidationCheck::STATUS_PASSED, $passed->status);
        self::assertSame(ValidationCheck::STATUS_WARNING, $warning->status);
        self::assertSame(1, $warning->warningCount);
        self::assertSame(ValidationCheck::STATUS_FAILED, $failed->status);
        self::assertSame(1, $failed->errorCount);
    }

    public function testChecksArePreservedByResultSerializationAndMerge(): void
    {
        $firstCheck = new ValidationCheck('first', 'Premier contrôle', ValidationCheck::STATUS_PASSED);
        $secondCheck = new ValidationCheck('second', 'Second contrôle', ValidationCheck::STATUS_WARNING, 0, 1);

        $result = ValidationResult::success([], [$firstCheck])->merge(
            ValidationResult::success([], [$secondCheck]),
        );

        self::assertSame([$firstCheck, $secondCheck], $result->getChecks());
        self::assertSame('first', $result->toArray()['checks'][0]['code']);
        self::assertSame('warning', $result->toArray()['checks'][1]['status']);
    }
}
