# P14-007 Consumer Experience and Closeout Report

Status: Accepted

## Summary

Install直後のQuickstartへ、認証／認可付きInline Failure Operation `diagnostics.failure.trigger`を追加した。`POST /failures`は非Secretの`reference`だけをApplication Logへ記録し、Sensitiveな`sensitiveNote`と固定Exception MessageをSafe Surfaceへ出さず、Operation ID付き500を返す。

Consumer E2EはこのIDをCanonical Journal、`operation:inspect` Human／JSON、Development Local Viewer、Application／Framework JSONLへ引き渡す。ViewerはPCNTLを持つnamed CLI Containerで明示起動し、同じNetwork NamespaceのLoopbackからToken、Session、Canonical Redirect、GET／HEAD、POST 405を実HTTP検証する。全Safe ArtifactにCredential、Sensitive Sentinel、Exception Message、Raw Actor IDがないことも否定検証した。

Quickstart／Skeleton、Framework Update、Guide／Reference／Security／Troubleshooting、Documentation Website、Specificationを同じContractへ同期した。Full PHP、Consumer、Websiteの全Gateが成功し、Phase 14 Delivery PlanとTODOは全件完了した。Documentation WebsiteのPublication／Deployは実行していない。

## Changed Files

### Quickstart and Documentation

- `examples/quickstart/app/Feature/Diagnostics/TriggerFailure/TriggerFailure.php`
- `examples/quickstart/app/Feature/Diagnostics/TriggerFailure/TriggerFailureValue.php`
- `examples/quickstart/app/Feature/Diagnostics/TriggerFailure/FailureTriggered.php`
- `examples/quickstart/config/logging.php`
- `examples/quickstart/README.md`
- `README.md`
- `docs/guide/configuration.md`
- `docs/guide/directory-structure.md`
- `docs/guide/mvp-sample.md`
- `docs/guide/mvp-status.md`
- `docs/guide/project-cli.md`
- `docs/guide/security.md`
- `docs/guide/troubleshooting.md`
- `docs/internal/installed-application-status.md`
- `docs/internal/mvp-e2e.md`
- `docs/website/content-map.mjs`
- `docs/website/scripts/check-site.mjs`

### Tests and Consumer

- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- `tests/Integration/MvpSampleEndToEndTest.php`
- `tests/Consumer/fixtures/viewer-request.php`
- `tests/Consumer/quickstart-e2e.sh`
- `tests/Consumer/framework-update-generators.sh`
- `tests/Consumer/frankenphp-worker-mode.sh`
- `tests/Consumer/skeleton-create-project.sh`
- `tests/Consumer/skeleton-publication.sh`
- `tests/Consumer/skeleton-publication-workflow.sh`

### Specification and Orchestration

- `develop/spec/49-feature-first-quickstart-application.md`
- `develop/spec/51-local-runtime-and-consumer-e2e.md`
- `develop/spec/65-operation-diagnostics.md`
- `develop/spec/66-phase-14-delivery-plan.md`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/reports/P14-007-consumer-experience-and-closeout.md`

Framework `src/`、Migration、Database Schema、Public PHP APIは変更していない。

## Decisions and Assumptions

- Failure OperationはTyped Self-handled Signatureを使い、到達しない正常Outcomeも具象`FailureTriggered`で表現した。
- `#[Sensitive]`のModeはPublic APIの`BlackOps\Core\Attribute\SensitiveMode`を使用した。
- QuickstartのApplication LogはD099のBuilt-in `jsonl`だけを使い、Application-ownedの絶対Path `var/log/application.jsonl`へ出す。Directory作成は既存Setup責務のままである。
- Viewer Native ServerはPCNTLが必要なため、FrankenPHP HTTP ContainerではなくQuickstartのCLI App Imageからnamed one-off Containerを明示起動した。Viewer HTTP Clientも同じContainerで実行し、Loopback限定を弱めていない。
- Skeleton Publication Workflow RegressionはRepositoryの未コミット差分を検証できるよう、一時cloneへ現在の`examples/quickstart`だけをfixture commitした。実RepositoryのHEAD、Index、Working Treeは変更しない。
- Stable `1.1.0`と`main` Previewを分離し、Phase 14 DiagnosticsはStableで未提供と明記した。

## Consumer Diagnostics Journey Evidence

1. `POST /failures`へ認証Header、非Secret Reference、固有Sensitive Sentinelを送信した。
2. HTTP 500のBodyが`status=error`、`code=internal_error`、UUIDv7 Operation IDだけであることを確認した。
3. PostgreSQL Canonical Journalが`operation.received -> attempt.started -> attempt.failed -> operation.failed`の4 Eventへ到達した。
4. Human InspectがType、Failed Terminal、Availability、Timeline、Attempt、Mask済みValue／Actorを表示した。
5. JSON InspectがSchema Version 1、同一ID、Inline Strategy、Failed State、Availability、4 Event、1 Attempt、`outcome: null`を返した。
6. Viewerを明示起動し、Tokenなし404、Token付き303、Session Cookie、Canonical Operation Path、GET 200、HEAD 200かつBodyなし、POST 405を確認した。
7. Viewer HTMLが同じID、Type、Failed State、Timeline、Attempt、Reference、Mask済みValue／Actorを表示した。
8. Application JSONLが同じOperation IDのApplication RecordとFramework Recordを各1件持ち、共通予約Fieldと`internal_error`分類を保持した。
9. Viewer Container／Process、Token／Cookieを含むTemporary Artifact、Compose Project／Volume／Imageが成功／失敗の両方でcleanupされることをScript境界で固定した。

## Human／JSON／Viewer／Log Safe Projection Matrix

| Surface | Correlation | Safe Evidence |
| --- | --- | --- |
| HTTP 500 | Operation ID | 固定`internal_error`だけ。Sentinel／Message／Credential／Actorなし |
| Observed Journal JSONL | Operation／Attempt／Correlation ID | `sensitiveNote`は`[masked]`、Actor IDはmask |
| Human Inspect | Operation／Attempt／Correlation ID | TimelineとFailure Typeを表示し、Raw Value／Messageなし |
| JSON Inspect | Operation／Attempt／Correlation ID | Schema 1のSafe AggregateだけをEncode |
| Local Viewer | Operation IDと同じAggregate | Token Session後のRead-only HTML。Raw／Messageなし |
| Application JSONL | Operation／Attempt／Correlation／Causation ID | Application／Framework共通Envelope、Actor `[masked]`、Messageなし |

全Artifact Guardは`local-example`、固有Sensitive Sentinel、`Intentional quickstart diagnostics failure.`、`quickstart-user`が存在しないことを確認した。Canonical PostgreSQL JournalはRestricted Dataとして別責務のままである。

## Framework Update／Skeleton Evidence

- Framework Update前後のQuickstart Application-owned Diagnostics Feature、`config/diagnostics.php`、`config/logging.php`、READMEをbyte-for-byte hashで維持した。
- Update後の`build:compile`と`operation:list`が`diagnostics.failure.trigger`を解決した。
- Generated Operation／HTTP Manifestが`diagnostics.failure.trigger`と`POST /failures`を対応付けた。
- Skeleton Package、通常Create-project、`--no-scripts` Create-projectがFailure Featureと二つのConfigを含み、Composer autoloadでOperation Classを解決した。
- Publication dry-runがDistribution Allowlist、Generated State不在、Source／Docker State不変を維持した。
- Workflow regressionの一時split commitがDiagnostics 3 File、二つのConfig、READMEを含み、annotated tag、idempotency、divergent／lightweight／legacy境界を維持した。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: Root／Quickstart valid。

docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: All files formatted。No issues found。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (1233 tests, 4523 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 2225 / Warnings 0 / Errors 0。

bash tests/Consumer/quickstart-setup.sh
Result: Quickstart setup tests passed。

bash tests/Consumer/quickstart-e2e.sh
Result: HTTP／Journal／Human／JSON／Viewer／Log／Deferred／Retentionを含めQuickstart consumer E2E passed。

bash tests/Consumer/framework-update-generators.sh
Result: Application-owned Source hash、Generator、Build／Operation Manifestを含めpassed。

bash tests/Consumer/frankenphp-worker-mode.sh
Result: Worker／Classic、DB reconnect、multi-request、相関Failure JSONLを含めpassed。

bash tests/Consumer/skeleton-create-project.sh
bash tests/Consumer/skeleton-publication.sh --dry-run
bash tests/Consumer/skeleton-publication-workflow.sh
Result: 通常／no-scripts、Distribution dry-run、temporary split workflow regression passed。

mise exec -- pnpm --dir docs/website install --frozen-lockfile
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
Result: lockfile固定install、38 tests passed、Astro 0 errors／warnings／hints、30 pages build、29 public pages navigation／search check passed。

Website tracked artifact、Internal path、Management Comment ID、Forbidden Diagnostics Option、git diff --check Guard
Result: すべて成功。
```

Website BuildはViteの500 kB超chunk warningを一件出したが、Build／Artifact／Search Checkは成功した。新規Error／WarningをAstro checkは報告していない。

## Orchestrator Review Correction

初回GuideはDocker ComposeでQuickstartを準備した直後にHostで`php blackops operation:viewer`を起動し、Browserで開けるように読めた。しかしQuickstartはPostgreSQLをHostへPublishせず、Applicationの`POSTGRES_HOST=postgres`はCompose Network内だけで解決する。ViewerもCLI ProcessのLoopback限定なので、Host Native CLIとHost Browserのどちらからもこの構成へ到達できない。

Review修正としてRoot README、Quickstart README、利用者向けQuickstartを次の実行可能な境界へ揃えた。

- Docker-only Quickstartは`docker compose run --rm app php blackops operation:inspect <id> [--json]`を利用する
- Consumer E2EのViewer検証はViewerとHTTP Clientを同じnamed CLI Container／Local Network Namespaceへ置く内部検証であり、Host Browser公開手順ではない
- BrowserでViewerを使う場合はApplication／PHP CLI／PostgreSQL／Browserが同じLocal Network Namespaceから到達可能なNative Runtimeを準備する
- Non-loopback Bindへ緩めず、Loopback限定を維持する

Website Guide TestとBuilt Site Checkへこの境界を追加した。修正後にWebsite 38 tests、Astro check、Build、Artifact／Navigation／Search Check、Forbidden Bind Guard、`git diff --check`を再実行して成功した。Compose、Framework `src/`、Publication設定は変更していない。

独立Quickstart E2E Reviewでは、P14-007のHuman／Viewer positive assertionが`grep -F`／`grep -Fx`の一致行を通常stdoutへ出し、単一行Viewer HTML全体とOperation ID／Safe ReferenceをCI Logへ繰り返し流していた。Assertionを`grep -Fq`／`grep -Fxq`へ変更し、否定grep、Operation ID／Cookie抽出、失敗時診断は維持した。`bash -n tests/Consumer/quickstart-e2e.sh`とQuickstart consumer E2Eを再実行し、通常成功LogへHuman行／Viewer HTMLを出さずにpassedした。

## Acceptance Criteria

- [x] Install直後のQuickstartに認証付きInline Failure Operationが含まれる
- [x] HTTP 500、Framework Log、Canonical Journal、Human／JSON／Viewerが同じOperation IDで相関する
- [x] Inline Failure Lifecycleが4つの規定Eventへ到達する
- [x] Human／JSON ExampleとConsumer実出力のShapeが一致する
- [x] Viewerの明示起動、Token、Session、Read-only、Timelineを実HTTPで検証した
- [x] Credential、Sensitive Value、Raw Actor ID、Exception MessageをSafe Surfaceへ出さない
- [x] Quickstart Local Logging ConfigがBuilt-in JSONLとApplication-owned File責務を示す
- [x] CLI、Configuration、Security、Troubleshooting、Quickstart Guideを同期した
- [x] Framework UpdateがApplication-owned Diagnostics Sourceを保持する
- [x] Skeleton通常／`--no-scripts`が新Feature／Configを再現する
- [x] Stable／mainとExperimental Compatibilityを正直に維持した
- [x] Full PHP、Consumer、Website、Artifact Guardが成功した
- [x] Delivery PlanとTODOを全件完了し、Report／STATEを同期した
- [x] Documentation Websiteを外部公開していない
- [x] WorkerはCommitしていない

## Phase 14 Closeout

Phase 14のDelivery PlanとTODOは全件完了した。HTTP ErrorのOperation IDからSafe Human／JSON DiagnosticsとDevelopment Local Viewerへ到達し、Application／Framework Log、Journal、Attempt、Correlationを一続きで追跡できる。Public PHP Query API、Remote Viewer、Status／Outcome HTTP API、OpenTelemetry／Metric／Collectorは追加せず、後続Phase境界を維持した。

Orchestrator Reviewと独立Critical Gateも完了した。P14-007をAcceptedとし、Phase 14を正式Closeする。

## Remaining Issues

P14-007を妨げるBlockerはない。

Documentation WebsiteはUser判断どおり未公開である。Vite chunk size warningは既存Presentation dependencyを含むBuild warningで、今回のContent同期を妨げない。Public Status／Outcome APIはPhase 16、OpenTelemetry等のRemote ObservabilityはPhase 18のDeferred Scopeである。

## Suggested Next Action

Phase 15 Operation Frontend Bridgeの設計対話へ進み、Wayfinder相当のRequest DescriptorとFull Typed Clientのどちらを最初のVertical Sliceにするか、Frontend Frameworkへ依存するかを決定する。

## Orchestrator Review

2026-07-19T06:13:43+09:00にAcceptedとした。OrchestratorはFailure Operation、Sensitive Projection、HTTP 500からHuman／JSON／Viewer／JSONLへ続くOperation ID相関、Skeleton／Framework Update、Stable／`main`表示、Docker-onlyとNative Viewerの到達境界を差分レビューした。

独立検証ではFull PHPUnit 1233件／4523 assertions、Composer Root／Quickstart、Mago format／lint／analyze、Deptrac Violations 0、Quickstart Consumer E2E、Skeleton Create-project、Website 38 tests／Astro check／30ページbuild／29ページArtifact Checkが成功した。Management ID、Forbidden Diagnostics Option、Non-loopback Bind、Generated Website Artifact、Internal Path、`src/`無変更、`git diff --check`のGuardも成功した。

初回Reviewで検出したDocker-only QuickstartからHost Browser Viewerへ到達できるように読める説明と、Consumer assertionがViewer HTML／Operation IDを通常CI stdoutへ出す問題は修正済みである。Documentation WebsiteのPublication／Deployは実行していない。Phase 14 Acceptance Criteriaを満たす。
