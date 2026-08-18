# Observability

このページでは、公開済みExperimental Stable `1.2.0`のOpenTelemetry API-only SurfaceをApplicationへ組み込み、Docker上のCollectorでTrace／Metricを確認し、Liveness／Readinessを明示RouteまたはCLIへ接続する方法を説明します。Production Readinessと1.x Minor間のBackward Compatibilityは保証しません。

## Structured Record Version 1

Application／Framework／Journal／Observed operational eventのRecordは、末尾にLFを一つ持つ一行のUTF-8 JSON Objectです。共通Envelopeは次のFieldを使います。Observed `kind=audit` は、Applicationが`LoggingRetentionPurgeAuditPort`を明示構成したRetention Purgeの`retention.purge.completed`だけを分類します。既定Application CLIはPostgreSQL Audit Storeだけを使い、ReplayとRotationは専用Audit Storeに留まるため、これらはDefault JSONLへは出ません。RotationのSafe Fingerprint／Scope HashもDefault Metricへ複製しません。Canonical Journalや汎用Business／Security Audit Trailを表しません。

| Field | Type | `application`／`framework` | `journal` | Observed operational event |
| --- | --- | --- | --- | --- |
| `schemaVersion` | integer | 必須、`1` | 必須、`1` | 必須、`1` |
| `kind` | string | 必須、`application`または`framework` | 必須、`journal` | 必須、`audit`（Observed分類） |
| `occurredAt` | UTC RFC 3339 microseconds | 必須、末尾`Z` | 必須、末尾`Z` | 必須、末尾`Z` |
| `operation` | object | Scopeがある場合 | 必須 | — |
| `attempt` | object／null | Attempt Scope時だけ | 必須（`null`可） | — |
| `telemetry` | object | Active Span時だけ | Active Span時だけ | — |
| `level` | lowercase PSR-3 string | 必須 | — | — |
| `message` | string | 必須 | — | — |
| `channel` | Safe channel string | 必須 | — | — |
| `context` | filtered object | 必須 | — | — |
| `recordId` | string | — | 必須 | — |
| `event` | string | — | 必須 | 必須 |
| `sequence` | integer | — | 必須 | — |
| `data` | object | — | 必須（空は`{}`） | 必須（Safe data） |

Application／Frameworkの`operation`は`id`、`type`、`strategy`、`correlationId`、`causationId`、`actors`、`tenant`を持ち、Schedule Scopeだけ`{name, scheduledAt}`を追加します。Journalの`operation`はこれらに`schemaVersion`を加えます。Application／Frameworkの`attempt`はnon-nullのAttempt Scope時だけ、Journalの`attempt`は常時存在して`null`または`id`、`number`、`startedAt`になります。Observed operational eventにはOperation、Attempt、Telemetryを出しません。Actor／Tenantの`id`は必ず`[masked]`です。Application／Framework／Journalの`telemetry`はValid Contextがあるときだけ次の3 Fieldを持ちます。

```json
{"schemaVersion":1,"kind":"application","occurredAt":"2026-08-09T09:00:00.000000Z","operation":{"id":"018f0000-0000-7000-8000-000000000001","type":"invoice.create","strategy":"inline","correlationId":"018f0000-0000-7000-8000-000000000002","causationId":null,"actors":{"origin":{"id":"[masked]","type":"user"},"authorization":null,"execution":{"id":"[masked]","type":"runtime"}},"tenant":{"id":"[masked]","type":"account"}},"telemetry":{"traceId":"0123456789abcdef0123456789abcdef","spanId":"0123456789abcdef","sampled":true},"level":"info","message":"operation completed","channel":"application","context":{"result":"completed"}}
```

KindごとのOptional Field境界は次のJSONLでも確認できます。`framework`はOperation Scopeなし、`journal`は`operation.schemaVersion`と`attempt: null`を持ちます。Observed `audit`は、明示構成したRetention Purgeの`retention.purge.completed`だけが出すSafe Recordで、Operation／Attempt／Telemetryを持ちません。Replay／Rotationの専用Audit Storeや既定Application CLIのPostgreSQL Audit StoreをDefault JSONLへ複製するものではありません。RotationのSafe Fingerprint／Scope HashもDefault Metricへ複製しません。これはCanonical Audit TrailのRecordではありません。

```jsonl
{"schemaVersion":1,"kind":"framework","occurredAt":"2026-08-09T09:00:00.000000Z","level":"info","message":"worker started","channel":"framework","context":{}}
{"schemaVersion":1,"kind":"journal","recordId":"018f0000-0000-7000-8000-000000000003","event":"operation.completed","occurredAt":"2026-08-09T09:00:00.000000Z","sequence":3,"operation":{"id":"018f0000-0000-7000-8000-000000000001","type":"invoice.create","schemaVersion":1,"strategy":"inline","correlationId":"018f0000-0000-7000-8000-000000000002","causationId":null,"actors":null,"tenant":null},"attempt":null,"data":{}}
{"schemaVersion":1,"kind":"audit","occurredAt":"2026-07-12T03:04:05.123456Z","event":"retention.purge.completed","data":{"audit_id":"019f32ab-2be0-7b38-a0a7-1ab2f9689b01","operation_id":"019f32ab-2be0-7b38-a0a7-1ab2f9689b02","target":"journal","affected_count":2,"policy":"production-retention-v1","purged_at":"2026-07-12T03:04:05.123456Z","purged_by":{"id":"[masked]","type":"retention"},"tenant":null}}
```

Stable `1.1.0`の既存Journal JSONLは`journal` Recordの範囲です。Stable `1.2.0`ではApplication／Framework／Journal／Observed operational eventを同じVersion 1 Envelopeへ正規化し、Monologの`datetime`、`level_name`、integer `level`、`extra`、Nested `context.schemaVersion`を公開Wireへ出しません。旧`operation.attemptId`やNested Monolog ShapeとのDual-write／Legacy Formatterはありません。既存Applicationは新しいTop-level FieldをParseし、`kind`ごとの追加Fieldだけを読み取ってください。

## 何をFrameworkが提供するか

FrameworkのProduction Dependencyは`open-telemetry/api`だけです。`ApplicationBuilder::withTracerProvider()`と`ApplicationBuilder::withMeterProvider()`へApplicationが構成したProviderを渡せます。SDK、OTLP Exporter、Resource、Endpoint、Credential、Collectorの起動はApplication／Infrastructureが所有し、Frameworkはそれらを自動登録しません。

Providerがない場合はNo-opとして動作します。Span、Metric、JSONL、Operation、Outcome、HTTP Response、ReadinessはTelemetry Providerの有無やExport失敗で変わりません。Frameworkは `/health` や `/ready` を自動公開しないため、RouteとCLIのどちらもApplicationが明示的に構成します。

## JSONL Correlation

Observed JSONLはVersion 1の共通Recordとして、Telemetryから`traceId`、`spanId`、`sampled`だけを`telemetry`へ投影します。`traceparent`、`tracestate`、Baggage、Exporter固有値はJSONLへ出しません。Actor IDとTenant IDは`[masked]`になり、Payload、Outcome、Credential、Key、Throwable Message／Stackは自動出力されません。[Journal](journal.md)のCanonical StoreとObserved Projectionの境界を先に確認してください。

## ProviderをApplicationで構成する

SDKとExporterはApplicationの直接Dependencyとして追加します。Framework PackageのDependencyへ移したり、CredentialをConfig、Manifest、Logへ保存したりしないでください。次の例ではOTLP HTTPのEndpointをApplicationのEnvironmentから解決します。下記の`--dev`指定はApplicationのLocal検証用です。Deployed RuntimeでExportするApplicationは、同じPackageを自身のRuntime Dependency（`require`）として宣言し、FrameworkのProduction Dependencyへ移しません。

公開済み`1.2.0`でこのLocal検証を再現するApplicationは、Project Rootで次のDevelopment Dependencyを固定します。FrameworkのProduction Dependencyへ追加する手順ではありません。

```bash
composer require --dev \
  open-telemetry/sdk:^1.15 \
  open-telemetry/exporter-otlp:^1.4 \
  php-http/guzzle7-adapter:^1.1
```

```php
use BlackOps\Application\Application;
use BlackOps\Application\Environment;
use OpenTelemetry\Contrib\Otlp\ContentTypes;
use OpenTelemetry\Contrib\Otlp\MetricExporter;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

// Applicationの外部Loaderで解決済みの全Snapshotを一度だけ作ります。
// この最小例ではProcess Environmentを使い、実運用では同じ形の
// Secret／.env Loaderの結果を渡します。値自体はLogへ出しません。
/** @var array<string, string> $environmentSnapshot */
$resolvedEnvironment = getenv();
$environmentSnapshot = is_array($resolvedEnvironment) ? $resolvedEnvironment : [];
$environment = new Environment($environmentSnapshot);
$otelEndpoint = rtrim(
    $environment->optionalString('OTEL_EXPORTER_OTLP_ENDPOINT')
        ?? 'http://127.0.0.1:4318',
    '/',
);
$transportFactory = new OtlpHttpTransportFactory();
$spanTransport = $transportFactory->create(
    $otelEndpoint . '/v1/traces',
    ContentTypes::PROTOBUF,
);
$tracerProvider = new TracerProvider(new SimpleSpanProcessor(
    new SpanExporter($spanTransport),
));
$metricTransport = $transportFactory->create(
    $otelEndpoint . '/v1/metrics',
    ContentTypes::PROTOBUF,
);
$metricExporter = new MetricExporter($metricTransport);
$meterProvider = MeterProvider::builder()
    ->addReader(new ExportingReader($metricExporter))
    ->build();

$application = Application::configure(dirname(__DIR__))
    ->withTracerProvider($tracerProvider)
    ->withMeterProvider($meterProvider)
    ->withEnvironment($environmentSnapshot)
    ->withConfiguration()
    ->create();
```

Metricは同じApplication-owned Providerから構成し、`MeterProvider`のReaderとExporterをApplicationのShutdown境界でFlush／Shutdownします。Providerを渡したProcessの終了処理は次のようにApplicationが所有します。

```php
$meterProvider->forceFlush();
$tracerProvider->shutdown();
$meterProvider->shutdown();
```

HTTP WorkerではRequestごと、Deferred WorkerではAttemptごとにScopeが終了するよう、長期ProcessでActive Contextを次のRequestへ持ち越さないでください。Providerの構成に失敗した場合はNo-opへ縮退し、一次処理を止めない設計にします。

## W3C ContextとJourney

HTTP入口はW3C `traceparent`（任意の`tracestate`）を検証し、Invalidまたは複数値のHeaderはRemote Parentなしとして扱います。Raw HeaderはLog／Metricへ出しません。`TelemetryContext`はVersion `00`の有効なTrace ID／Span IDとFlagsを保持し、`ExecutionContext::telemetry()`から読み取れます。

Process境界では同じTraceを次のように渡します。

| 境界 | Span | 相関の規則 |
| --- | --- | --- |
| HTTP | ApplicationのServer Span（必要な場合） | W3C Remote Parentを検証して開始 |
| Inline | `blackops.operation.execute`（Internal） | 現在のSpanをParentにする |
| Deferred受付 | `blackops.operation.accept`（Producer） | Producer Contextを暗号化Contextへ保存 |
| Worker／Retry | `blackops.operation.execute`（Consumer） | Persisted Parentを使い、Attemptごとに新しいSpan |
| Outbox | `blackops.outbox.relay`（Internal） | Outbox Producer ContextをParentにする |
| Schedule／Maintenance | `blackops.operation.schedule.evaluate`／`blackops.maintenance.run` | Runtimeごとに独立して開始・終了 |

Retryは同じTrace IDでも別Span IDです。待機中のDeferred／Retry／OutboxでSpanを開いたままにせず、Process境界の`finally`でScopeを閉じます。Observer Replayは元RecordのCorrelationを保ち、Replay Runtime Spanとは混ぜません。

## SpanとMetricの参照

FrameworkのInstrumentation Scopeは`blackops.framework`、Versionは公開済み`1.2.0`です。公開済みStable `1.1.0`のScope契約は変更しません。ApplicationのSpan／DB Instrumentationを重複生成しません。Frameworkが受け付ける結果は`completed`、`rejected`、`failed`、`retry_scheduled`、`dead_lettered`、`interrupted`の有限値です。

Metricは次の10個で、値は秒または固定単位を使います。Labelへ個別のOperation ID、Attempt ID、Trace／Span ID、Actor／Tenant ID、自由文を入れません。

| Name | Type | Unit | 固定属性の例 |
| --- | --- | --- | --- |
| `blackops.operation.duration` | Histogram | `s` | operation type／strategy／runtime／result |
| `blackops.operation.active` | UpDownCounter | `{operation}` | operation type／strategy／runtime |
| `blackops.worker.claims` | Counter | `{claim}` | result |
| `blackops.worker.heartbeat.failures` | Counter | `{failure}` | failure code |
| `blackops.outbox.relay.duration` | Histogram | `s` | result |
| `blackops.outbox.relay.records` | Counter | `{record}` | result |
| `blackops.scheduler.run.duration` | Histogram | `s` | scheduler kind／result |
| `blackops.scheduler.occurrences` | Counter | `{occurrence}` | scheduler kind／result |
| `blackops.observer.failures` | Counter | `{failure}` | observer／failure |
| `blackops.storage.protection.failures` | Counter | `{failure}` | purpose／failure |

## Healthを明示的に構成する

`OperationalHealthQuery`は`OperationalHealthKind::Liveness`または`OperationalHealthKind::Readiness`を受け、Version 1の`OperationalHealthReport`を返します。Readinessの標準Check Codeは次の6つです。

`compiled_artifact`、`runtime_configuration`、`database`、`migration_compatibility`、`storage_key_provider`、`runtime_services`

Applicationは`OperationalHealthQueryFactory::fromCallbacks()`で各Checkを構成できます。LivenessはQueryが応答できることだけを表し、Database、Storage Key Provider、OTLP Exporter、Collector接続は確認しません。ReadinessでもCollector停止、Sampling、Dashboard、Remote Backendは判定材料になりません。

```php
use BlackOps\Observability\OperationalHealthKind;
use BlackOps\Observability\OperationalHealthQueryFactory;

$callbacks = [];
foreach (OperationalHealthQueryFactory::requiredReadinessCheckCodes() as $code) {
    $callbacks[$code] = static fn (): bool => true; // ApplicationのBounded Checkへ置き換える
}
$query = OperationalHealthQueryFactory::fromCallbacks($callbacks);
$report = $query->check(OperationalHealthKind::Readiness);
```

HTTPでは`OperationalHealthRequestHandler`をApplicationのPSR-15 Routeへ登録し、`OperationalHealthJsonResponder`がPassを`200`、Failを`503`、非`GET`を`405`へ変換します。Bodyは`schemaVersion`、`kind`、`status`、UTCの`checkedAt`、SafeなCheck Codeだけです。CLIでは`OperationalHealthCliAdapter::run($kind, json: true)`の`output`と`exitCode`を外部Supervisorへ渡します。FrameworkはRouteやCLI Commandを自動登録しません。

ApplicationのAdapter配線は、利用するPSR-17 FactoryとRouterへ明示します。

```php
use BlackOps\Console\Observability\OperationalHealthCliAdapter;
use BlackOps\Http\Observability\OperationalHealthJsonResponder;
use BlackOps\Http\Observability\OperationalHealthRequestHandler;
use BlackOps\Observability\OperationalHealthKind;

$responder = new OperationalHealthJsonResponder($responseFactory, $streamFactory);
$readyHandler = new OperationalHealthRequestHandler(
    $query,
    OperationalHealthKind::Readiness,
    $responder,
);
$router->get('/ready', $readyHandler); // Application-owned route

$cli = new OperationalHealthCliAdapter($query);
$result = $cli->run(OperationalHealthKind::Readiness, json: true);
// $result['exitCode'] === 0 (pass) または 1 (fail)
// $result['output'] は schemaVersion／kind／status／checkedAt／checks のJSON
```

PassのHTTP Responseは`200`、`Content-Type: application/json`、`Cache-Control: no-store`、Failは`503`です。Methodが`GET`以外またはBodyが空でない場合は`405`と`Allow: GET`になります。Routeを登録しない場合は、FrameworkはどのPathにもHealth Responseを返しません。

## DockerでLocal Collectorを確認する

はい。CollectorはFrameworkのDefault Composeへ追加せず、Application／ConsumerだけのLocal Networkで起動できます。OTLP HTTP（`4318`）だけを使い、Collectorの`debug` ExporterへTrace／Metricを出します。Imageは固定Digestを使用します。Host Application laneとContainer Application laneはEndpointとNetworkが異なるため、混ぜないでください。

### 共通ConfigをHost Fileへ保存する

Project Rootで、次のYAMLを**`otel-collector-config.yaml`**として保存します。起動コマンドのMount元と同じ名前です。

```yaml
receivers:
  otlp:
    protocols:
      http:
        endpoint: 0.0.0.0:4318
exporters:
  debug:
    verbosity: detailed
service:
  pipelines:
    traces:
      receivers: [otlp]
      exporters: [debug]
    metrics:
      receivers: [otlp]
      exporters: [debug]
```

### Framework利用者: Host Application lane

Host上のPHP Applicationから送る場合は、CollectorだけをDockerで起動し、Host loopbackへだけ公開します。ApplicationのEnvironmentで`OTEL_EXPORTER_OTLP_ENDPOINT=http://127.0.0.1:4318`を選び、Provider例のTrace／Metric URLをこの値から組み立てます。`collector`というContainer hostnameはHostから解決できません。

次のSnippetは対象Resourceだけを一意にし、Config Error／中断でもContainerとNetworkを削除します。`docker network rm`と`docker rm`の対象はこのSnippetが作ったResourceだけです。

```bash
set -Eeuo pipefail
COLLECTOR_IMAGE='otel/opentelemetry-collector:0.158.0@sha256:5b97e6e3550ec6e48a71dba6f6304d349a293af8df4ee1f51da67be94fce2ecd'
RUN_ID="${USER:-local}-$(date +%s)-$$"
NETWORK="blackops-otel-host-${RUN_ID}"
COLLECTOR="blackops-otel-host-collector-${RUN_ID}"
cleanup() {
  docker rm -f "$COLLECTOR" >/dev/null 2>&1 || true
  docker network rm "$NETWORK" >/dev/null 2>&1 || true
}
trap cleanup EXIT INT TERM
cleanup
test -s "$PWD/otel-collector-config.yaml"
docker network create "$NETWORK" >/dev/null
docker run -d --name "$COLLECTOR" --network "$NETWORK" --network-alias collector \
  --volume "$PWD/otel-collector-config.yaml:/etc/otelcol/config.yaml:ro" \
  -p 127.0.0.1:4318:4318 "$COLLECTOR_IMAGE" \
  --config=/etc/otelcol/config.yaml >/dev/null
for _ in $(seq 1 30); do
  if docker logs "$COLLECTOR" 2>&1 | grep -q 'Everything is ready'; then break; fi
  sleep 1
done
docker logs "$COLLECTOR" 2>&1 | grep -m1 'Everything is ready'
docker inspect --format '{{.State.Status}}' "$COLLECTOR"
docker logs "$COLLECTOR" 2>&1 | tail -n 20
read -r -p 'Run the Host Application in another terminal, then press Enter to clean up: ' _
```

別のTerminalでHost Application／HTTP Workerを通常どおり起動し、Environmentへ`OTEL_EXPORTER_OTLP_ENDPOINT=http://127.0.0.1:4318`を渡します。Inline Operationを一回発火し、Deferred受付→Worker→Retry、Outbox Producer→RelayをApplicationの通常入口から実行して、ProviderのFlush／Shutdownを完了させます。Application固有のRoute／Worker CommandだけはそのApplicationの実装値へ置き換えてください。期待結果はCollector Logの`blackops.operation.duration`、Trace／Span相関、10 MetricのName／Type／Unit、Mask済みActor／Tenantです。Collector停止後もPrimary Operation、JSONL、Health、Readinessが同じ結果を保つことを確認します。

### Framework利用者: Container Application lane

Application／EmitterもDockerで動かす場合は、Host Portを公開しません。CollectorとApplication／Emitterへ同じ`--network "$NETWORK"`とCollectorのalias `collector`を付け、Environmentへ`OTEL_EXPORTER_OTLP_ENDPOINT=http://collector:4318`を渡します。Host laneの`127.0.0.1` Endpointや`-p`オプションをこのlaneへ持ち込まないでください。

```bash
set -Eeuo pipefail
COLLECTOR_IMAGE='otel/opentelemetry-collector:0.158.0@sha256:5b97e6e3550ec6e48a71dba6f6304d349a293af8df4ee1f51da67be94fce2ecd'
RUN_ID="${USER:-local}-container-$(date +%s)-$$"
NETWORK="blackops-otel-container-${RUN_ID}"
COLLECTOR="blackops-otel-container-collector-${RUN_ID}"
cleanup() {
  docker rm -f "$COLLECTOR" >/dev/null 2>&1 || true
  docker network rm "$NETWORK" >/dev/null 2>&1 || true
}
trap cleanup EXIT INT TERM
cleanup
test -s "$PWD/otel-collector-config.yaml"
docker network create "$NETWORK" >/dev/null
docker run -d --name "$COLLECTOR" --network "$NETWORK" --network-alias collector \
  --volume "$PWD/otel-collector-config.yaml:/etc/otelcol/config.yaml:ro" \
  "$COLLECTOR_IMAGE" --config=/etc/otelcol/config.yaml >/dev/null
for _ in $(seq 1 30); do
  if docker logs "$COLLECTOR" 2>&1 | grep -q 'Everything is ready'; then break; fi
  sleep 1
done
docker run --rm --network "$NETWORK" \
  --env OTEL_EXPORTER_OTLP_ENDPOINT=http://collector:4318 \
  <application-image> <application-entrypoint>
```

`<application-image>`と`<application-entrypoint>`はApplicationが所有する実値です。Emitterは同じNetworkでOperationを発火し、Flush／Shutdown後に終了します。期待結果と停止後のIsolationはHost laneと同じですが、CollectorのHost listenerはありません。再実行時は作成した`$COLLECTOR`を停止／削除してから`$NETWORK`を削除します。

### Application laneの結果を確認する

Host laneのReady確認、Container／NetworkのIsolation、Applicationの認可済みInspectは、それぞれのlaneを実行している同じTerminalで確認します。Host laneでは`read`の前にContainer Statusと最後のLogを表示するため、別Shellの`$COLLECTOR`へ依存しません。ApplicationがEmitterを構成した場合だけ、Application固有のTrace／Metric／JSONL結果をそのApplicationの手順で確認します。Collector停止後もPrimary Operation、Health、Readinessが変わらないことを確認し、固定Digest laneの`cleanup`に自分が作成したResourceだけを回収させます。

Collectorを起動しただけではTrace／Metricは生成されません。ApplicationのProviderを起動し、Span／Metricを作成してFlushするEmitterが必要です。実行DirectoryはApplicationのProject Rootです。

## 失敗時の切り分け

| 症状 | 確認 | 対応 |
| --- | --- | --- |
| CollectorがReadyにならない | Image Digest、Config Mount、`4318`のPort | Collector LogのSafe Errorだけを確認し、同じDigestで再起動 |
| Trace／Metricが届かない | ApplicationのEndpoint、Network Alias、`/v1/traces`／`/v1/metrics` | CollectorとEmitterを同じNetworkへ置き、Export時にFlushする |
| Invalid Contextが見える | `traceparent`のVersion、桁数、複数Header | Raw Headerを保存せず、Parentなしで処理 |
| Provider構成が失敗する | SDK／ExporterのApplication DependencyとProvider登録 | No-opへ縮退し、Primary Operation／Readinessを変更しない |
| Collector停止でReadinessがFailになる | Readiness CheckにExporter／Collectorを含めていないか | `OperationalHealthQueryFactory`のBounded Checkから外す |

Collectorの停止、Invalid Context、Provider／Exporter Failureは、Raw値やCredentialをLogへ出さないまま安全な有限Codeへ縮約します。Remote Collector、Dashboard、Alert、Production DeployはこのLocal手順の対象外です。[Deployment](deployment.md)、[Troubleshooting](troubleshooting.md)、[Security](security.md)も合わせて確認してください。

## Local Grafana LGTMのReadinessを確認する

開発・Demo・TestでGrafanaの入口を確認する場合は、Application-owned Local Grafana LGTMを固定Digestの自己完結laneで起動します。FrameworkのDefault ComposeへServiceを追加する手順ではありません。次の自動laneの検証範囲は、LGTM ContainerのReadiness、Grafana HTTP Health、loopback Port、ResourceのIsolation／Cleanupです。Application Emitterを実行しないため、Trace／Metricが保存されたことやExploreの結果はこのlaneの成功条件にしません。

```bash
set -Eeuo pipefail
LGTM_IMAGE='grafana/otel-lgtm:0.29.2@sha256:af7242c1a9608faf6d26e6f235392fd0c32b67258228f9a3cfc96e724974930c'
RUN_ID="${USER:-local}-$(date +%s)-$$"
NETWORK="blackops-grafana-lgtm-${RUN_ID}-network"
LGTM="blackops-grafana-lgtm-${RUN_ID}-backend"
GRAFANA_PASSWORD="${GRAFANA_PASSWORD-local-admin}"
cleanup() {
  docker rm -f "$LGTM" >/dev/null 2>&1 || true
  docker network rm "$NETWORK" >/dev/null 2>&1 || true
}
trap cleanup EXIT INT TERM
docker network create "$NETWORK" >/dev/null
docker run -d --name "$LGTM" --network "$NETWORK" --network-alias collector \
  --env GF_SECURITY_ADMIN_USER=admin \
  --env GF_SECURITY_ADMIN_PASSWORD="$GRAFANA_PASSWORD" \
  -p 127.0.0.1::3000 -p 127.0.0.1::4318 "$LGTM_IMAGE" >/dev/null
GRAFANA_PORT=''
for _ in $(seq 1 90); do
  status=$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$LGTM" 2>/dev/null || true)
  GRAFANA_PORT="$(docker port "$LGTM" 3000/tcp 2>/dev/null | sed -n 's/.*:\([0-9][0-9]*\)$/\1/p' | head -n 1)"
  if { test "$status" = healthy || test "$status" = running; } && test -n "$GRAFANA_PORT" && curl --fail --silent --show-error "http://127.0.0.1:${GRAFANA_PORT}/api/health" | grep -q '"database":"ok"'; then break; fi
  sleep 1
done
if ! curl --fail --silent --show-error "http://127.0.0.1:${GRAFANA_PORT}/api/health" | grep -q '"database":"ok"'; then
  FINAL_STATUS="$(docker inspect --format '{{.State.Status}}' "$LGTM" 2>/dev/null || printf 'unavailable')"
  FINAL_HEALTH="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}not-configured{{end}}' "$LGTM" 2>/dev/null || printf 'unavailable')"
  printf 'LGTM failure diagnostics: state=%s health=%s\n' "$FINAL_STATUS" "$FINAL_HEALTH" >&2
  printf 'LGTM startup diagnostic: Grafana health endpoint did not report database ok.\n' >&2
  exit 1
fi
printf 'LGTM readiness passed: Grafana http://127.0.0.1:%s\n' "$GRAFANA_PORT"
```

失敗時はHealthの非2xx、ContainerのState／Health、固定Digestの安全なstartup diagnosticだけを観測し、trapがこのlaneのContainer／Networkだけを削除します。Grafana 3000とOTLP HTTP 4318はランダムなloopback Portにだけ公開し、Backend PortやCredentialを外部へ公開しません。自動laneはTelemetryの存在を捏造せず、Readiness／failure／isolation／cleanupだけを境界にします。

画面と実際のTelemetryを確認する場合は、次の明示的なInteractive laneを同じProject Rootで実行します。これはReadiness確認後に停止せず待機するだけで、Emitter／Trace／Metricを自動生成しません。Applicationが所有するEmitterを表示されたDocker Networkへ参加させ、表示されたOTLP endpoint `http://collector:4318`とPort `4318`を使い、ProviderのFlush／ShutdownをApplicationの手順で実行してから、表示されたGrafana URLを開きます。CredentialはShellのGRAFANA_PASSWORD（未指定時local-admin）と一致し、Loginはadminと設定済みGRAFANA_PASSWORDです。Password自体はTerminal handoffやLogへ出力しません。

```bash
set -Eeuo pipefail
LGTM_IMAGE='grafana/otel-lgtm:0.29.2@sha256:af7242c1a9608faf6d26e6f235392fd0c32b67258228f9a3cfc96e724974930c'
RUN_ID="${USER:-local}-interactive-$(date +%s)-$$"
NETWORK="blackops-grafana-lgtm-${RUN_ID}-network"
LGTM="blackops-grafana-lgtm-${RUN_ID}-backend"
GRAFANA_PASSWORD="${GRAFANA_PASSWORD-local-admin}"
cleanup() { docker rm -f "$LGTM" >/dev/null 2>&1 || true; docker network rm "$NETWORK" >/dev/null 2>&1 || true; }
trap cleanup EXIT INT TERM
docker network create "$NETWORK" >/dev/null
docker run -d --name "$LGTM" --network "$NETWORK" --network-alias collector \
  --env GF_SECURITY_ADMIN_USER=admin --env GF_SECURITY_ADMIN_PASSWORD="$GRAFANA_PASSWORD" \
  -p 127.0.0.1::3000 -p 127.0.0.1::4318 "$LGTM_IMAGE" >/dev/null
for _ in $(seq 1 90); do
  GRAFANA_PORT="$(docker port "$LGTM" 3000/tcp 2>/dev/null | sed -n 's/.*:\([0-9][0-9]*\)$/\1/p' | head -n 1)"
  status=$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$LGTM" 2>/dev/null || true)
  { test "$status" = healthy || test "$status" = running; } && test -n "$GRAFANA_PORT" && curl --fail --silent "http://127.0.0.1:${GRAFANA_PORT}/api/health" | grep -q '"database":"ok"' && break
  sleep 1
done
FINAL_STATUS="$(docker inspect --format '{{.State.Status}}' "$LGTM" 2>/dev/null || printf 'unavailable')"
FINAL_HEALTH="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}not-configured{{end}}' "$LGTM" 2>/dev/null || printf 'unavailable')"
if ! { test "$FINAL_STATUS" = healthy || test "$FINAL_STATUS" = running; } || ! test -n "$GRAFANA_PORT" || ! curl --fail --silent --show-error "http://127.0.0.1:${GRAFANA_PORT}/api/health" | grep -q '"database":"ok"'; then
  printf 'LGTM failure diagnostics: state=%s health=%s\n' "$FINAL_STATUS" "$FINAL_HEALTH" >&2
  printf 'LGTM startup diagnostic: Grafana health endpoint did not report database ok.\n' >&2
  exit 1
fi
printf 'LGTM final health passed: status=%s Grafana=http://127.0.0.1:%s\n' "$FINAL_STATUS" "$GRAFANA_PORT"
printf 'Second Terminal Docker handoff: network=%s OTLP endpoint=http://collector:4318 OTLP port=4318\n' "$NETWORK"
printf 'Copy-paste Docker emitter: docker run --rm --network %s --env OTEL_EXPORTER_OTLP_ENDPOINT=http://collector:4318 <application-image> <application-entrypoint>\n' "$NETWORK"
printf 'Open http://127.0.0.1:%s and login as admin with configured GRAFANA_PASSWORD. Press Enter after the Application-owned emitter check: ' "$GRAFANA_PORT"
read -r _
```

Interactive laneの成功条件は、利用者がApplicationの実Emitterで送った相関値をExploreから確認できること、失敗条件はEmitterのEndpoint／Network／FlushまたはProvider設定を特定できることです。Response全体、Payload、Credential、Sensitive／High-cardinality LabelをLogへ貼り付けず、停止後のPrimary Operation／Health／Readinessが変わらないことを確認してください。

## Releaseと責務

Stable `1.2.0`はStructured JSONL、Provider Composition、Trace／Metric Adapter、Operational Health Query、Local Collector連携を含むExperimental Surfaceです。1.x Minor間の互換性とProduction Readinessは保証されません。[Releases](mvp-status.md)で制約を確認し、ApplicationがSDK／Exporter／Route／CLI／Deployment／Credentialを所有することをレビューしてから導入してください。
