# P11-001: Release Surface Reset Report

Status: Accepted

## Summary

Experimental `1.1.0` Release Surfaceから旧`blackops:*` Project CLI Aliasとその予約を削除した。Project CLIはProject Root `blackops`とPrefixなしCanonical CommandだけをFramework所有Surfaceとする。旧Command名はApplication Commandとして利用でき、Canonical Command名またはそれをAliasに持つApplication CommandはFail-fastする。

`1.0.0`からのPublic API、Entrypoint、Command、Database Metadata、Configuration、HTTP Surfaceを監査し、破壊的変更、追加、不変部分を下記へ固定した。Internal Compiler Commandの`blackops:*`名はProject CLI Surfaceではないため変更していない。

## Changed Files

- `src/Internal/Application/ApplicationConsoleKernel.php`
- `src/Internal/Console/ApplicationBuildCompileCommand.php`
- `src/Internal/Console/ApplicationOperationListCommand.php`
- `src/Internal/Console/DatabaseMigrationMigrateCommand.php`
- `src/Internal/Console/DatabaseMigrationStatusCommand.php`
- `src/Internal/Console/LazyFrameworkCommand.php`
- `src/Internal/Console/RetentionPlanCommand.php`
- `src/Internal/Console/RetentionPurgeCommand.php`
- `src/Internal/Console/SchedulerDaemonCommand.php`
- `src/Internal/Console/SchedulerRunCommand.php`
- `src/Internal/Console/WorkerRunCommand.php`
- `tests/Internal/Application/ApplicationConsoleKernelTest.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `tests/Consumer/framework-update-generators.sh`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/55-project-generators-and-application-migrations.md`
- `docs/internal/project-generators.md`
- `develop/orchestration/tasks/P11-001-release-surface-reset.md`
- `develop/orchestration/reports/P11-001-release-surface-reset.md`
- `develop/STATE.md`

## Release Surface Audit

Audit Baseはannotated tag `1.0.0`、CurrentはP11-001 Working Treeである。

### Breaking Surface

| Surface | `1.0.0` | `1.1.0` Candidate | Migration Input |
| --- | --- | --- | --- |
| Project Entrypoint | `php bin/blackops ...` | `php blackops ...` | Project Rootの`blackops`を使い、旧`bin/blackops`を削除する |
| Project CLI Command | `blackops:build:compile`等9 Command | `build:compile`等のPrefixなし9 Command | Script、Process Manager、Compose CommandをCanonical名へ更新する |
| Legacy Name Ownership | 旧`blackops:*`名はFramework Command | Alias登録もFramework予約もしない | Application独自Commandとして使用可能 |
| HTTP Protocol Error | JSON Decode／Body Shape失敗はTyped Error Response Contractなし | malformed JSONとNon-object Bodyを安定Code付き400 JSONで応答 | Clientは`status=error`と`code`を扱う |
| HTTP Binding Error | Missing／Type Failureは422 Lifecycle Contractなし | Operation IDとViolation付き422 Rejected | Clientは`operationId`、`category`、`code`、`violations`を扱う |
| Quickstart HTTP Runtime | Classic FrankenPHP | FrankenPHP Worker ModeがDefault、Classicは`classic-mode` Profile | Long-running ProcessのRequest StateをApplication Serviceに保持しない |

9 Commandの変更は次のとおりである。

| Removed `1.0.0` Name | Canonical `1.1.0` Name |
| --- | --- |
| `blackops:build:compile` | `build:compile` |
| `blackops:operation:list` | `operation:list` |
| `blackops:database:status` | `database:status` |
| `blackops:database:migrate` | `database:migrate` |
| `blackops:worker:run` | `worker:run` |
| `blackops:retention:plan` | `retention:plan` |
| `blackops:retention:purge` | `retention:purge` |
| `blackops:scheduler:run` | `scheduler:run` |
| `blackops:scheduler:daemon` | `scheduler:daemon` |

### Additive Surface

- `#[PublicApi]` Source Typeは111から119へ増加し、削除はない。追加は`Choice`、`Count`、`Email`、`Length`、`NotBlank`、`Range`、`Regex`、`Violation`の8型である。
- `RejectionReason::validation()`はOptional `list<Violation>`引数と`violations()` Getterを追加した。既存の1引数Callは維持される。
- `make:operation`と`make:migration`をProject CLIへ追加した。
- `App\Migrations`のApplication Migration Discovery、Framework先行Ordering、共通Doctrine Metadata Tableを追加した。
- OperationValue Validation Runtimeと`400` Protocol／`422` Binding／Value Validation Lifecycleを追加した。
- `symfony/validator:^7.4`をFramework Runtime Dependencyへ追加した。
- QuickstartにFrankenPHP Worker Entrypoint、Classic Fallback Profile、`CLASSIC_HTTP_PORT`、`FRANKENPHP_MAX_REQUESTS`を追加した。

### Unchanged Surface

- `#[PublicApi]` Typeの削除はない。既存Public Typeで変更されたのは上記`RejectionReason`のAdditive Signatureだけである。
- Framework Migration 2 Fileは`1.0.0`から差分がなく、Framework Table／Column／IndexとDoctrine Metadata Table名は変更していない。
- Quickstartの6 PHP Config Fileは`1.0.0`からKey／Shapeの差分がない。Environment追加はLocal HTTP Runtime用である。
- 正常なInline Outcomeの200、Deferred Acceptanceの202、Operation ID Contractは維持する。
- Internal Compiler Commandの`blackops:operation-manifest:compile`、`blackops:build:compile`、`blackops:operation:list`、`blackops:container:compile`、`blackops:http-manifest:compile`は変更していない。

## Decisions and Assumptions

- D094のExperimental Compatibility Policyに従い、旧Project CLI Aliasと旧EntrypointのBackward Compatibilityを成功条件にしない。
- `ApplicationConsoleKernel` Canonical Name SetだけをFramework予約の正本とし、Legacy ConstantとLazy Command Alias Parameterも削除した。
- Framework Update Consumer SmokeはProject Root `blackops`と生成済みSourceの不変性を検証し、旧`bin/blackops`の不変性は検証しない。
- Release DocumentationのUser-facing Migration手順とVersion Metadata更新はP11-002のScopeとする。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: RootとQuickstartのComposer Metadataがstrict validationに成功。

docker compose run --rm app mago format --check src tests examples
Result: LazyFrameworkCommand変更後の1 Fileに整形差分を検出。

docker compose run --rm app mago format src tests examples
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: 1 Fileを整形し、再CheckとLint／Analyzeはすべて成功。No issues found。

docker compose run --rm app vendor/bin/phpunit tests/Internal/Application/ApplicationConsoleKernelTest.php tests/Integration/ApplicationConsoleKernelTest.php
Result: OK (13 tests, 88 assertions)。

docker compose run --rm app vendor/bin/phpunit
Result: OK (871 tests, 2831 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 1712 / Warnings 0 / Errors 0。

bash tests/Consumer/quickstart-e2e.sh
Result: Quickstart Consumer E2E成功。Canonical Project CLIでGenerator、Build、Migration、HTTP、Validation、Workerを検証。

bash tests/Consumer/framework-update-generators.sh
Result: Framework Update Generator Smoke成功。Project Root Entrypointと生成済みSourceの不変、Current Stub／Command、Canonical Buildを検証。

bash tests/Consumer/skeleton-publication.sh --dry-run
Result: Skeleton Publication Dry Run成功。Project Root blackopsの存在／実行可能とbin/blackops不在を検証。

bash -n tests/Consumer/framework-update-generators.sh
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples --glob '*.php'
git diff --check
Result: Shell Syntax、Management ID Guard、Diff Checkはすべて成功。
```

## Acceptance Criteria

- [x] Project CLI CommandがLegacy Aliasを持たない
- [x] Legacy Alias名がFramework予約名として拒否されない
- [x] PrefixなしCanonical Command名、または同名をAliasに持つApplication Commandは競合時にFail-fastする
- [x] Canonical Project CLI CommandのIntegration Testが成功する
- [x] SkeletonにProject Root `blackops`があり、`bin/blackops`がない
- [x] `1.0.0`からのBreaking／Additive SurfaceがReportへ分類される
- [x] Required Quality Commandsが成功する
- [x] ReportとSTATEが更新される

## Remaining Issues

P11-001のBlockerはない。Breaking SurfaceのUser-facing説明、Skeleton Constraint `^1.1`、`CHANGELOG.md`、`UPGRADE.md`、Latest Stable表記はP11-002で更新する。

## Orchestrator Review

Worker差分をFile単位でReviewし、Legacy Alias／予約／定数だけが削除され、Internal Compiler Command名が維持されていることを確認した。Public API 111型から119型、削除なし、Migration／Quickstart PHP Configuration差分なしも`1.0.0` Tagから独立再監査した。

次をOrchestratorが再実行し、すべて成功した。

```text
composer validate --strict（Root／Quickstart）
mago format --check／lint／analyze
PHPUnit: 871 tests / 2831 assertions
Deptrac: Violations 0
framework-update-generators.sh
skeleton-publication.sh --dry-run
Shell Syntax／Management ID Guard／git diff --check
```

Acceptance Criteriaを満たし、BlockerがないためP11-001をAcceptedとする。

## Suggested Next Action

P11-001をTask単位でCommitし、P11-002 Release Documentation and MetadataをTask Packet化する。
