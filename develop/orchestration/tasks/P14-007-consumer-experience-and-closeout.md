# P14-007: Consumer Experience and Closeout

Status: Ready

## Goal

Phase 14で実装したOperation成立後Failure、相関Log、Internal Diagnostics Aggregate、`operation:inspect`、Development Local Viewerを、Install直後のQuickstartから一続きで体験できるConsumer Journeyへする。

Quickstart／Skeleton、Guide／Reference／Security／Troubleshooting、Framework Update、Create-project、Consumer E2E、Documentation Website Artifactを同じPublic Contractへ同期し、実行証拠に基づいてPhase 14をCloseする。Documentation Websiteは外部公開しない。

## In Scope

- Quickstartへ認証付きで意図的に失敗するInline Operationを追加する
- Operation ValueのSensitive Propertyと内部Exception Messageを安全なSurfaceへ出さないExampleにする
- HTTP 500のOperation IDを`operation:inspect` Human／JSONへ渡すJourneyを追加する
- Local ViewerのEnable、明示起動、Bootstrap Token、Session、Read-only、Timelineを実Consumerで検証する
- Quickstart Local用Application JSONL Logging Configを追加し、Application／Framework Log相関を実出力で確認する
- Quickstart READMEと利用者向けQuickstartへRequest／Response／CLI／JSON／Viewer／JSONLを入力と出力の対で記載する
- Project CLI、Configuration、Security、Troubleshooting、Directory Structure、Current Statusを実装へ同期する
- Framework UpdateとSkeleton通常／`--no-scripts` Create-projectへ新FeatureとConfigを同期する
- Quickstart／Integration／Architecture／Consumer／Website Testを更新する
- Full PHP／Consumer／Website Quality Gateを実行する
- TODO、Delivery Plan、Report、STATEを同期してPhase 14をCloseする

## Out of Scope

- Framework Public API／Internal Runtime／Migration／Database Schemaの追加または変更
- Public PHP Diagnostics Query API、Public Status／Outcome HTTP API
- ViewerのProduction公開、Application User Authentication、Authorization、Tenant分離
- Canonical Raw Download、Sensitive／Error Detail表示Option
- ViewerのList／Search／Write／Retry／Replay／Background Daemon／Remote Bind
- Custom Logging Backend、Remote Collector、OpenTelemetry、Metric、Dashboard
- New Project Installer、Remote Package Publication、Version Tag、Stable Release
- Cloudflare Pages Project、Preview／Production Deployment

## Relevant Specifications and Decisions

- `develop/spec/10-logging-and-traceability.md`
- `develop/spec/25-sensitive-projection.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/49-feature-first-quickstart-application.md`
- `develop/spec/51-local-runtime-and-consumer-e2e.md`
- `develop/spec/65-operation-diagnostics.md`
- `develop/spec/66-phase-14-delivery-plan.md`
- `develop/decisions/090-documentation-information-architecture.md`
- `develop/decisions/092-project-cli-command-names.md`
- `develop/decisions/094-experimental-versioning-and-release-surface.md`
- `develop/decisions/097-phase-14-operation-diagnostics.md`
- `develop/decisions/099-production-logging-configuration.md`

## Files Allowed to Change

### Quickstart and Skeleton Source

- `examples/quickstart/app/Feature/Diagnostics/TriggerFailure/**`
- `examples/quickstart/config/logging.php`
- `examples/quickstart/config/diagnostics.php`（既存Contractとの同期が必要な場合だけ）
- `examples/quickstart/README.md`
- `README.md`

### Tests and Consumer

- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Integration/ApplicationHttpRuntimeTest.php`
- `tests/Integration/MvpSampleEndToEndTest.php`
- `tests/Consumer/quickstart-e2e.sh`
- `tests/Consumer/quickstart-setup.sh`
- `tests/Consumer/framework-update-generators.sh`
- `tests/Consumer/frankenphp-worker-mode.sh`（新FeatureのWorker／Classic回帰に必要な場合だけ）
- `tests/Consumer/skeleton-create-project.sh`
- `tests/Consumer/skeleton-publication.sh`
- `tests/Consumer/skeleton-publication-workflow.sh`（配布Allowlist変更が必要な場合だけ）
- P14-007 Consumer内のViewer HTTP Clientを分離する場合の新規`tests/Consumer/fixtures/*`

### User and Internal Documentation

- `docs/guide/README.md`
- `docs/guide/mvp-sample.md`
- `docs/guide/project-cli.md`
- `docs/guide/configuration.md`
- `docs/guide/security.md`
- `docs/guide/troubleshooting.md`
- `docs/guide/directory-structure.md`
- `docs/guide/mvp-status.md`
- `docs/internal/mvp-e2e.md`
- `docs/internal/installed-application-status.md`

### Documentation Website

- `docs/website/content-map.mjs`
- `docs/website/site-navigation.mjs`
- `docs/website/scripts/check-site.mjs`
- `docs/website/tests/content-pipeline.test.mjs`
- `docs/website/tests/guide-code.test.mjs`
- `docs/website/tests/reader-experience.test.mjs`
- `docs/website/tests/site-navigation.test.mjs`
- `docs/website/src/styles/custom.css`（新規Presentationを必要とする場合だけ）

### Specification and Orchestration

- `develop/spec/49-feature-first-quickstart-application.md`
- `develop/spec/51-local-runtime-and-consumer-e2e.md`
- `develop/spec/65-operation-diagnostics.md`
- `develop/spec/66-phase-14-delivery-plan.md`
- `develop/TODO.md`
- `develop/orchestration/reports/P14-007-consumer-experience-and-closeout.md`
- `develop/STATE.md`

Framework `src/`の変更が必要な場合は実装を広げず、仕様との不一致またはBlockerとしてReportへ返す。生成Website Content、`dist/`、`node_modules/`をCommitしない。

## Quickstart Failure Operation Contract

Feature-first Pathは`app/Feature/Diagnostics/TriggerFailure/`とし、次のPublic Exampleを提供する。

- Operation Type: `diagnostics.failure.trigger`
- HTTP Route: `POST /failures`
- Execution Strategy: Inline
- Authorization: Quickstartの既存Application Policyを使用する
- Value: 非Secretの`reference`と、`#[Sensitive(SensitiveMode::Mask)]`を付けた`string $sensitiveNote`
- Outcome: Typed Self-handled Signatureを満たす具象Outcome。正常ReturnはExample上到達しない
- Handler: `Psr\Log\LoggerInterface`をConstructor Injectionし、非Secret ReferenceだけをApplication Logへ出した後、固定の内部Exception Messageを持つ`RuntimeException`を投げる

HTTP Responseは次のShapeだけを返す。IDは実行ごとのUUIDv7である。

```json
{"status":"error","code":"internal_error","operationId":"019..."}
```

Canonical JournalはRestricted DataとしてRaw ValueとException Messageを保持し得る。一方、HTTP、Application／Framework JSONL Log、Observed Journal JSONL、`operation:inspect` Human／JSON、Viewer HTMLへはSensitive Note、Exception Message、Credential、Raw Actor IDを出さない。Failure Type、Safe classification、Mask済みValue、Operation／Attempt／Correlation IDは表示できる。

Quickstartの`config/logging.php`はBuilt-in `jsonl`だけを使用し、Local Consumerが実出力を検証できるApplication-owned絶対Path `var/log/application.jsonl`、Channel `blackops`、Minimum Level `info`を設定する。Directory作成は既存Setup責務のままとし、Frameworkへ追加しない。

## Diagnostics Consumer Journey

`tests/Consumer/quickstart-e2e.sh`は既存Journeyへ次を追加する。

1. 認証付き`POST /failures`へReferenceと固有Sensitive Sentinelを送る
2. HTTP 500とSafe JSON、UUIDv7 Operation IDを取得する
3. Canonical Journalが`operation.received -> attempt.started -> attempt.failed -> operation.failed`へ到達することを確認する
4. `php blackops operation:inspect <id>`のstdout／Exit 0でType、Failed Terminal、Timeline、Attempt、Outcome unavailable、Mask済みActor／Valueを確認する
5. `php blackops operation:inspect <id> --json`をDecodeし、Schema Version、Status、同一ID、State、Timeline、Attempt、Availabilityを確認する
6. Quickstartで既定Enabledの`php blackops operation:viewer`を明示起動し、起動後に一度だけ出るBootstrap URLからTokenを取得する
7. Tokenなし404、Token付きBootstrap Redirect／Session Cookie、Canonical Operation Path、Read-only GET／HEAD、POST 405を実HTTPで確認する
8. Viewer HTMLに同じID、Type、Failed State、Timeline、Attempt、Mask済みActor／Valueがあり、Sensitive SentinelとException Messageがないことを確認する
9. Application JSONLで同じOperation IDのApplication／Framework Record、共通予約Field、Mask済みActorを確認する
10. HTTP、Application Log、Observed Journal、Human、JSON、Viewerの全ArtifactにCredential、Sensitive Sentinel、Exception Message、Raw Actor IDがないことを確認する

Viewer Token／Cookieを通常Consoleへ表示せずTemporary Artifact内だけで扱い、終了時にViewer Process／Container／Temporary Fileを必ずcleanupする。ViewerはLoopbackのまま検証し、非Loopback Bindへ変更しない。

## Documentation Contract

- Quickstartは「失敗を起こす → HTTP Operation IDを得る → Human／JSONで調べる → Local Viewerで見る」の順で、入力と実出力を対にする
- Human／JSON Exampleは実Formatter／EncoderのField名、順序、State、Availability、Timelineと一致させる。可変ID／時刻はExample値であることを明記する
- `project-cli.md`へ`operation:inspect <operation-id> [--json]`と`operation:viewer`、stdout／stderr／Exit 0／2／3／4、既定無効／Quickstart Local有効を記載する
- `configuration.md`へ`diagnostics.php`と`logging.php`、Canonical Key、既定、限定Stream、Fail-fast／Best-effort責任境界を追加する
- `security.md`へCanonical Restricted DataとSafe Diagnostics Surface、Local Viewer Token／Loopback／Read-only／No-storeの責任境界を追加する
- `troubleshooting.md`へOperation ID付き500の確認順、IDなし500との違い、Inspect Error Code、Viewer Disabled／Bind／Sessionの典型Failureを追加する
- Stable `1.1.0`とRepository `main` Preview、Experimental Compatibility、Website未公開を正直に維持する
- 新しいTop-level IA SectionまたはPublic Pageを追加せず、Diátaxis上QuickstartはTutorial、CLI／ConfigurationはReference、Security／Troubleshootingは各責務へ置く
- Website Contentは`docs/guide/`から生成し、Stable／main Banner、Navigation、Search、Accessibility、Artifact Boundaryを維持する

## Framework Update and Skeleton Contract

- Framework Update SmokeはDiagnostics Feature、`config/diagnostics.php`、`config/logging.php`、READMEをApplication-owned Sourceとしてbyte-for-byte維持する
- Skeleton通常／`--no-scripts`の両方へFailure Operationと二つのConfigを含める
- Create-project後の`operation:list`／`build:compile`で`diagnostics.failure.trigger`と`POST /failures`を解決できる
- Distribution Allowlist、Generated State不在、Source Tree不変、Remote Publicationなしの既存保証を維持する

## Phase Closeout Acceptance Criteria

- [ ] Install直後のQuickstartに認証付きInline Failure Operationが含まれる
- [ ] HTTP 500、Framework Log、Canonical Journal、Human／JSON／Viewerが同じOperation IDで相関する
- [ ] Inline Failure LifecycleがReceived、Attempt Started、Attempt Failed、Operation Failedへ到達する
- [ ] Human／JSON ExampleとConsumer実出力のShapeが一致する
- [ ] Viewerを明示起動し、Token／Session／Read-only／Timelineを実Consumer HTTPで検証する
- [ ] Credential、Sensitive Value、Raw Actor ID、Exception MessageがHTTP／Log／Observed JSONL／CLI／Viewerへ出ない
- [ ] Quickstart Local Logging ConfigがBuilt-in JSONLとApplication-owned File責務を示す
- [ ] CLI／Configuration／Security／Troubleshooting／Quickstart Guideを実装へ同期する
- [ ] Framework Update SmokeがApplication-owned Diagnostics Sourceを保持する
- [ ] Skeleton通常／`--no-scripts`が新Feature／Configを再現する
- [ ] Stable／main表示とExperimental Compatibilityの正直さを維持する
- [ ] Full PHP、Relevant Consumer、Website Test／Check／Build／Artifact Guardが成功する
- [ ] Phase 14 Delivery PlanとTODOを全件完了し、Report／STATEをClosedへ同期する
- [ ] Documentation Websiteを外部公開しない
- [ ] WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac
bash tests/Consumer/quickstart-setup.sh
bash tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/framework-update-generators.sh
bash tests/Consumer/frankenphp-worker-mode.sh
bash tests/Consumer/skeleton-create-project.sh
bash tests/Consumer/skeleton-publication.sh --dry-run
bash tests/Consumer/skeleton-publication-workflow.sh
mise exec -- pnpm --dir docs/website install --frozen-lockfile
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
! git ls-files docs/website/src/content/docs docs/website/.generated docs/website/dist | grep -q .
! rg -n 'docs/internal|develop/' docs/website/dist docs/website/.generated
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples --glob '*.php'
! rg -n 'show-sensitive|show-error-detail|0\.0\.0\.0' examples/quickstart docs/guide/project-cli.md docs/guide/mvp-sample.md
git diff --check
```

Consumer ScriptはDocker StateとPortを共有するため並列実行しない。Website InstallでNetwork／Registryが必要になり実行できない場合は、未実行理由とLocal Lockfile／Cacheで実行できたGateをReportへ分離する。Publication／Deploy Commandは実行しない。

## Expected Report

`develop/orchestration/reports/P14-007-consumer-experience-and-closeout.md`へ少なくとも次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Consumer Diagnostics Journey Evidence
- Human／JSON／Viewer／Log Safe Projection Matrix
- Framework Update／Skeleton Evidence
- Commands and Results
- Acceptance Criteria
- Phase 14 Closeout
- Remaining Issues
- Suggested Next Action
