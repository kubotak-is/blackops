# P14-005: Development Local Viewer

Status: Ready

## Goal

P14-003の内部`OperationDiagnosticsQuery`を、Installed ApplicationのCanonical BlackOps CLI `php blackops operation:viewer`から明示起動するDevelopment限定Local Viewerへ接続する。

Viewerは既定無効、Loopback限定、起動ごとのRandom Token必須、Read-only、Server-renderedとする。Terminalと同じSafe Aggregateだけを表示し、Canonical Store、Raw Value、Actor ID、Exception／Dead Letter Message、CredentialをHTTP Surfaceへ出さない。

## In Scope

- PrefixなしCanonical `operation:viewer` Command
- Framework Console KernelへのLazy登録とFramework Command名予約
- `config/diagnostics.php`のViewer Configuration読込、既定値、型／範囲検証
- Framework既定`enabled: false`と、Quickstart Localだけの明示`true`
- Disabled時のDatabase Composition／Socket Bind前Fail-fast
- IPv4 `127.0.0.1`とIPv6 `::1`だけを許可するBind検証
- 既定`127.0.0.1:8082`、Port 1..65535、Bind失敗／競合の安全なExit 1
- Framework内部へ隔離した、単一Process／ForegroundのNative Stream HTTP Server
- SIGINT／SIGTERMによるGraceful StopとSignal Handler復元
- 起動ごとの256 bit以上Random Bootstrap Tokenと独立Session Token
- Bootstrap URL一度だけのstdout出力、Constant-time Token比較、TokenなしURLへのRedirect
- HttpOnly／SameSite=Strict／Path=/ Session Cookie
- GET／HEAD限定、その他Method 405
- `GET /`のOperation ID入力画面と、完全一致ID一件のLookup画面
- Summary、Availability、Safe Actor Context、Timeline、Attempts、OutcomeのServer-rendered HTML
- Unavailable／Invalid IDの同一404、Storage／Decode／Integrity FailureのDetailなし500
- 全ResponseのSecurity Header、No-store、No-referrer、nosniff、DENY、CSP
- Request Line／Header Size、Read Timeout、Host Authorityを狭く制限するLocal Server境界
- Command、Configuration、Router、Token／Session、Renderer、Server、Kernelの単体／Integration Test

## Out of Scope

- Applicationの通常Route／PSR-15 PipelineへのViewer登録
- Public PHP Diagnostics API、Public HTTP Status／Outcome API
- Application User Authentication、Authorization、Tenant Access Policy
- Non-loopback Bind、Wildcard、Hostname、Unix Socket、TLS、Reverse Proxy対応
- Background Daemon、Remote Support UI、Multi-process／Concurrent HTTP Server
- Operation List、一覧、Prefix／全文検索、Latest Operation暗黙選択
- Retry／Replay／Cancel／Delete／Hold／Configuration変更等のWrite操作
- Raw JSON Endpoint、Raw Download、`--show-sensitive`、`--show-error-detail`
- Access Log、Token／Cookie／Operation IDのRequest Log
- Composer Production Dependencyの追加、HTTP Server Packageの追加
- Migration、DDL、Schema変更
- Skeleton、Guide、Documentation Website、Consumer E2Eの同期
- Documentation Website公開

## Relevant Specifications and Decisions

- `develop/spec/20-identifier-value-objects.md`
- `develop/spec/21-clock-and-time.md`
- `develop/spec/37-postgresql-table-layout.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/47-public-http-runtime-configuration.md`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/49-feature-first-quickstart-application.md`
- `develop/spec/65-operation-diagnostics.md`
- `develop/spec/66-phase-14-delivery-plan.md`
- `develop/decisions/092-project-cli-command-names.md`
- `develop/decisions/097-phase-14-operation-diagnostics.md`

## Files Allowed to Change

### Production

- 新規`src/Internal/Diagnostics/Viewer/*.php`
- 新規`src/Internal/Console/OperationViewer*.php`
- 新規`src/Internal/Application/ApplicationDiagnosticsViewer*.php`
- `src/Internal/Application/ApplicationConfigurationLoader.php`
- `src/Internal/Application/ApplicationConsoleKernel.php`
- `src/Internal/Application/ApplicationConsoleCommandFactory.php`
- `src/Internal/Application/ApplicationDiagnosticsQueryFactory.php`（ViewerとCLIの同一Query Composition再利用に必要な修正だけ）
- `src/Internal/Execution/PcntlSignalSupport.php`（既存Signal境界の安全な再利用に必要な修正だけ）
- `src/Internal/Diagnostics/*.php`（Rendererが必要とするSafe DTOの不変Contract修正またはReviewで判明したIntegrity修正だけ）
- `examples/quickstart/config/diagnostics.php`

### Tests

- 新規`tests/Internal/Diagnostics/Viewer/*.php`
- 新規`tests/Internal/Console/OperationViewer*.php`
- 新規`tests/Internal/Application/ApplicationDiagnosticsViewer*.php`
- `tests/Internal/Application/ApplicationConfigurationLoaderTest.php`
- `tests/Internal/Application/ApplicationDiagnosticsConsoleKernelTest.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- P14-005でInternal Diagnostics Contract修正が必要な場合の`tests/Internal/Diagnostics/*.php`

### Specification and Orchestration

- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/49-feature-first-quickstart-application.md`
- `develop/spec/65-operation-diagnostics.md`
- `develop/spec/66-phase-14-delivery-plan.md`
- `develop/TODO.md`
- `develop/orchestration/reports/P14-005-development-local-viewer.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は実装を広げず、ReportのBlockerとしてOrchestratorへ返す。

## Configuration Contract

Canonical Shapeは次とする。

```php
return [
    'viewer' => [
        'enabled' => false,
        'bind' => '127.0.0.1',
        'port' => 8082,
    ],
];
```

- `config/diagnostics.php`がない場合も上記Framework既定として扱う
- `enabled`はboolだけ、`bind`は文字列の`127.0.0.1`または`::1`だけ、`port`はintの1..65535だけを受け付ける
- String Boolean、Numeric String、Trim、Hostname解決、環境名推測を行わない
- Invalid ConfigurationはSocket BindとDatabase Compositionより前に安全なCommand Failureへする
- Quickstartの`config/diagnostics.php`だけがLocal Viewerを`true`にする。Framework既定を変更しない
- Config SnapshotはApplication起動時に一度解決済みの値を使用し、Server Loop内でConfig Fileや`$_ENV`を再読込しない

## Command and Process Contract

Canonical Invocationは次だけとする。

```text
php blackops operation:viewer
```

- Aliasと旧`blackops:operation:viewer`を登録／予約しない
- `list`／`help`／Application BootstrapだけではServer、Database、Tokenを生成しない
- Command実行とEnable Gateの両方が成立した場合だけServerを構成する
- Disabled、Invalid Config、Bind失敗／Port競合はstderrへ安全な一行Codeだけを出しExit 1にする
- 起動成功時、Bootstrap URLをstdoutへ一度だけ出す。Tokenをそれ以外の出力、Log、HTMLへ出さない
- Foregroundで処理し、SIGINT／SIGTERMでAccept Loopを止め、Socketを閉じ、既存Signal Handlerを復元してExit 0にする
- PCNTLが利用できない環境では、停止不能なServerを開始せず安全にExit 1にする
- Runtime Throwable Message、Bind Address以外のConnection Detail、Database Credential、SQLをstderrへ出さない

## HTTP Route Contract

Phase 14のRouteは次に限定する。

| Method | Path | Result |
| --- | --- | --- |
| GET／HEAD | `/?token=<bootstrap-token>` | Token一致時だけSession Cookieを設定し`/`へ303 Redirect |
| GET／HEAD | `/` | 有効Session時だけOperation ID完全一致入力Formを表示 |
| GET／HEAD | `/operations/<uuid-v7>` | 有効Session時だけ一件をQueryしDiagnosticsを表示 |
| Other | Any | 405、`Allow: GET, HEAD` |

- Tokenなし、不一致、期限切れSession、未知Pathは同じ404 Page／Header Shapeに畳む
- Bootstrap TokenはRoot Routeだけで受け付け、Query StringをHTML、Location、Referrerへ再出力しない
- Bootstrap TokenとSession Tokenは別の256 bit以上Random値とし、`random_bytes()`由来の固定長表現を使う
- TokenとCookieは`hash_equals()`で比較する
- Session Cookieは固定名、HttpOnly、SameSite=Strict、Path=/とし、Process終了で無効になるSession Cookieとする
- Request `Host`はConfigured Loopback Authorityと一致するものだけを受け付け、Mismatchを404へ畳む
- Operation IDは`OperationId::fromString()`へPath Segmentをそのまま渡す。Trim、短縮、Prefix補完をしない
- Invalid Operation IDとUnavailableは存在差を出さず同じ404にする
- HEADはGETと同じStatus／Headerを持ちBodyだけEmptyにする
- HTTP ParserはLocal Toolに必要なHTTP/1.0／1.1 Request LineとHeaderだけを扱い、Body、Chunked、Upgrade、Pipeliningを受け付けない
- Request Line／Header全体／Header数へ小さい上限とRead Timeoutを持たせ、Malformed／Oversized Requestは安全な400でConnectionを閉じる

## HTML and Security Contract

- HTMLは`OperationDiagnostics`だけを入力にし、Canonical Record、Raw Outcome、Connection、Throwableを保持しない
- Operation IDを除く可変文字列はContextに応じてHTML Escapeし、Attribute／URLへ生文字列を連結しない
- Actor IDは`[masked]`以外を表示しない。Credential、Raw Value、Exception／Dead Letter Message、Token、Cookieを表示しない
- JavaScript、External Asset、External Link、FormのWrite Methodを使用しない
- CSPは少なくとも`default-src 'none'`、`style-src 'unsafe-inline'`、`form-action 'self'`、`base-uri 'none'`、`frame-ancestors 'none'`を含む
- 全Responseへ`Cache-Control: no-store`、`Referrer-Policy: no-referrer`、`X-Content-Type-Options: nosniff`、`X-Frame-Options: DENY`、CSPを付ける
- HTML ResponseはUTF-8を明示する
- UnavailableはDetailなし404、Storage／Decode／Integrityは同じDetailなし500 Pageとする
- Formは完全一致Operation IDを`/operations/<id>`へNavigationする。Client-side Scriptを導入しないため、RootでGET Queryを受けて厳密検証後にCanonical PathへRedirectする方式も許容する

## Native Server Boundary

- Composer Dependencyを追加せず、`stream_socket_server()`等のNative Stream機能をFramework Internal Adapterへ隔離する
- Command、Router、Renderer、Token／Session、Query、Socket Accept Loopを分離し、SocketなしでSecurity Contractの大半を単体Test可能にする
- Serverは一接続ずつ処理するDevelopment Toolとし、Concurrent／Keep-aliveを保証しない。各Response後にConnectionを閉じる
- Socket Resource、Request Resource、Signal Handlerを`finally`で必ず解放／復元する
- Server AdapterはDiagnostics QueryのState再計算、Raw Store Access、HTML生成を行わない
- Server起動前にConfiguration、PCNTL、Random Sourceを検証し、失敗途中でBootstrap URLを出さない

## Acceptance Criteria

- [ ] `operation:viewer`がFramework Console KernelへLazy登録され、Application Command名／Alias競合を拒否する
- [ ] `blackops:operation:viewer` Alias／予約が存在しない
- [ ] Configなし／`enabled: false`ではDatabase／Token生成／Socket Bind前にExit 1になる
- [ ] Enabled Quickstart ConfigだけがViewer起動を許可し、Framework既定は無効のままである
- [ ] Invalid bool／bind／portを厳密拒否し、Wildcard／LAN／Hostname／Unix SocketへBindしない
- [ ] 既定`127.0.0.1:8082`、IPv6 `::1`、Port 1..65535を受け付ける
- [ ] Bootstrap TokenとSession Tokenが各256 bit以上で起動ごとに変わり、Constant-time比較される
- [ ] Bootstrap URLが起動成功後stdoutへ一度だけ出て、Tokenがstderr／HTML／Redirect／Logへ再出力されない
- [ ] 正しいBootstrap TokenだけがHttpOnly／SameSite=Strict Session Cookieを開始し、Tokenなし`/`へRedirectされる
- [ ] Tokenなし／不一致／期限切れSession／未知Pathが同じ404になる
- [ ] GET／HEADだけを許可し、その他Methodは405と`Allow` Headerを返す
- [ ] Host Mismatch、Malformed／Oversized Requestを安全に拒否する
- [ ] Rootが完全一致Operation ID入力だけを提供し、Operation ID一件だけをQueryする
- [ ] Found PageがSummary、Availability、Safe Actors、Timeline、Attempts、Outcomeを表示する
- [ ] Unavailable／Invalid IDが同じ404、Storage／Decode／Integrity Failureが同じDetailなし500になる
- [ ] HEADがGETと同じStatus／Header、Empty Bodyになる
- [ ] 全ResponseがSecurity Headerを持ち、HTML／HeaderへRaw Value、Actor ID、Error Message、Credential、Token、Cookieが漏れない
- [ ] List／Search／Raw／Write／Retry／Replay／Cancel／Delete／Hold Surfaceが存在しない
- [ ] SIGINT／SIGTERMでForeground Serverが終了し、SocketとSignal Handlerを復元する
- [ ] Migration、Public API、Production Dependency、Application Routeを追加しない
- [ ] Target／Full Test、Composer、Mago、Deptrac、Security Guardが成功する
- [ ] Report／STATEを更新し、WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations \
  tests/Internal/Console/OperationViewerCommandTest.php \
  tests/Internal/Application/ApplicationDiagnosticsViewerConfigurationTest.php \
  tests/Internal/Application/ApplicationDiagnosticsConsoleKernelTest.php \
  tests/Internal/Diagnostics/Viewer \
  tests/Internal/Diagnostics \
  tests/Integration/ApplicationConsoleKernelTest.php \
  tests/Architecture/QuickstartApplicationArchitectureTest.php
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
! rg -n '#\[PublicApi\]' src/Internal/Console/OperationViewer*.php src/Internal/Application/ApplicationDiagnosticsViewer*.php src/Internal/Diagnostics/Viewer
! rg -n 'blackops:operation:viewer|show-sensitive|show-error-detail|raw-(json|download)' src/Internal/Console/OperationViewer*.php src/Internal/Application/ApplicationConsoleKernel.php src/Internal/Diagnostics/Viewer
! rg -n '0\.0\.0\.0|::0|retry|replay|cancel|delete|hold' src/Internal/Diagnostics/Viewer --glob '*.php'
git diff --check
```

責務分割によりTest File名が異なる場合は、実在するP14-005対象Testをすべて指定して同等以上の範囲を実行する。否定Testや安全な説明文字列へGuardが反応する場合は、Production Surfaceに禁止機能がないことをReportへ具体的に記録する。

## Expected Report

`develop/orchestration/reports/P14-005-development-local-viewer.md`へSummary、Changed Files、Decisions and Assumptions、Configuration Matrix、Lazy Composition、Route Matrix、Token／Session Lifecycle、HTTP Parser Limits、Security Header Matrix、Sensitive Evidence、Signal／Resource Cleanup、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
