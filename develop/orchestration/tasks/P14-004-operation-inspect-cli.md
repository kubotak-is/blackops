# P14-004: Operation Inspect CLI

Status: Ready

## Goal

P14-003の内部`OperationDiagnosticsQuery`を、Installed ApplicationのCanonical BlackOps CLI `php blackops operation:inspect <operation-id>`へ接続する。

既定Human表示と`--json` Version 1は同じSafe Aggregateを表し、Found Dataはstdout、Invalid／Unavailable／Diagnostics Failureはstderrへだけ出力する。Database Configuration SnapshotとFramework Connectionを再利用し、Command実行時までDatabase／Reader／Queryを構成しない。

## In Scope

- PrefixなしCanonical `operation:inspect <operation-id>` Command
- Framework Console KernelへのLazy登録とFramework Command名予約
- Operation IDの厳密検証とExit Code 2
- P14-003 Query FactoryのCommand実行時Lazy Composition
- Accepted Database Configuration Snapshot、`framework.connection`、`framework.schema`の再利用
- Existing Canonical Journal Reader、Outcome Reader、PostgreSQL Diagnostics Reader／AdapterのComposition
- 同じ`OperationDiagnostics`を入力にするHuman FormatterとJSON Version 1 Encoder
- Operation、State、Availability、Actors、Timeline、Attempts、Outcomeの完全表示
- Human／JSONのFound stdoutとError stderrの分離
- Found 0、Invalid Input 2、Unavailable 3、Storage／Decode／Integrity 4
- Missing Argument、Malformed ID、Whitespace付きID、Unavailable、Database unavailable、Decode／Integrity Failureの安全な出力
- Kernel list／helpのDatabase非依存、Application Command競合、Unknown Command回帰
- Command／Formatter／Encoder単体TestとApplication Console Integration Test

## Out of Scope

- `operation:viewer`、HTTP／HTML Diagnostics Surface
- Public PHP Diagnostics API、Public HTTP Status／Outcome API
- Unauthorized／Tenant Access Policy
- `--show-sensitive`、`--show-error-detail`、Raw JSON／Raw Download
- Operation List／Search、Prefix Search、Latest Operation暗黙選択
- Retry／Replay／Cancel／Delete／Hold等のWrite操作
- Diagnostics Config、Viewer Enable／Bind／Token
- PSR-3 Backend設定、外部Collector、OpenTelemetry、Metric
- Migration、DDL、Schema変更
- Quickstart、Skeleton、Guide、Documentation Websiteの更新
- Documentation Website公開

## Relevant Specifications and Decisions

- `develop/spec/20-identifier-value-objects.md`
- `develop/spec/21-clock-and-time.md`
- `develop/spec/37-postgresql-table-layout.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/47-public-http-runtime-configuration.md`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/65-operation-diagnostics.md`
- `develop/spec/66-phase-14-delivery-plan.md`
- `develop/decisions/092-project-cli-command-names.md`
- `develop/decisions/097-phase-14-operation-diagnostics.md`

## Files Allowed to Change

### Production

- 新規`src/Internal/Console/OperationInspect*.php`
- `src/Internal/Console/LazyFrameworkCommand.php`（stderr／入力境界に共通変更が必要な場合だけ）
- `src/Internal/Application/ApplicationConsoleKernel.php`
- `src/Internal/Application/ApplicationConsoleCommandFactory.php`
- P14-004のLazy Query Compositionに必要な新規`src/Internal/Application/ApplicationDiagnostics*.php`
- `src/Internal/Diagnostics/*.php`（Encoderが必要とするSafe DTOの不変Contract修正またはReviewで判明したIntegrity修正だけ）

### Tests

- 新規`tests/Internal/Console/OperationInspect*.php`
- P14-004のQuery Factory単体Testに必要な新規`tests/Internal/Application/ApplicationDiagnostics*.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- P14-004でInternal Diagnostics Contract修正が必要な場合の`tests/Internal/Diagnostics/*.php`

### Specification and Orchestration

- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/65-operation-diagnostics.md`
- `develop/spec/66-phase-14-delivery-plan.md`
- `develop/TODO.md`
- `develop/orchestration/reports/P14-004-operation-inspect-cli.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は実装を広げず、ReportのBlockerとしてOrchestratorへ返す。

## Command Contract

Canonical Invocationは次だけとする。

```text
php blackops operation:inspect <operation-id>
php blackops operation:inspect <operation-id> --json
```

- `operation-id`は論理的に必須とし、MissingとMalformedをCommand内で同じInvalid Inputへ畳む
- `OperationId::fromString()`へ入力をそのまま渡し、Trim、Case変換、短縮、Prefix補完をしない
- Invalid InputではQuery FactoryとDatabase Connectionを構成しない
- `--json`はValueを取らないFlagとする
- Aliasと旧`blackops:operation:inspect`を登録／予約しない
- `--show-sensitive`、`--show-error-detail`、Raw系Optionを定義しない

## Output Contract

### Found

Human既定表示は仕様順に、Operation、State、Availability、Actors、Timeline、Attempts、Outcomeをすべて含める。Colorの有無へ意味を持たせず、Non-interactiveでも同じ情報を読めるようにする。

`--json`はstdoutへ一つのJSON Objectと末尾改行だけを出す。

```json
{
  "schemaVersion": 1,
  "status": "found",
  "operation": {},
  "state": {},
  "availability": {},
  "timeline": [],
  "attempts": [],
  "outcome": null
}
```

- Keyは専用Encoderで固定したcamelCase
- 時刻はAggregateが持つUTC RFC 3339マイクロ秒文字列を維持
- JSON_UNESCAPED_SLASHES等のPresentation差は許容するが、一つの有効Object以外を混ぜない
- Human FormatterとJSON EncoderはCanonical Store／Outcome Store／Connectionへアクセスしない
- Formatter／Encoderは全出力文字列を構築してから一度だけstdoutへ書き、失敗時に部分Dataを残さない

### Failure

| Result | Exit | stderr code | stdout |
| --- | ---: | --- | --- |
| Found | 0 | Empty | HumanまたはJSON |
| Missing／Malformed ID | 2 | `operation.invalid_id` | Empty |
| Unavailable | 3 | `operation.unavailable` | Empty |
| Storage Failure | 4 | `diagnostics.storage_failed` | Empty |
| Decode Failure | 4 | `diagnostics.decode_failed` | Empty |
| Integrity Failure | 4 | `diagnostics.integrity_failed` | Empty |

- Human ErrorはSafe Codeを含む一行だけをstderrへ出す
- `--json` ErrorはstderrへVersion付きJSON一Objectと末尾改行だけを出す
- JSON Error Shapeは`{"schemaVersion":1,"status":"error","code":"..."}`とする
- SQL、Table名、Connection Parameter、Payload、Codec Detail、Throwable Messageを出さない
- Actual Console Processでは`ConsoleOutputInterface::getErrorOutput()`等を使ってstderrを分離する
- Test用Outputがstderr分離を提供しない場合もProduction契約を弱めず、分離可能なOutput Fixtureでstdout／stderrを個別検証する

## Constraints

- `list`／`help`／Invalid InputでDatabase Connection、Journal、Outcome、Diagnostics Readerを生成しない
- Console Kernel構成時にMigration、DDL、Queryを実行しない
- Accepted Snapshotの`framework.connection`と`framework.schema`以外を暗黙選択しない
- Human／JSONで別Queryや別State計算を行わない
- Aggregate、Formatter、EncoderへRaw Journal、Raw Outcome、Connection、Throwableを保持しない
- Actor IDは`[masked]`のまま、Failure／Dead Letter Messageは存在しないまま出力する
- Error時stdoutを空に保ち、JSON stderrへDecoration、Progress、Debug Detailを混ぜない
- Framework Error LogへのOperation ID／Code相関とLog Backend設定はP14-006で扱い、P14-004のstderr SchemaへLogを混在させない
- Public API、Viewer、Migrationを追加しない
- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する

## Acceptance Criteria

- [ ] `operation:inspect <operation-id>`がFramework Console KernelへLazy登録される
- [ ] `php blackops operation:inspect`と`--json`が同じAggregate内容を表す
- [ ] HumanがOperation、State、Availability、Actors、Timeline、Attempts、Outcomeを仕様順で表示する
- [ ] JSONがVersion 1、固定camelCase Key、単一Object、末尾改行だけをstdoutへ出す
- [ ] Missing／Malformed／Whitespace付きOperation IDがExit 2、stdout Empty、Safe stderrになる
- [ ] Invalid InputではDatabase／Query Factoryを構成しない
- [ ] UnavailableがExit 3、Storage／Decode／IntegrityがExit 4になる
- [ ] Human Errorが安全な一行、JSON ErrorがVersion付き単一Objectとしてstderrだけへ出る
- [ ] Error時stdoutが空で、SQL／Credential／Raw Value／Actor ID／Exception Messageを出さない
- [ ] Database unavailableが`diagnostics.storage_failed`へ畳まれ、Previous Detailを出さない
- [ ] `list`／`help`がDatabase／Artifact／PCNTLなしで`operation:inspect`を表示できる
- [ ] Application Command名／Aliasの`operation:inspect`競合を拒否する
- [ ] `blackops:operation:inspect` Alias／予約とSensitive／Raw Optionが存在しない
- [ ] Migration、Public API、Viewerを追加しない
- [ ] Report／STATEを更新し、WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations \
  tests/Internal/Console/OperationInspectCommandTest.php \
  tests/Internal/Console/OperationInspectJsonEncoderTest.php \
  tests/Internal/Console/OperationInspectHumanFormatterTest.php \
  tests/Integration/ApplicationConsoleKernelTest.php \
  tests/Internal/Diagnostics \
  tests/Transport/PostgreSql/PostgreSqlDiagnosticsReaderTest.php \
  tests/Transport/PostgreSql/PostgreSqlDiagnosticsQueryIntegrationTest.php
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
! rg -n '#\[PublicApi\]' src/Internal/Console/OperationInspect*.php src/Internal/Application/ApplicationDiagnostics*.php
! rg -n 'blackops:operation:inspect|show-sensitive|show-error-detail|raw' src/Internal/Console/OperationInspect*.php src/Internal/Application/ApplicationConsoleKernel.php
git diff --check
```

責務分割によりTest File名が異なる場合は、実在するP14-004対象Testをすべて指定して同等以上の範囲を実行する。`raw` Guardが説明用の否定Test名等を誤検知する場合は、Public Option／Output Key／Command AliasにRaw SurfaceがないことをReportへ具体的に記録する。

## Expected Report

`develop/orchestration/reports/P14-004-operation-inspect-cli.md`へSummary、Changed Files、Decisions and Assumptions、Lazy Composition、Human Output、JSON Schema、stdout／stderr Matrix、Exit Code Matrix、Sensitive Evidence、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
