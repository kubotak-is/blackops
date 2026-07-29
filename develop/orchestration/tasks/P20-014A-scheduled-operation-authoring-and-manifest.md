# P20-014A: Scheduled Operation Authoring and Manifest

Status: Accepted

## Goal

Scheduled Application Operationの最初のProduction Sliceとして、`#[ScheduledBy]`、Schedule Context、Schedule Metadata、Build-time Validation、Operation Manifest Schemaを実装する。

Runtime評価、PostgreSQL、Operation実行を接続せず、後続TaskがReflectionへFallbackせずCompile済みMetadataだけでScheduleを構成できる状態にする。

## Source of Truth

- `AGENTS.md`
- `develop/decisions/134-scheduled-application-operation.md`
- `develop/spec/98-scheduled-application-operation.md`
- `develop/decisions/115-deferred-authoring-and-operation-dispatch.md`
- `develop/spec/82-operation-dispatch-and-deferred-authoring.md`
- `develop/spec/08-registry-and-manifest.md`
- `develop/spec/17-core-api.md`
- `develop/spec/18-operation-envelope.md`
- `develop/spec/19-execution-context-api.md`
- `develop/spec/21-clock-and-time.md`
- Current Operation Metadata／Manifest Source and Tests

## In Scope

- Public non-repeatable `BlackOps\Core\Attribute\ScheduledBy`
- Public immutable `BlackOps\Core\ScheduleContext`
- Public immutable `BlackOps\Core\Registry\OperationScheduleMetadata`
- `ExecutionContext::schedule(): ?ScheduleContext`
- Optional Schedule Metadata on `OperationMetadata`
- Schedule name index on `OperationRegistry`
- 5 Field numeric POSIX Cron parser／validator shared by Compiler and Manifest Decoder
- IANA Timezone validation and UTC default
- Scheduled OperationValue instantiability／zero-required-argument validation
- Scheduled Ephemeral Outcome rejection
- Schedule name uniqueness acrossRegistry
- Operation Manifest Schedule encode／decode and Schema Version `3`
- Unit／Manifest Regression
- Report／STATE／TODO synchronization

## Out of Scope

- PostgreSQL Table／Migration、Schedule State、Occurrence
- Calendar Slot evaluation、DST traversal、Misfire、Overlap
- Schedule Actor Provider、Authorization Composition
- Inline／Deferred Scheduled Invocation
- ExecutionContext Transport Codec／Deferred Payload／Journal Projection
- `operation:schedule:run`、Daemon、Application Runtime Composition
- HTTP／Console／Frontend ManifestへのSchedule公開
- Example、Consumer、Guide、Website
- New Composer Dependency
- Commit、Push、PR、External Deploy

## Files Allowed to Change

- `src/Core/Attribute/ScheduledBy.php`
- `src/Core/ScheduleContext.php`
- `src/Core/ExecutionContext.php`
- `src/Core/Registry/OperationScheduleMetadata.php`
- `src/Core/Registry/OperationMetadata.php`
- `src/Core/Registry/OperationRegistry.php`
- `src/Internal/Scheduling/**`
- `src/Internal/Registry/OperationMetadataCompiler.php`
- `src/Internal/Registry/OperationManifestMetadataCodec.php`
- `src/Internal/Registry/OperationManifestFile.php`
- `tests/Core/Attribute/ScheduledByTest.php`
- `tests/Core/ScheduleContextTest.php`
- `tests/Core/ExecutionContextTest.php`
- `tests/Core/Registry/OperationRegistryTest.php`
- `tests/Internal/Scheduling/**`
- `tests/Internal/Registry/OperationMetadataCompilerTest.php`
- `tests/Internal/Registry/OperationManifestFileTest.php`
- `develop/spec/98-scheduled-application-operation.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-014A-scheduled-operation-authoring-and-manifest.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Implementation Constraints

- `ScheduledBy`はClass Target、非Repeatableとし、`name`、`cron`、`timezone = 'UTC'`を持つ
- Schedule名はOperation Typeと同じlowercase dot-separated Grammarを使う
- CronはSpecification 98の5 Field、numeric Range／List／Step／Wildcardだけを受理する
- Day of Month／Day of Week OR SemanticsはParser Modelへ明示し、Evaluatorが再解釈しない形にする
- TimezoneはIANA Nameまたは`UTC`だけを受理し、Host DefaultへFallbackしない
- Attribute ConstructorとManifest Decoderの両方がInvalid Inputを拒否する
- Safe ErrorへClass名、Cron全文、Timezone、Value Propertyを露出しない
- Scheduled OperationValueはInstantiableかつ必須Constructor引数0をBuild時に要求する。CompilerでValue Constructorを実行しない
- Existing non-scheduled OperationはSchedule Metadata `null`で挙動を変えない
- `ScheduledBy`だけならInline、`ScheduledBy`＋`Deferred`ならDeferredを維持する
- Schedule名重複はRegistry構築時に拒否する
- Operation Manifest Schemaを`3`へ更新し、旧Schemaを暗黙移行しない
- Manifest Scheduleは`name`、`cron`、`timezone`だけを保存する
- `ScheduleContext`はScheduled AtをUTCへ正規化し、Name／TimezoneをInvariant検証する
- `ExecutionContext` Constructorの末尾Optional Parameterとして追加し、既存Call Siteを壊さない
- Runtime、Transport、Journalは接続せず、Schedule Contextの既存Codecへの追加も行わない
- New Dependencyを追加しない
- Existing Phase 20 Working Tree差分を保持する
- WorkerはCommitしない

## Acceptance Criteria

- [x] `ScheduledBy`のPublic readonly Attribute ShapeとDefault UTCをTestする
- [x] Schedule名、Cron、TimezoneのPositive／Negative MatrixをTestする
- [x] Cron Parserが5 Field Range／List／Step／WildcardとDOM／DOW ORを表現する
- [x] Scheduled Valueの必須引数、Private Constructor、Abstract／Non-instantiableをBuild Errorにする
- [x] Repeated Schedule、Duplicate Schedule Name、Ephemeral Scheduled OperationをBuild Errorにする
- [x] Scheduled Inline／DeferredのStrategy IndependenceをTestする
- [x] `ScheduleContext`のUTC Normalizationと`ExecutionContext::schedule()` null／non-nullをTestする
- [x] Operation Metadata／RegistryがOptional Scheduleを保持し、Schedule Name Lookupを提供する
- [x] Manifest Schema `3`がScheduleあり／なしをRound-tripする
- [x] Malformed Schedule Manifestと旧Schemaを拒否する
- [x] Existing Operation Metadata／Manifest／Registry Testを維持する
- [x] New Dependency、Runtime、Database、CLI、Guideを変更しない
- [x] Required Commandsを実行し、Repository既存Blockerには変更Sourceの代替Evidenceを残す
- [x] Report／STATE／TODOを同期し、WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app vendor/bin/phpunit --display-deprecations tests/Core tests/Internal/Registry tests/Internal/Scheduling
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago analyze src tests
docker compose run --rm app vendor/bin/deptrac analyse --no-progress
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

存在しない`tests/Internal/Scheduling`は実装で作成する。Commandが環境要因で実行できない場合は、未実行理由と代替EvidenceをReportへ記録する。

## Completion Report

`develop/orchestration/reports/P20-014A-scheduled-operation-authoring-and-manifest.md`へSummary、Changed Files、Decisions and Assumptions、Cron Validation Matrix、Manifest Compatibility、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
