# Workflow Operations Bundle

This local package provides an operational orchestration layer around Symfony Workflow.

It is developed inside ORéOF while keeping a strict boundary from the application's domain model. The bundle must never depend on ORéOF entities, users, forms, repositories or notification services.

## Initial scope

- describe a workflow transition as an operation;
- inspect its Workflow availability and authorization;
- collect structured business blockers from tagged providers;
- carry typed input, actor and metadata context;
- execute a tagged domain handler before applying the Symfony transition.

Forms, Doctrine persistence, notifications and ORéOF business rules remain outside the initial core.

## Workflow context

Operation input is kept as a structured `input` entry and is also exposed at
the top level of the Symfony Workflow context for event subscribers. Explicit
aliases preserve existing subscriber contracts without coupling the bundle to
domain field names:

```yaml
metadata:
    context:
        aliases:
            argumentaire: motif
```

`OperationContextNormalizer` reads this transition metadata and builds the
context from any normalized application input. With an input containing
`date` and `argumentaire`, subscribers can read
`$event->getContext()['date']` and `$event->getContext()['motif']`, while new
code can consume the complete normalized payload from `input`.

During its incubation inside ORéOF, the package namespace is exposed by the root Composer autoloader. The package keeps its own `composer.json` so it can be moved to a path repository or extracted without reorganizing its sources.

## Authorization metadata

An operation can delegate its authorization to any Symfony voter:

```yaml
metadata:
    authorization:
        attribute: 'DOCUMENT_PUBLISH'
        subject: 'operation' # subject, operation or none
        reason: 'document.publish.denied'
```

With `subject: operation`, the voter receives an `OperationAuthorizationSubject` containing the domain subject, operation definition and execution context.
