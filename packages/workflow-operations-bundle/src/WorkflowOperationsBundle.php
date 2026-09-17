<?php

declare(strict_types=1);

namespace Dannebicque\WorkflowOperationsBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

final class WorkflowOperationsBundle extends AbstractBundle
{
    public function loadExtension(
        array $config,
        ContainerConfigurator $container,
        ContainerBuilder $builder,
    ): void {
        $container->services()
            ->defaults()
                ->autowire()
                ->autoconfigure()
            ->set(Operation\OperationBlockerCollector::class)
                ->args([tagged_iterator('workflow_operations.blocker_provider')])
            ->set(Operation\OperationAuthorizationChecker::class)
                ->args([tagged_iterator('workflow_operations.authorizer')])
            ->set(Operation\OperationHandlerRegistry::class)
                ->args([tagged_iterator('workflow_operations.handler')])
            ->set(Operation\OperationCompletionHandlerRegistry::class)
                ->args([tagged_iterator('workflow_operations.completion_handler')])
            ->set(Operation\WorkflowOperationFactory::class)
            ->set(Operation\OperationContextNormalizer::class)
            ->set(Operation\WorkflowOperationInspector::class)
            ->set(Operation\WorkflowOperationExecutor::class)
            ->set(Security\SecurityMetadataOperationAuthorizer::class)
        ;
    }
}
