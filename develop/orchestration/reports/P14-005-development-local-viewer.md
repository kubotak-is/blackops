# P14-005 Development Local Viewer Report

Status: Accepted

## Summary

Installed ApplicationのPrefixなしCanonical `operation:viewer`を実装した。Viewerは既定無効、明示Command起動、Loopback限定、Read-only、Server-renderedであり、P14-003とP14-004が使用する同じInternal `OperationDiagnosticsQuery`だけを参照する。

Native Stream HTTP Serverは`BlackOps\Internal\Diagnostics\Viewer`へ隔離し、Configuration、Token／Session、Request Parser、Router、Renderer、Response、Accept Loopを分離した。Application通常Route、PSR-15 Pipeline、Public API、Migration、Production Dependencyは追加していない。

## Changed Files

- `src/Internal/Application/ApplicationConfigurationLoader.php`
- `src/Internal/Application/ApplicationConsoleCommandFactory.php`
- `src/Internal/Application/ApplicationConsoleKernel.php`
- `src/Internal/Application/ApplicationDiagnosticsViewerConfiguration.php`
- `src/Internal/Console/OperationViewerCommand.php`
- `src/Internal/Diagnostics/Viewer/*.php`
- `examples/quickstart/config/diagnostics.php`
- `tests/Internal/Application/ApplicationConfigurationLoaderTest.php`
- `tests/Internal/Application/ApplicationDiagnosticsViewerConfigurationTest.php`
- `tests/Internal/Application/ApplicationDiagnosticsConsoleKernelTest.php`
- `tests/Internal/Console/OperationViewerCommandTest.php`
- `tests/Internal/Diagnostics/Viewer/*.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/49-feature-first-quickstart-application.md`
- `develop/spec/65-operation-diagnostics.md`
- `develop/spec/66-phase-14-delivery-plan.md`
- `develop/TODO.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Native Streamを新規Composer Dependencyより優先し、Framework Internal Adapterへ限定した。
- Framework既定はConfig欠落時もDisabledとし、Quickstart LocalのConfigだけをEnabledにした。
- Bootstrap TokenとSession Tokenを別々の32-byte `random_bytes()`から64桁hexとして生成する。
- Bootstrap QueryとCookieは重複値を拒否し、Constant-time比較する。
- Request HostはConfigured Authorityとの完全一致とし、Hostname解決やProxy Headerを扱わない。
- Request／Runtime Failureの内部MessageをResponse、stdout、stderrへ出さない。

## Configuration Matrix

| Input | Result |
| --- | --- |
| Configなし | `enabled=false`, `127.0.0.1:8082` |
| bool `enabled` | Accepted |
| string boolean | Rejected |
| `127.0.0.1` / `::1` | Accepted |
| Wildcard／LAN／Hostname／Unix path | Rejected |
| int port 1..65535 | Accepted |
| numeric string／範囲外 | Rejected |

Invalid／DisabledはToken、Query、Socket Bind前にExit 1となる。

## Lazy Composition

KernelはCommand名、Description、Factory Closureだけを登録する。`list`／`help`はConfig正規化、PCNTL、Random、Database、Socketを要求しない。Command実行時にConfigを検証し、Enable Gate成功後だけToken、Router、Serverを構成する。Database Queryは認証済みの完全一致Operation Pathへ到達した時だけ構成する。

## Route Matrix

| Method / Path | Result |
| --- | --- |
| GET／HEAD `/?token=<valid>` | 303、Session Cookie、Location `/` |
| GET／HEAD `/` + Session | ID Form |
| GET／HEAD `/?operationId=<uuid-v7>` + Session | Canonical Pathへ303 |
| GET／HEAD `/operations/<uuid-v7>` + Session | Found／404／500 |
| Other Method | 405 + `Allow: GET, HEAD` |
| Invalid Host／Session／Token／Path／ID | 同一404 |

HEADはGETと同じStatus／Header／Content-Lengthを持ち、wire bodyだけを空にする。

## Token / Session Lifecycle

- 起動ごとに独立したBootstrap／Session Tokenを各256 bit生成する。
- Socket Bind成功後のStarted CallbackだけがBootstrap URLをstdoutへ一度出す。
- Bootstrap TokenはRoot Queryでのみ受理し、Redirect、HTML、Logへ反射しない。
- Cookieは固定名、HttpOnly、SameSite=Strict、Path=/、Process Memory限定である。
- Bootstrap／Sessionとも`hash_equals()`で比較する。
- Token／Cookieの重複Parameterを拒否する。

## HTTP Parser Limits

| Boundary | Limit |
| --- | ---: |
| Request line | 2048 bytes |
| Header total | 8192 bytes |
| Header count | 32 |
| Read timeout | 2 seconds |

HTTP/1.0／1.1のOrigin-formだけを扱い、Body、Chunked、Upgrade、Header folding、Duplicate Header、Pipeliningを受け付けない。各Response後にConnectionを閉じる。

## Security Header Matrix

全Responseへ次を付与する。

- `Cache-Control: no-store`
- `Referrer-Policy: no-referrer`
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Content-Type: text/html; charset=UTF-8`
- CSP: `default-src 'none'`, inline style限定, `form-action 'self'`, `base-uri 'none'`, `frame-ancestors 'none'`

## Sensitive Evidence

- Renderer入力は`OperationDiagnostics`だけであり、Store、Connection、Throwableを保持しない。
- Actor IDはDTOが保証する`[masked]`だけを表示する。
- Timeline／Outcome DataをJSON encode後にHTML Escapeする。
- Identity／Actor／Outcome等の可変文字列をHTML Escapeし、Control Characterもescaped representationへ変換する。
- Token、Cookie、Credential、Exception／Dead Letter Message、SQLをHTMLへ出さない。
- Invalid IDとUnavailableを同じ404、全Diagnostics Failureを同じDetailなし500へ畳む。
- JavaScript、External Asset、External Link、Write Form、List／Search／Raw／Retry等のSurfaceを実装していない。

## Signal / Resource Cleanup

PCNTL利用不可時はBind前に安全に失敗する。ServerはSIGINT／SIGTERMの既存Handlerとasync settingを保存し、Foreground Loop停止後に復元する。Server Socketと各Request Connectionは`finally`で閉じる。Bind warning／Accept interruptionはPHP warningを外部stderrへ出さない狭いError Handlerで抑止し、必ず元Handlerを復元する。Signal停止、Handler復元、Port Conflict時のStarted Callback未実行をTestした。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: Root／Quickstart valid。

docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: 成功。No issues found。

docker compose run --rm app vendor/bin/phpunit --display-deprecations <P14-005 target tests>
Result: OK (89 tests, 659 assertions)。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (1194 tests, 4358 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 2211 / Warnings 0 / Errors 0。

Management Comment ID、Internal PublicApi、Forbidden Viewer Surface、Non-loopback／Write Surface、git diff --check Guard
Result: 成功。
```

## Acceptance Criteria

- [x] `operation:viewer`をLazy登録し、Application Command名／Alias競合を拒否した
- [x] 旧Prefix名をAlias／予約せずApplication Commandとして利用可能にした
- [x] Configなし／DisabledをDatabase／Token／Bind前にExit 1へした
- [x] Quickstart LocalだけをEnabledにした
- [x] Config型、Loopback、Portを厳密検証した
- [x] IPv4／IPv6 LoopbackとPort境界を受理した
- [x] 独立256 bit Token、起動ごとの変更、Constant-time比較を実装した
- [x] Bind成功後だけBootstrap URLを一度出力する
- [x] HttpOnly／SameSite Sessionと同一404認証境界を実装した
- [x] GET／HEAD、405、Host、Malformed／Oversized Request境界を実装した
- [x] 完全一致ID一件だけをQueryする
- [x] Found PageへSummary、Availability、Actors、Timeline、Attempts、Outcomeを表示した
- [x] Invalid／Unavailable 404、Diagnostics Failure 500をDetailなしで統一した
- [x] 全Response Security Header、HTML Escape、Sensitive非露出を検証した
- [x] SIGINT／SIGTERM、Socket、Connection、Signal Handler cleanupを実装した
- [x] Migration、Public API、Production Dependency、Application Routeを追加していない
- [x] Test／Composer／Mago／Deptrac／Guardを成功させた
- [x] WorkerはCommitしていない

## Remaining Issues

P14-005を妨げるBlockerはない。ViewerはDevelopment用単一Process Serverであり、TLS、Proxy、Concurrency、Production Authentication、Public Status APIは意図的にScope外である。

## Orchestrator Review Correction

Orchestrator Reviewで、Client disconnect／Broken Pipe時の`fwrite()` Warningが外部stderrへ漏れる可能性を指摘された。各write callを狭いError Handlerで囲み、`finally`で既存Handlerを必ず復元するよう修正した。Peer close済み`stream_socket_pair()`へのwriteでWarningが外部Handlerへ伝播せず、元Handlerが維持されることをTestした。修正後にTarget、Full PHPUnit、Mago、Deptrac、Guardを再実行し、すべて成功した。

## Orchestrator Review

Native Socket、Request Parser、Host／Method／Session境界、Token反射、HTML Context Escape、Security Header、Signal／Resource Cleanupを差分Reviewした。Broken Pipe修正後、Orchestratorが次を独立実行した。

```text
docker compose run --rm app vendor/bin/phpunit --display-deprecations <P14-005 orchestrator critical targets>
Result: OK (89 tests, 659 assertions)。Configuration、Command、Kernel、Token／Session、Router／Renderer、Parser、Native Server、Broken Pipe、Diagnostics、Integration、Quickstart Architectureを含む。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (1194 tests, 4358 assertions)。

docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app vendor/bin/mago format --check src tests examples
docker compose run --rm app vendor/bin/mago lint
docker compose run --rm app vendor/bin/mago analyze
docker compose run --rm app vendor/bin/deptrac
Result: Composer Root／Quickstart valid。Mago全成功。Deptrac Violations 0 / Skipped 0 / Uncovered 0 / Allowed 2211 / Warnings 0 / Errors 0。

Management Comment ID、Internal PublicApi、Forbidden Viewer Surface、Non-loopback／Write Surface、git diff --check Guard
Result: 成功。
```

Review指摘修正と独立品質Gateがすべて成功したため、P14-005をAcceptedとした。

## Suggested Next Action

P14-006 Production Correlation and Security Regressionへ進む。
