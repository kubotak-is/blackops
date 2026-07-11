# Monolog JSONL Backend

`MonologJsonlLoggerFactory` is the internal factory for the MVP application-log sink. It configures Monolog 3 with one `StreamHandler` and one `JsonFormatter`, then returns the result as PSR-3 `LoggerInterface`.

Monolog classes remain implementation details of `BlackOps\Internal\Logging`. Core contracts, Operation APIs, handlers, and services depend on PSR-3 rather than Monolog types.

## Defaults and Configuration

The deterministic defaults are:

```text
channel: blackops
minimum level: info
JSON batch mode: newline-delimited records
record newline: enabled
```

Create a file-backed logger:

```php
use BlackOps\Internal\Logging\MonologJsonlLoggerFactory;

$backend = new MonologJsonlLoggerFactory()->create(
    stream: __DIR__ . '/../../var/log/application.jsonl',
    channel: 'application',
    minimumLevel: 'info',
);
```

Tests and application composition may pass an open stream resource instead of a path:

```php
$stream = fopen('php://stderr', 'w');
$backend = new MonologJsonlLoggerFactory()->create($stream);
```

The Factory does not implement file opening, writes, level comparison, or JSON encoding itself. `StreamHandler` owns stream/path handling and minimum-level filtering. `JsonFormatter` owns normalization and emits one newline-terminated JSON object per record.

Stream initialization and write exceptions are not caught or replaced by the Factory. Composition code therefore sees the original Monolog exception and can apply its own application-log delivery policy.

## Execution Scope and Sensitive Filtering

The Monolog backend is an output sink. Application and handler logging should compose it inside `ExecutionScopedLogger`:

```php
use BlackOps\Internal\Logging\ExecutionScopedLogger;
use BlackOps\Internal\Logging\MonologJsonlLoggerFactory;

$backend = new MonologJsonlLoggerFactory()->create(
    stream: $stream,
    channel: 'operations',
    minimumLevel: 'debug',
);

$logger = new ExecutionScopedLogger(
    inner: $backend,
    scope: $executionScopeProvider,
    sensitive: $sensitiveProjectionFilter,
);
```

The decorator reads the current Operation scope, places framework-owned metadata under `operation`, projects user context through `SensitiveProjectionFilter`, and delegates only the enriched and filtered context to Monolog. It does not copy the unfiltered user context into another field.

The resulting Monolog JSON record retains its standard fields, including:

```text
channel
level
level_name
message
context
datetime
extra
```

The `context` field contains the framework structure:

```json
{
  "operation": {
    "id": "019...",
    "type": "report.generate",
    "attemptId": "019...",
    "strategy": "BlackOps\\Core\\Execution\\Deferred"
  },
  "context": {
    "reportId": "report-123"
  }
}
```

Reserved sensitive keys such as password, token, and secret are removed before this record reaches `JsonFormatter`. Direct use of the backend does not add Operation scope or run the framework sensitive filter; use the decorator for application context.

## Extension Boundary

Handlers and services receive `Psr\Log\LoggerInterface`. Applications may replace the backend with another PSR-3 implementation without changing Core or Operation code.

Rotation, buffering, network handlers, OpenTelemetry, and full production DI wiring remain separate composition concerns. Adding those capabilities should preserve the existing order: framework scope enrichment and sensitive projection first, output-adapter processing second.
