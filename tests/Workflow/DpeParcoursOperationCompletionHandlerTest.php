<?php

declare(strict_types=1);

namespace App\Tests\Workflow;

use App\Entity\DpeParcours;
use App\Message\GenerateDpeMcccBackup;
use App\Repository\DpeDemandeRepository;
use App\Workflow\Operation\Handler\DpeParcoursOperationCompletionHandler;
use Dannebicque\WorkflowOperationsBundle\Model\OperationContext;
use Dannebicque\WorkflowOperationsBundle\Model\WorkflowOperation;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class DpeParcoursOperationCompletionHandlerTest extends TestCase
{
    public function testCfvuValidationQueuesTheMcccBackup(): void
    {
        $subject = new DpeParcours();
        $id = new \ReflectionProperty($subject, 'id');
        $id->setValue($subject, 42);

        $dispatched = [];
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $message, array $stamps = []) use (&$dispatched): Envelope {
                $dispatched[] = $message;

                return Envelope::wrap($message, $stamps);
            });

        $handler = new DpeParcoursOperationCompletionHandler(
            $this->createMock(DpeDemandeRepository::class),
            $messageBus,
        );

        $handler->complete(
            $subject,
            new WorkflowOperation('dpeParcours', 'valider_cfvu'),
            OperationContext::empty(),
        );

        self::assertCount(1, $dispatched);
        self::assertInstanceOf(GenerateDpeMcccBackup::class, $dispatched[0]);
        self::assertSame(42, $dispatched[0]->dpeParcoursId);
    }

    public function testAnotherTransitionDoesNotQueueTheMcccBackup(): void
    {
        $subject = new DpeParcours();
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $handler = new DpeParcoursOperationCompletionHandler(
            $this->createMock(DpeDemandeRepository::class),
            $messageBus,
        );

        $handler->complete(
            $subject,
            new WorkflowOperation('dpeParcours', 'valider_central'),
            OperationContext::empty(),
        );
    }
}
