# P7-006: Local Runtime and Consumer End-to-End

Status: Ready

## Goal

Quickstart所有のDocker／FrankenPHP／PostgreSQL RuntimeとPublic JSONL Journal Compositionを実装し、Framework RepositoryのDev Autoloadから分離した一時ConsumerでBuild、Migration、HTTP、Worker Retry、Outcome、Sensitive Projection、RetentionをEnd-to-End検証する。

## In Scope

- `config/journal.php` のPublic ValidationとHTTP Runtime Composition
- JSONL Observer、Sensitive Projection、Observation PipelineのFramework内部構成
- Best Effort／Required Delivery Policy
- QuickstartのDocker CLI Image、FrankenPHP HTTP Image、Caddyfile、Compose
- PostgreSQL 18 Health CheckとNamed Volume
- Default Compose ServiceをPostgreSQL／HTTPだけに制限
- Explicit CLI／Worker／Maintenance ProfileとCommand例
- Explicit Composer Install、Artifact Build、Migration順序
- Temp Quickstart CopyへのComposer Path Repository注入
- `symlink: false` でConsumer `vendor/` へFramework PackageをCopy Install
- Inline Welcome、Deferred Report、Retry、Outcome、Sensitive JSONL、Retention Plan／Dry RunのConsumer E2E
- Source Tree非変更と失敗時Cleanupの自動検証
- Integration／Architecture／Guide／Internals更新

## Out of Scope

- Worker側Observed Journal Lifecycle／Flush
- Default Worker／Scheduler／Migration／Purge自動起動
- Retention Purge `--confirm`
- Remote Packagist／Skeleton Split Repository公開
- Framework RootのDocker／Compose再構成
- Deferred Status／Outcome Public HTTP Endpoint
- Production Image Release／Registry Push

## Relevant Specifications and Decisions

- `develop/decisions/064-installed-application-layout-and-bootstrap.md`
- `develop/decisions/069-skeleton-http-entrypoint-adapters.md`
- `develop/decisions/070-quickstart-journal-observer.md`
- `develop/spec/25-sensitive-projection.md`
- `develop/spec/39-retention-runtime.md`
- `develop/spec/42-installed-application-boundary.md`
- `develop/spec/43-installed-application-layout-and-bootstrap.md`
- `develop/spec/45-phase-7-delivery-plan.md`
- `develop/spec/47-public-http-runtime-configuration.md`
- `develop/spec/49-feature-first-quickstart-application.md`
- `develop/spec/51-local-runtime-and-consumer-e2e.md`

## Files Allowed to Change

- `src/Internal/Application/**`
- `src/Internal/Runtime/ProductionRuntimeDependencies.php`
- `tests/Internal/Application/**`
- `tests/Internal/Runtime/ProductionRuntimeComposerTest.php`
- `tests/Integration/ApplicationHttpRuntimeTest.php`
- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- `tests/Consumer/**`
- `examples/quickstart/.env.example`
- `examples/quickstart/.gitignore`
- `examples/quickstart/Caddyfile`
- `examples/quickstart/Dockerfile`
- `examples/quickstart/Dockerfile.frankenphp`
- `examples/quickstart/compose.yaml`
- `examples/quickstart/config/journal.php`
- `examples/quickstart/README.md`
- `docs/guide/application-bootstrap.md`
- `docs/guide/runtime-bootstrap.md`
- `docs/guide/mvp-sample.md`
- `docs/internals/bootstrap.md`
- `docs/internals/jsonl-journal-observer.md`
- `docs/internals/mvp-e2e.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P7-006-local-runtime-and-consumer-e2e.md`
- `develop/orchestration/reports/P7-006-local-runtime-and-consumer-e2e.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は実装を広げず、ReportのBlockerとして返す。

## Public Journal Configuration Contract

```php
return [
    'jsonl' => [
        'enabled' => true,
        'path' => dirname(__DIR__) . '/var/log/journal.jsonl',
        'delivery' => 'best_effort',
    ],
];
```

- `jsonl` は配列、`enabled` はBooleanで省略時false
- enabled=falseはPath／Deliveryを要求せずObserverを構成しない
- enabled=trueのPathは空でない絶対Path
- Parent Directoryは既存Directoryかつ書込可能
- Deliveryは `best_effort` または `required`
- Quickstart Defaultは `best_effort`
- JSONLはAppend Binary Modeで開き、既存内容を切り捨てない
- FrameworkはParent Directoryを暗黙作成しない
- Open ErrorはConfig Keyと責務だけを示す安全なPublic Bootstrap Errorへ変換する
- Inline Journal RecordをSensitive Projection後だけJSONL Observerへ渡す
- Best Effort Observer FailureはCanonical Operationを失敗させない

## Quickstart Runtime Contract

- CLI ImageはPHP 8.5、Composer、PCNTL、PDO PostgreSQL、ZIPを持つ
- HTTP ImageはOfficial FrankenPHP 1／PHP 8.5 Debian Imageを基礎にする
- HTTPはCaddyfileから `public/index.php` をClassic Modeで実行する
- Image StartupでComposer Install、Build、Migrationを行わない
- Application Directoryを `/app` へMountする
- PostgreSQL 18と `pg_isready` Health Checkを使う
- Credentialは`.env`／Process Environmentから渡す
- Default Service Setは `postgres` と `http` のみ
- `app` は明示CLI run target、`worker` は `worker` Profile、`scheduler` は `maintenance` Profile
- HTTP Port Local Defaultは8080でEnvironmentから上書き可能
- Worker、Scheduler、Migration、Retention PurgeをDefault起動しない

## Consumer Isolation Contract

- `examples/quickstart/` を一時DirectoryへCopyする
- Checked-in QuickstartにはPath Repository、Lock、Vendorを追加しない
- Temp Consumerだけに `/framework` のPath Repositoryを注入する
- `options.symlink` はfalse、`versions.blackops/framework` は `1.0.0`
- Composer Install時だけFramework RootをRead-only `/framework` へMountする
- RuntimeはTemp Consumerの `vendor/autoload.php` のみを使う
- Root Dev Autoload、Root `vendor/autoload.php`、Root Test Namespaceへ依存しない
- Cleanup TrapでCompose Project、Container、Volume、Image、Temp Directoryを削除する

## End-to-End Scenario

1. Temp ConsumerへComposer Installする
2. Operation ListがWelcome／ReportをDiscoveryする
3. Build Compileが3 Artifactを生成する
4. Build時点でFramework Schemaが存在しないことを確認する
5. Database StatusがRead-onlyであることを確認する
6. Explicit Migrate後だけSchemaが存在することを確認する
7. Default PostgreSQL／HTTPを起動する
8. `GET /welcome` の200／Expected JSONを確認する
9. Test用Sensitive Header Raw値がJSONLに存在せずMask値が存在することを確認する
10. `POST /reports` の202／Operation IDを取得する
11. `--iterations` 付きWorker 1回目でRetry Scheduledを確認する
12. Due Time後のWorker再実行でCompletedを確認する
13. PostgreSQL Operation StateがCompletedであることを確認する
14. Outcome RowとEncoded Outcomeを確認する
15. Retention PlanとPurge Dry Runを実行する
16. Scheduler／Purge Confirmを実行していないことを保証する

## Constraints

- Production CodeとTestのComment／DocBlockへDecision、Spec、Task、TODOの管理番号を書かない
- Quickstart／Consumer Codeから `BlackOps\Internal` をImportしない
- Runtime Source DiscoveryまたはRoot Autoload Fallbackを追加しない
- E2EはRepository Source、Root Lock、Root Vendor、Checked-in Quickstartを変更しない
- Docker Startup CommandへInstall、Build、Migrateを隠さない
- Default Compose ServiceへWorker、Scheduler、Migration、Purgeを含めない
- Test Credential／Sensitive Raw値をReportへ記録しない
- Consumer E2E Scriptは `tests/Consumer/` 配下に置き、Root `scripts/` を再作成しない

## Acceptance Criteria

- [ ] Public Journal ConfigをValidationし、無効時はObserverを構成しない
- [ ] JSONLをAppendし、Best Effort／Required Policyを選択できる
- [ ] Inline WelcomeのSensitive Raw値をJSONLへ出さない
- [ ] Quickstart Compose ConfigがValidation成功する
- [ ] Default Compose Service SetがPostgreSQL／HTTPだけである
- [ ] PHP 8.5 CLI／FrankenPHP 1 HTTP／PostgreSQL 18 Runtimeが構成される
- [ ] Build／Migration／Worker／MaintenanceがExplicit Commandでのみ実行される
- [ ] Temp ConsumerへFrameworkがCopy Installされる
- [ ] Consumer RuntimeがRoot Dev Autoloadへ依存しない
- [ ] Inline／Deferred／Retry／Outcome／Retention E2Eが成功する
- [ ] E2E失敗時を含めContainer／Volume／Image／TempをCleanupする
- [ ] Test後にSourceへVendor、Lock、Artifact、Log、Path Repositoryが残らない
- [ ] Focused／Full Test、Mago、Deptrac、Composer Validation、境界Guardが成功する
- [ ] Docs、Report、Checkpointが更新される

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose -f examples/quickstart/compose.yaml config
docker compose -f examples/quickstart/compose.yaml config --services
docker compose run --rm app mago format --check src tests examples/quickstart/app examples/quickstart/bootstrap examples/quickstart/config
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit tests/Internal/Application tests/Internal/Runtime/ProductionRuntimeComposerTest.php tests/Integration/ApplicationHttpRuntimeTest.php tests/Architecture/QuickstartApplicationArchitectureTest.php
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
bash tests/Consumer/quickstart-e2e.sh
! rg -n 'BlackOps\\Internal' examples/quickstart --glob '*.php'
! rg -n '"type"[[:space:]]*:[[:space:]]*"path"|/framework|repositories' examples/quickstart/composer.json
! test -e examples/quickstart/composer.lock
! test -d examples/quickstart/vendor
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples/quickstart --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P7-006-local-runtime-and-consumer-e2e.md` に次を記録する。

- Summary
- Public Journal Configuration／Sensitive Projection Evidence
- Docker／Compose Default and Explicit Process Evidence
- Consumer Isolation Evidence
- Inline／Deferred／Retry／Outcome／Retention Evidence
- Source Cleanliness／Cleanup Evidence
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
