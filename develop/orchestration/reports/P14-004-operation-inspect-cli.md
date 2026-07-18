# P14-004 Operation Inspect CLI Report

Status: Accepted

## Summary

PrefixなしCanonical `operation:inspect <operation-id>`をInstalled ApplicationのFramework Console Kernelへ追加した。CommandはOperation IDを厳密検証した後にだけAccepted Database SnapshotからFramework Connection／Schemaを解決し、P14-003のInternal Diagnostics Queryへ接続する。

Foundは同じ`OperationDiagnostics` AggregateからHumanまたはJSON Version 1を一度だけstdoutへ書き、Invalid／Unavailable／Storage／Decode／Integrity Failureは安全なCodeだけをstderrへ書く。Missing ArgumentはSymfonyによる汎用ExitではなくCommand-owned Exit 2へ畳みつつ、HelpのCanonical Usageは論理必須Argumentとして維持した。

## Changed Files

- `src/Internal/Application/ApplicationDiagnosticsQueryFactory.php`
- `src/Internal/Application/ApplicationConsoleCommandFactory.php`
- `src/Internal/Application/ApplicationConsoleKernel.php`
- `src/Internal/Console/LazyFrameworkCommand.php`
- `src/Internal/Console/OperationInspectCommand.php`
- `src/Internal/Console/OperationInspectFormat.php`
- `src/Internal/Console/OperationInspectHumanFormatter.php`
- `src/Internal/Console/OperationInspectJsonEncoder.php`
- `tests/Internal/Application/ApplicationDiagnosticsConsoleKernelTest.php`
- `tests/Internal/Console/OperationInspectCommandTest.php`
- `tests/Internal/Console/OperationInspectFixture.php`
- `tests/Internal/Console/OperationInspectHumanFormatterTest.php`
- `tests/Internal/Console/OperationInspectJsonEncoderTest.php`
- `tests/Integration/ApplicationConsoleKernelTest.php`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/65-operation-diagnostics.md`
- `develop/spec/66-phase-14-delivery-plan.md`
- `develop/TODO.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Symfony Input Definitionでは`operation-id`をOptionalとして登録し、MissingをCommand内でMalformedと同じ`operation.invalid_id`へ畳んだ。
- Optionalな実装詳細をHelpへ露出しないため、Lazy Commandと実CommandのSynopsisを`operation:inspect <operation-id> [--json]`へ固定した。
- Application Command FactoryはOperation ID検証後に呼ばれるFinder ClosureをCommandへ渡す。Closure呼出時にだけDiagnostics Queryを構成する。
- Query Result、Formatter、EncoderはInternalのままとし、`#[PublicApi]`を追加していない。
- Unsupported Result型は安全側に`diagnostics.integrity_failed`として扱い、Query Factoryの予期しないThrowableはDetailを捨てて`diagnostics.storage_failed`へ畳む。

## Lazy Composition

Kernel構成時に登録するのはCommand名、Description、Argument／Option Definition、Factory Closureだけである。`list`、`help`、Missing／Malformed／Whitespace付きIDでは`ApplicationDiagnosticsQueryFactory::create()`を呼ばない。

Valid IDの実行時だけAccepted Snapshotを`ApplicationDatabaseConfiguration`へ変換し、`framework.connection`と`framework.schema`から次を構成する。

- `PostgreSqlCanonicalJournalStore`
- `PostgreSqlOutcomeStore`
- `PostgreSqlDiagnosticsReader`
- `PostgreSqlDiagnosticsSourceReader`
- `OperationDiagnosticsQuery`

Kernel構成、Help、Invalid InputではMigration、DDL、SQLを実行しない。

## Human Output

Formatterは次の順序で全体を文字列へ構築し、一度だけstdoutへ書く。

1. Operation
2. State
3. Availability
4. Actors
5. Timeline
6. Attempts
7. Outcome

Actor IDはAggregateの`[masked]`を維持し、Timeline／OutcomeはSafe DataだけをJSON Objectとして表示する。色やDecorationへ意味を持たせない。

可変string scalarはJSON string escapingを基礎に、通常ASCII／Unicode表示を維持したまま改行、CR、tab、ESC、DEL／C1、quote、backslashを一行内のescaped representationへ変換する。Identity、State Source、Actor Type、Timeline、Attempt、Outcomeを同じ経路へ通し、Terminalの行／Section／ANSI Decorationを偽装させない。

## JSON Schema

JSON Encoderは固定camelCase Keyを使い、`schemaVersion: 1`、`status: found`、Operation、State、Availability、Timeline、Attempts、Outcomeを一Objectへ変換する。AggregateのCanonical UTC Timestampを変更せず、一つのJSON Objectと末尾改行だけを一度にstdoutへ書く。

## stdout / stderr Matrix

| Result | stdout | stderr |
| --- | --- | --- |
| Found Human | 完全なHuman表示 | Empty |
| Found JSON | Version 1 JSON一Object＋改行 | Empty |
| Invalid Human | Empty | Safe Code一行 |
| Invalid JSON | Empty | Version 1 Error JSON一Object＋改行 |
| Unavailable／Diagnostics Failure | Empty | HumanまたはJSONのSafe Codeだけ |

Testは`ConsoleOutputInterface`互換の分離可能Outputを使い、stdoutとstderrを個別に検証した。

## Exit Code Matrix

| Result | Exit | Code |
| --- | ---: | --- |
| Found | 0 | なし |
| Missing／Malformed／Whitespace付きID | 2 | `operation.invalid_id` |
| Missing／Fully Purged | 3 | `operation.unavailable` |
| Storage Failure | 4 | `diagnostics.storage_failed` |
| Decode Failure | 4 | `diagnostics.decode_failed` |
| Integrity Failure | 4 | `diagnostics.integrity_failed` |

## Sensitive Evidence

- Human／JSON fixtureのActor IDは`[masked]`だけを表示する。
- Database unavailable TestはPrivate DB名、User、CredentialをSnapshotへ含めたが、stderrは`diagnostics.storage_failed`だけである。
- Unexpected ThrowableへPassword、Host、SQL、Payloadを含めてもstderrへDetailを出さない。
- Human scalarへ改行、ESC、DEL／C1、偽のSection Headingを含めてもRaw Control Byteと独立行を出さず、`\n`／`\u001b`／`\u007f`／`\u0085`へescapeする。
- 同じControl Character FixtureをJSON Encoderへ渡し、有効なJSONとして元の文字列へDecodeできることを固定した。
- CommandにSensitive／Error Detail／Raw表示用Option、Alias、Prefix検索を追加していない。
- Formatter／EncoderはCanonical Journal、Raw Outcome、Connection、Throwableを保持しない。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
Result: ./composer.json is valid。

docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: examples/quickstart/composer.json is valid。

docker compose run --rm app mago format --check src tests examples
Result: All files are already formatted。

docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: No issues found。

docker compose run --rm app vendor/bin/phpunit --display-deprecations <P14-004 required targets>
Result: OK (49 tests, 312 assertions)。Database unavailable、Kernel Missing Argument、Human Terminal Injection／JSON Escapeを含む。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (1160 tests, 4180 assertions)。

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 2196 / Warnings 0 / Errors 0。

Management Comment ID Guard
Internal Operation Inspect PublicApi Guard
Forbidden Prefix／Sensitive／Error Detail／Raw Guard
git diff --check
Result: 成功。
```

## Acceptance Criteria

- [x] `operation:inspect <operation-id>`をFramework Console KernelへLazy登録した
- [x] Human／JSONが同じSafe Aggregate内容を表す
- [x] Humanが全Sectionを仕様順で表示する
- [x] JSONがVersion 1固定Key、一Object、末尾改行だけをstdoutへ出す
- [x] Missing／Malformed／Whitespace付きIDをExit 2へ畳み、Queryを構成しない
- [x] Unavailable Exit 3、Storage／Decode／Integrity Exit 4を実装した
- [x] Human／JSON Errorをstderrだけへ書く
- [x] Error時stdoutを空にし、Storage／Throwable Detailを露出しない
- [x] Database unavailableを`diagnostics.storage_failed`へ畳んだ
- [x] `list`／`help`をDatabase非依存に保ち、Canonical Required Usageを表示した
- [x] Application Command名／Alias競合を拒否した
- [x] 旧Prefix名をAlias／予約せず、Application Commandとして利用可能にした
- [x] Migration、Public API、Viewerを追加していない
- [x] Specification、TODO、Report、STATEを同期した
- [x] WorkerはCommitしていない

## Remaining Issues

P14-004を妨げる既知のBlockerはない。Framework Error LogとのOperation ID／Code相関は予定どおりP14-006、Guide／Skeleton／Consumer E2EはP14-007で扱う。

## Orchestrator Review Correction

Orchestrator Reviewで、Human FormatterがSafe Aggregate内の可変string scalarをRaw連結し、Actor Type等に含まれる改行やANSI Control CharacterでTerminal表示を偽装できる境界を指摘された。全scalarを共通escape経路へ統一し、C0、DEL／C1、quote、backslashと偽Section Headingの回帰Testを追加した。修正後にTarget、Full PHPUnit、Mago、Deptrac、Guardを再実行し、すべて成功した。

## Orchestrator Review

OrchestratorはLazy Query Composition、Canonical Help Synopsis、Human／JSON Schema、stdout／stderr、Exit Code、Database unavailable、Application Command競合、Terminal control-character境界を独立に確認した。

```text
docker compose run --rm app vendor/bin/phpunit --display-deprecations <P14-004 orchestrator critical targets>
Result: OK (49 tests, 312 assertions)。Command、Human／JSON、Application Kernel、実Database、Internal Diagnostics回帰を含む。

docker compose run --rm app vendor/bin/phpunit --display-deprecations
Result: OK (1160 tests, 4180 assertions)。

docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/deptrac
Result: Composer Root／Quickstart valid。Mago全成功。Deptrac Violations 0 / Allowed 2196。

Management Comment ID、Internal PublicApi、Forbidden Surface、Raw Property、git diff --check Guard
Result: 成功。
```

Review指摘修正と独立品質Gateがすべて成功したため、P14-004をAcceptedとした。

## Suggested Next Action

OrchestratorがLazy Composition、Help Synopsis、Human／JSON同値性、実stderr分離、Exit Code、Sensitive GuardをReviewする。Accepted後、P14-005 Development Local Viewerへ進む。
