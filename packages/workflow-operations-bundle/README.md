# Workflow Operations Bundle

This local package provides an operational orchestration layer around Symfony Workflow.

It is developed inside ORéOF while keeping a strict boundary from the application's domain model. The bundle must never depend on ORéOF entities, users, forms, repositories or notification services.

## Initial scope

- describe a workflow transition as an operation;
- collect structured business blockers from tagged providers;
- carry typed input, actor and metadata context;
- progressively centralize authorization, inspection and execution.

Forms, Doctrine persistence, notifications and ORéOF business rules remain outside the initial core.

During its incubation inside ORéOF, the package namespace is exposed by the root Composer autoloader. The package keeps its own `composer.json` so it can be moved to a path repository or extracted without reorganizing its sources.
