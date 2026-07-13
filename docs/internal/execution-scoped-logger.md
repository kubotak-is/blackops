# Execution Scoped Logger

ExecutionScopedLogger is an internal PSR-3 decorator.

It delegates to an inner PSR-3 logger and enriches each record with the current Operation metadata when ExecutionScopeProvider has an active scope.

User context is placed under the `context` field, so user-provided keys cannot overwrite framework-owned fields such as `operation`.

User context is filtered with the shared Sensitive Projection Filter before delegation. Scope metadata is added by the framework and is not sourced from user context.
