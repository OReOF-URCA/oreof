<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle\Tests\Operation;

use Dannebicque\WorkflowOperationsBundle\Operation\OperationFormResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\AbstractType;

final class OperationFormResolverTest extends TestCase
{
    public function testItResolvesAConfiguredSymfonyFormType(): void
    {
        $definition = (new OperationFormResolver())->resolveRequired([
            'form' => [
                'type' => DummyOperationType::class,
                'options' => ['mode' => 'confirmation'],
                'template' => 'operation/form.html.twig',
            ],
        ]);

        self::assertSame(DummyOperationType::class, $definition->type);
        self::assertSame(['mode' => 'confirmation'], $definition->options);
        self::assertSame('operation/form.html.twig', $definition->template);
    }

    public function testItReturnsNullWhenTheOperationHasNoForm(): void
    {
        self::assertNull((new OperationFormResolver())->resolve([]));
    }

    public function testItRejectsAnInvalidFormType(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new OperationFormResolver())->resolve([
            'form' => ['type' => \stdClass::class],
        ]);
    }
}

final class DummyOperationType extends AbstractType
{
}
