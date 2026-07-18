# Monolog JSONL Backend

`MonologJsonlLoggerFactory` is the internal factory for the MVP application-log sink. It configures Monolog 3 with one `StreamHandler` and one `JsonFormatter`, then returns the result as PSR-3 `LoggerInterface`.

Monolog classes remain implementation details of `BlackOps\Internal\Logging`. Core contracts, Operation APIs, handlers, and services depend on PSR-3 rather than Monolog types.

## Installed Application Configuration

Optional `config/logging.php`は次のShapeだけを受け付ける。Fileがない場合も同じ既定を使い、Loggingを無効にはしない。

```php
<?php

declare(strict_types=1);

return [
    'backend' => [
        'driver' => 'jsonl',
        'stream' => 'php://stderr',
        'channel' => 'blackops',
        'minimum_level' => 'info',
    ],
];
```

`driver`は`jsonl`だけ、`stream`は`php://stderr`、`php://stdout`、または`/`で始まる絶対Local File Pathだけを許可する。Relative Path、NUL、その他のStream Wrapper、Network URIは拒否する。`channel`は空、前後Whitespace、Control Characterを拒否する。`minimum_level`はlowercase完全一致のPSR-3 8 Levelだけを受け付ける。

型、未知Key、Custom Driverが不正ならHTTP／WorkerのRuntime CompositionをRequest／Attempt開始前に失敗させる。ExceptionへConfig値を反射しない。Config FileとEnvironmentはApplication作成時のSnapshotから一度だけ解決し、Request、Attempt、Log Recordごとに再読込しない。

## Factory Defaults

The deterministic defaults are:

```text
channel: blackops
minimum level: info
JSON batch mode: newline-delimited records
record newline: enabled
```

内部Testまたは低水準Compositionでfile-backed loggerを直接作る場合は次のFactoryを使う。

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

Factory自体はStream初期化とWrite Exceptionを吸収しない。Installed Applicationは必ずFactoryの結果を`ExecutionScopedLogger`の内側へ置き、最初のOpen／Write Failureを含めBest-effortで吸収する。別StreamへFallbackしない。

Retention audit logging composes this same backend directly with `LoggingRetentionPurgeAuditPort`. The decorator emits one `info` record whose context contains only the typed purge-audit metadata, so its backend must accept the `info` level; the Factory default does. Unlike ordinary application logging, this audit path is fail-closed: a backend exception propagates into the purge transaction so the database audit and deletion roll back together. Do not place `ExecutionScopedLogger` around this system-audit path because its operation scope is unrelated to maintenance execution and the audit record already carries the target Operation ID explicitly.

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

Handlers and services receive `Psr\Log\LoggerInterface`。Phase 14のInstalled Application ConfigはBuilt-in JSONLだけを選択でき、Custom Backend、Handler List、Remote SinkはPublic Configuration Surfaceではない。

FrameworkはOperation ID相関、Safe Envelope、Sensitive Filter、JSONL Backend、Best-effort書込境界を所有する。Application／Infrastructureはstdout／stderrまたはLocal File以降の収集、Delivery保証、Rotation、Retention、Disk Capacity、Access Control、Alertを所有する。FrameworkはLog到達、保存期間、Alert発火を保証しない。
