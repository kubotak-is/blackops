# Observability

このページでは、Repository `main`の試験的なOpenTelemetry API-only SurfaceをApplicationへ組み込み、Docker上のCollectorでTrace／Metricを確認し、Liveness／Readinessを明示RouteまたはCLIへ接続する方法を完了します。Stable `1.1.0`にはこのSurfaceは含まれません。

## Structured Record Version 1

Application／Framework／Journal／AuditのRecordは、末尾にLFを一つ持つ一行のUTF-8 JSON Objectです。共通Envelopeは次のFieldを使います。

| Field | Type | `application`／`framework` | `journal` | `audit` |
| --- | --- | --- | --- | --- |
| `schemaVersion` | integer | 必須、`1` | 必須、`1` | 必須、`1` |
| `kind` | string | 必須、`application`または`framework` | 必須、`journal` | 必須、`audit` |
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

Application／Frameworkの`operation`は`id`、`type`、`strategy`、`correlationId`、`causationId`、`actors`、`tenant`を持ち、Schedule Scopeだけ`{name, scheduledAt}`を追加します。Journalの`operation`はこれらに`schemaVersion`を加えます。Application／Frameworkの`attempt`はnon-nullのAttempt Scope時だけ、Journalの`attempt`は常時存在して`null`または`id`、`number`、`startedAt`になります。Audit RecordにはOperation、Attempt、Telemetryを出しません。Actor／Tenantの`id`は必ず`[masked]`です。Application／Framework／Journalの`telemetry`はValid Contextがあるときだけ次の3 Fieldを持ちます。

```json
{"schemaVersion":1,"kind":"application","occurredAt":"2026-08-09T09:00:00.000000Z","operation":{"id":"018f0000-0000-7000-8000-000000000001","type":"invoice.create","strategy":"inline","correlationId":"018f0000-0000-7000-8000-000000000002","causationId":null,"actors":{"origin":{"id":"[masked]","type":"user"},"authorization":null,"execution":{"id":"[masked]","type":"runtime"}},"tenant":{"id":"[masked]","type":"account"}},"telemetry":{"traceId":"0123456789abcdef0123456789abcdef","spanId":"0123456789abcdef","sampled":true},"level":"info","message":"operation completed","channel":"application","context":{"result":"completed"}}
```

KindごとのOptional Field境界は次のJSONLでも確認できます。`framework`はOperation Scopeなし、`journal`は`operation.schemaVersion`と`attempt: null`を持ち、`audit`はOperation／Attempt／Telemetryを持ちません。

```jsonl
{"schemaVersion":1,"kind":"framework","occurredAt":"2026-08-09T09:00:00.000000Z","level":"info","message":"worker started","channel":"framework","context":{}}
{"schemaVersion":1,"kind":"journal","recordId":"018f0000-0000-7000-8000-000000000003","event":"operation.completed","occurredAt":"2026-08-09T09:00:00.000000Z","sequence":3,"operation":{"id":"018f0000-0000-7000-8000-000000000001","type":"invoice.create","schemaVersion":1,"strategy":"inline","correlationId":"018f0000-0000-7000-8000-000000000002","causationId":null,"actors":null,"tenant":null},"attempt":null,"data":{}}
{"schemaVersion":1,"kind":"audit","occurredAt":"2026-08-09T09:00:00.000000Z","event":"storage.rotation.completed","data":{"status":"completed"}}
```

Stable `1.1.0`の既存Journal JSONLは`journal` Recordの範囲です。Repository `main`ではApplication／Framework／Journal／Auditを同じVersion 1 Envelopeへ正規化し、Monologの`datetime`、`level_name`、integer `level`、`extra`、Nested `context.schemaVersion`を公開Wireへ出しません。旧`operation.attemptId`やNested Monolog ShapeとのDual-write／Legacy Formatterはありません。既存Consumerは新しいTop-level FieldをParseし、`kind`ごとの追加Fieldだけを読み取ってください。

## 何をFrameworkが提供するか

FrameworkのProduction Dependencyは`open-telemetry/api`だけです。`ApplicationBuilder::withTracerProvider()`と`ApplicationBuilder::withMeterProvider()`へApplicationが構成したProviderを渡せます。SDK、OTLP Exporter、Resource、Endpoint、Credential、Collectorの起動はApplication／Infrastructureが所有し、Frameworkはそれらを自動登録しません。

Providerがない場合はNo-opとして動作します。Span、Metric、JSONL、Operation、Outcome、HTTP Response、ReadinessはTelemetry Providerの有無やExport失敗で変わりません。Frameworkは `/health` や `/ready` を自動公開しないため、RouteとCLIのどちらもApplicationが明示的に構成します。

## JSONL Correlation

Observed JSONLはVersion 1の共通Recordとして、Telemetryから`traceId`、`spanId`、`sampled`だけを`telemetry`へ投影します。`traceparent`、`tracestate`、Baggage、Exporter固有値はJSONLへ出しません。Actor IDとTenant IDは`[masked]`になり、Payload、Outcome、Credential、Key、Throwable Message／Stackは自動出力されません。[Journal](journal.md)のCanonical StoreとObserved Projectionの境界を先に確認してください。

## ProviderをApplicationで構成する

SDKとExporterはApplicationの直接Dependencyとして追加します。Framework PackageのDependencyへ移したり、CredentialをConfig、Manifest、Logへ保存したりしないでください。次の例ではOTLP HTTPのEndpointをApplicationのEnvironmentから解決します。下記の`--dev`指定はこのRepositoryのLocal Consumer検証用です。Deployed RuntimeでExportするApplicationは、同じPackageを自身のRuntime Dependency（`require`）として宣言し、FrameworkのProduction Dependencyへ移しません。

Repository `main`でこのLocal検証を再現するApplicationは、Project Rootで次のDevelopment Dependencyを固定します。Stable `1.1.0`のFramework Packageへ追加する手順ではありません。

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

FrameworkのInstrumentation Scopeは`blackops.framework`、Versionは`1.1.0`です。ApplicationのSpan／DB Instrumentationを重複生成しません。Frameworkが受け付ける結果は`completed`、`rejected`、`failed`、`retry_scheduled`、`dead_lettered`、`interrupted`の有限値です。

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

### Repository contributor: Consumer verification lane

これはFramework利用者向け手順とは別の、Repository contributor専用のConsumer検証です。Fresh checkoutのProject Rootで、Docker image cacheがなくても成立するよう先にFramework imageをBuildします。

```bash
docker compose build app
bash tests/Consumer/opentelemetry-observability.sh
```

ScriptはSource checkout rootをvolumeとして`/framework`へRead-only mountし、`blackops/framework:dev`を使ってConsumer fixtureを実行します。Collector Configは`tests/Consumer/fixtures/opentelemetry/collector-config.yaml`を明示Mountし、Emitter／Validatorは同じRandomized Docker Networkへ`--network`で参加します。期待結果は固定DigestのReady、HTTP／Deferred／Retry／Outbox Span相関、JSONL Correlation、10 Metric、Mask、Collector停止後のHealth／Readiness Isolation、Container／Network／一時Artifact cleanupです。Script自身の`trap cleanup EXIT`が失敗・中断も回収するため、Contributorは固定名Resourceを手動削除しません。

Collectorを起動しただけではTrace／Metricは生成されません。ApplicationのProviderを起動し、Span／Metricを作成してFlushするEmitterが必要です。Contributor laneの実行Directoryは必ずSource checkout rootです。

## 失敗時の切り分け

| 症状 | 確認 | 対応 |
| --- | --- | --- |
| CollectorがReadyにならない | Image Digest、Config Mount、`4318`のPort | Collector LogのSafe Errorだけを確認し、同じDigestで再起動 |
| Trace／Metricが届かない | ApplicationのEndpoint、Network Alias、`/v1/traces`／`/v1/metrics` | CollectorとEmitterを同じNetworkへ置き、Export時にFlushする |
| Invalid Contextが見える | `traceparent`のVersion、桁数、複数Header | Raw Headerを保存せず、Parentなしで処理 |
| Provider構成が失敗する | SDK／ExporterのApplication DependencyとProvider登録 | No-opへ縮退し、Primary Operation／Readinessを変更しない |
| Collector停止でReadinessがFailになる | Readiness CheckにExporter／Collectorを含めていないか | `OperationalHealthQueryFactory`のBounded Checkから外す |

Collectorの停止、Invalid Context、Provider／Exporter Failureは、Raw値やCredentialをLogへ出さないまま安全な有限Codeへ縮約します。Remote Collector、Dashboard、Alert、Production DeployはこのLocal手順の対象外です。[Deployment](deployment.md)、[Troubleshooting](troubleshooting.md)、[Security](security.md)も合わせて確認してください。

## Local Grafana LGTMでTrace／Metricを見る

開発・Demo・Testで画面から確認したい場合は、Application-owned Local
Grafana LGTM Consumerを使います。FrameworkのProduction DependencyやDefault
ComposeへGrafana／Tempo／Prometheusを追加する手順ではありません。`4318`はGrafana
の画面ではなくOTLP HTTPの送信先です。

CI／自動検証は次のNo-argument laneを使います。Probe完了後にContainerをcleanupし、
URLを表示しません。

```bash
bash tests/Consumer/opentelemetry-grafana-lgtm.sh
```

ContributorがFresh checkoutから実際に画面を閲覧する一続きのJourneyは、Project Rootで
Dockerを起動できることを確認し、Application imageをBuildしてからInteractive laneを
実行します。

```bash
cd /path/to/blackops
docker compose build app
bash tests/Consumer/opentelemetry-grafana-lgtm.sh --interactive
```

Probeが完了すると、ScriptはGrafana／OTLP loopback URL、Trace ID、保存Metric名を表示し、
Containerを停止せず待機します。表示されたGrafana URLを開き、GrafanaのExploreでTempo
datasourceを選択して、`Trace=<trace-id>`の値をTrace ID queryへ入力します。次にPrometheus
datasourceを選び、`metric=<stored-name>`の保存名を使って
`{__name__="<stored-name>"}`を実行します。確認後、同じTerminalで`Ctrl-C`を押すと
Container／Network／Temporary Artifactがcleanupされます。

Interactive laneはGrafanaのlocal development login `admin/admin`を使います。これは
この一時的なDevelopment／Demo／Test Consumerだけの既知値であり、Productionや共有
Grafanaへ持ち込まないでください。Trace IDは安全な相関値ですが、Credential、Trace
Payload、Backend ResponseはLog／Reportへ貼り付けません。

Scriptは固定DigestのLGTMをランダムなNetwork／Containerへ起動し、Grafana `3000`
とOTLP HTTP `4318`だけをランダムな`127.0.0.1` Portへ公開します。Tempo／Prometheus
のBackend Portは公開せず、Grafana datasource proxy経由でProvisioning、Emitterの
exact Trace ID、Tempoのnon-empty responseに含まれる
`blackops.operation.execute` span、Prometheusのnon-empty sample、保存されたInstrument名
`blackops.operation.duration`または
正規化名`blackops_operation_duration_seconds`（Histogramでは`_bucket`／`_sum`／`_count`）を
検証します。Prometheus OTLP ingestionへ送るこのLocal LGTM laneでは、Consumer scriptが
Emitterへ`BLACKOPS_OTEL_METRIC_TEMPORALITY=cumulative`を渡します。これはLGTMへの配送
条件だけで、既存Collector laneのDefault temporalityやFrameworkのMetric名／Schemaを変更
しません。Interactive laneの成功出力に
GrafanaのランダムPort（`http://127.0.0.1:<grafana-port>`）が表示されます。No-argument
laneはURLを表示せず終了します。OTLP Host laneを手動で
使う場合は`http://127.0.0.1:<otlp-port>`をApplicationの
`OTEL_EXPORTER_OTLP_ENDPOINT`へ設定します。これはIngestion endpointであり閲覧Page
ではありません。

Scriptは終端・失敗・割込みの全経路で、自分が作成したContainer／Network／一時
Artifactをcleanupします。Grafanaのlocal login値、Backend Response、Trace／Metric
PayloadはLog／Reportへ貼り付けません。LGTM停止をReadinessまたはPrimary Operation
Failureへ変換しないでください。

## Releaseと責務

Stable `1.1.0`はStructured JSONLと既存のOperation Correlationを含みますが、Provider Composition、Trace／Metric Adapter、Operational Health Query、Local Collector連携はRepository `main`の試験的Surfaceです。1.x Minor間の互換性とProduction Readinessは保証されません。[Releases](mvp-status.md)でRelease Laneを確認し、ApplicationがSDK／Exporter／Route／CLI／Deployment／Credentialを所有することをレビューしてから導入してください。
