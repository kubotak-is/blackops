# D099: Production Logging Configuration

Status: Decided

## Context

P14-006では、HTTP Request、Deferred Worker、Nested Operation、Long-running Loopが同じPSR-3 Backend設定を使用し、Application Configuration SnapshotからProcess起動時に一度だけ解決する必要がある。

現在のRuntimeは`RuntimeLoggingServiceInjector`が`MonologJsonlLoggerFactory`を直接呼び、常に`php://stderr`、Channel `blackops`、Minimum Level `info`を使用する。HTTP RuntimeとWorker Runtimeは同じ既定を持つが、Installed Applicationから設定できず、Canonical `config/logging.php`も存在しない。

一方、`ExecutionScopedLogger`はFramework所有の相関・Sensitive Filterを担うため、ApplicationがPSR-3 Backendを選べても、このDecoratorを迂回してはならない。Backend初期化失敗とRecord書込失敗の扱いも、Primary Operation FailureやTerminal Lifecycleを壊さない既存契約と整合させる必要がある。

このDecisionではPhase 14のPublic Configuration ShapeとBackend境界だけを決める。Remote Collector、OpenTelemetry、Metric、Dashboard、Network Handler、Rotationは対象外とする。

## Question 1: Canonical Backend Configuration

Installed ApplicationはPSR-3 Backendをどう設定するか。

### Options

- A: `config/logging.php`にBuilt-in JSONL BackendのCanonical設定を追加する。Phase 14のDriverは`jsonl`だけとし、HTTPとWorkerは同じApplication Configuration Snapshotから一度だけ解決する。Frameworkは必ず`ExecutionScopedLogger`でBackendを包み、Custom BackendのPublic Selectionは後続Phaseへ送る
- B: `config/logging.php`を追加せず、Application Service Providerが`Psr\Log\LoggerInterface`を登録する方式だけをCanonicalにする。FrameworkはContainerからBackendを取得して`ExecutionScopedLogger`で包む
- C: Phase 14でAとBの両方をPublic Contractにし、Config DriverとContainer Serviceの優先順位、Custom Driver Registryまで実装する

### Recommendation

Aを推奨する。

P14-006の目的はProduction相関と安全な既定を固定することであり、Built-in JSONLなら現在の実装をInstalled Application設定へ引き上げるだけでHTTP／Workerを統一できる。BはConfig Snapshotから一度だけ解決する合意とずれ、Service ProviderのCompile-time／Runtime境界も追加設計が必要になる。CはDriver Extension APIをPhase 14へ拡大しすぎる。

推奨Shapeは次とする。

```php
return [
    'backend' => [
        'driver' => 'jsonl',
        'stream' => 'php://stderr',
        'channel' => 'blackops',
        'minimum_level' => 'info',
    ],
];
```

[ANSWER]

A

[/ANSWER]

## Question 2: Allowed JSONL Stream

Built-in JSONL Backendの`stream`へ何を許可するか。

### Options

- A: `php://stderr`、`php://stdout`、絶対Local File Pathだけを許可する。Relative Path、任意PHP Wrapper、Network URIは拒否する
- B: Monolog `StreamHandler`が受け付ける文字列をそのまま許可し、任意PHP WrapperやNetwork URIもApplication責務とする
- C: Phase 14では`php://stderr`だけを許可し、stdoutとFile Pathも後続Phaseへ送る

### Recommendation

Aを推奨する。

Container Runtimeのstdout／stderrと、Host管理のLocal JSONL Fileの両方を扱える。一方、任意WrapperやNetwork URIを許可するとRemote Sink、Credential、Timeout、Retry、TLSの責任がPhase 14へ入り、Backend FailureがOperation Runtimeへ与える影響も広がる。Relative PathはWorking Directory依存になるため拒否する。

[ANSWER]

A

[/ANSWER]

## Question 3: Invalid Configuration and Runtime Failure

Backend設定または書込が失敗した場合をどう扱うか。

### Options

- A: Configの型、Driver、Stream、Channel、LevelはApplication Runtime Composition時に厳密検証してFail-fastする。起動後のOpen／Write Failureは`ExecutionScopedLogger`がBest-effortで吸収し、Primary Throwable、Journal、HTTP Response、Worker継続を変更しない。別Sinkへの暗黙Fallbackはしない
- B: Invalid ConfigまたはBackend Failure時は警告なしで`php://stderr`へFallbackし、Applicationを継続する
- C: ConfigもBackendも最初のLog RecordまでLazyにし、失敗時はOperationを失敗させる

### Recommendation

Aを推奨する。

設定ミスを起動時に発見しつつ、実行中のLogging二次障害をBusiness Operationの成否へ昇格させない。暗黙Fallbackは「どこにLogが出たか」を不明瞭にし、Cは既存のBest-effort Application Log契約に反する。

[ANSWER]

A

[/ANSWER]

## Question 4: Disable Switch

Application／Framework Error LogをConfigで無効化できるようにするか。

### Options

- A: Phase 14ではDisable Switchを提供しない。Config欠落時もJSONL `php://stderr`／`blackops`／`info`を安全な既定とし、Journalとは独立して相関Logを維持する
- B: `enabled: false`を許可して`NullLogger`へ切り替える。Journalだけを残す構成を正式に認める

### Recommendation

Aを推奨する。

Phase 14はProduction FailureをOperation IDで追跡できることが目的である。Sink／RetentionはApplication責務でも、Framework Error Logを暗黙に消せる設定は診断不能を作る。出力先を`php://stderr`または管理対象Fileへ明示して運用側で保持方針を決める方が責任分界を保ちやすい。

[ANSWER]

A

[/ANSWER]

## Proposed Impact of A / A / A / A

- `ApplicationConfigurationLoader`はOptional `logging.php`を一度だけ読込み、Snapshotへ保持する。
- `ApplicationLoggingConfiguration`はConfig欠落時の既定、厳密型、Driver、Stream、Channel、Levelを検証する。
- HTTPとWorkerのApplication Composerは同じConfigurationからBackendを一度構成し、同じ`ExecutionScopedLogger`をContainerとRuntime Failure Reporterへ渡す。
- `ExecutionScopedLogger`による相関、Reserved Field、Sensitive FilterはBackend選択に関係なく必須とする。
- Config Validation FailureはApplication Bootstrap Failureとし、Credential、Path、内部DetailをHTTPやLogへ出さない。
- Runtime Open／Write FailureはPrimary Operation、Terminal Journal、HTTP Response、Worker Loopを変更しない。
- Built-in DriverはJSONLだけとし、Remote Handler、OTel、Metric、Rotation、Custom Driver Public APIを追加しない。

## Decision

[DECISION]

1. Installed Applicationは`config/logging.php`の`backend`でProduction Loggingを設定する。Phase 14のDriverは`jsonl`だけとする。
2. Canonical Keyは`driver`、`stream`、`channel`、`minimum_level`とし、Config欠落時は`jsonl`、`php://stderr`、`blackops`、`info`を使用する。
3. JSONL Streamは`php://stderr`、`php://stdout`、絶対Local File Pathだけを許可する。Relative Path、任意PHP Wrapper、Network URIを拒否する。
4. Configの型、Driver、Stream、Channel、LevelはApplication Runtime Composition時に厳密検証し、Invalid ConfigをFail-fastする。別Sinkへ暗黙Fallbackしない。
5. 起動後のBackend Open／Write FailureはBest-effortで吸収し、Primary Throwable、Terminal Journal、HTTP Response、Worker Loopを変更しない。
6. FrameworkはBackendを必ず`ExecutionScopedLogger`で包み、相関Field、Reserved Field分離、Sensitive Filterを維持する。
7. Phase 14ではLogging Disable Switch、Custom Backend Public Selection、Remote Handler、OpenTelemetry、Metric、Rotationを追加しない。

[/DECISION]

## Consequences

[CONSEQUENCES]

- HTTP RuntimeとWorker Runtimeは同じApplication Configuration SnapshotからBackend設定をProcess Composition時に一度だけ解決する。
- Config FileやProcess EnvironmentをRequest／Attempt／Log Recordごとに再読込しない。
- Application／Framework Error Logは既定でJSONLとしてstderrへ出力され、運用側がSink、Delivery、Retention、Alertを所有する。
- Local FileはApplicationが書込権限、Directory作成、Rotation、Disk Capacityを管理する。FrameworkはDirectoryを暗黙作成しない。
- Runtime Backend FailureはApplication Logを失わせる可能性があるが、Business OperationとCanonical Journalの成否を変更しない。
- Custom PSR-3 BackendとDriver RegistryはPhase 14の互換性Contractに含めない。

[/CONSEQUENCES]

## References

- [D097 Phase 14 Operation Diagnostics](097-phase-14-operation-diagnostics.md)
- [Logging and Traceability](../spec/10-logging-and-traceability.md)
- [Operation Diagnostics](../spec/65-operation-diagnostics.md)
- [Phase 14 Delivery Plan](../spec/66-phase-14-delivery-plan.md)
- [Public Application Bootstrap API](../spec/44-public-application-bootstrap-api.md)
