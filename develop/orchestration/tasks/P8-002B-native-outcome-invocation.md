# P8-002B: Native Outcome Invocation

Status: Completed

## Goal

Typed Self-handled OperationのValue／OutcomeをNative `handle()` Signatureから推論し、成功時は具象Outcomeまたは`void`を直接返し、予期された業務拒否はFramework Public `OperationRejectedException`で既存Rejected Lifecycleへ接続する。

## In Scope

- Native Value／Outcome／Void Signature ReflectionとBuild Validation
- Typed Self-handledでOptionalな `#[Accepts]`／`#[Returns]` 移行互換
- Existing Typed `OperationResult` Compatibility Mode
- Manifest Invocation ModeのBackward-compatible保存／Load Validation
- Runtime Outcome／Void／Rejected Exception Normalization
- Public `OperationRejectedException`とCategory Factory
- Inline／Deferred Rejected Lifecycle統合
- Retryable／通常例外／Worker Interruptの既存Failure Boundary維持
- Quickstart Welcome／ReportのNative Outcome移行
- Void、Rejected、Runtime Mismatch、Legacy Compatibility Test
- Guide／Internals／Quickstart README更新

## Out of Scope

- Legacy `OperationHandler`、`OperationResult`、`Accepts`、`Returns` Public API削除
- Typed Separate Handler
- 複数Success Outcome、Union／Intersection Return
- Rejection自由文Message／Details
- HTTP Rejection Status Mappingの新規設計
- D073 Distribution Publication
- `LT-blackops-framework.md`

## Relevant Specifications and Decisions

- `develop/decisions/075-native-outcome-and-rejection-exception.md`
- `develop/spec/04-handler-and-result.md`
- `develop/spec/17-core-api.md`
- `develop/spec/29-handler-result-contract.md`
- `develop/spec/50-operation-authoring-and-build-discovery.md`
- `develop/spec/53-typed-self-handled-operation-invocation.md`
- `develop/spec/54-native-outcome-and-rejection-exception.md`

## Files Allowed to Change

- `src/Core/Exception/OperationRejectedException.php`
- `src/Core/Registry/OperationMetadata.php`
- `src/Internal/Registry/**`
- `src/Internal/Execution/**`
- `src/Internal/DependencyInjection/**`
- `src/Internal/Runtime/ProductionRuntimeComposer.php`
- `src/Internal/Application/ApplicationWorkerComposer.php`
- `tests/Core/**`
- `tests/Internal/Registry/**`
- `tests/Internal/Execution/**`
- `tests/Internal/DependencyInjection/**`
- `tests/Internal/Runtime/**`
- `tests/Internal/Application/ApplicationConsoleKernelTest.php`
- `tests/Internal/Console/**`
- `tests/Http/**`
- `tests/Integration/**`
- `tests/Architecture/**`
- `examples/quickstart/app/Feature/Welcome/ShowWelcome/ShowWelcome.php`
- `examples/quickstart/app/Feature/Report/GenerateReport/GenerateReport.php`
- `examples/quickstart/README.md`
- `docs/guide/application-bootstrap.md`
- `docs/guide/mvp-sample.md`
- `docs/guide/runtime-bootstrap.md`
- `docs/internal/architecture.md`
- `docs/internal/handler-result.md`
- `docs/internal/inline-dispatcher.md`
- `docs/internal/operation-metadata.md`
- `docs/internal/operation-registry.md`
- `docs/internal/runtime-container.md`
- `docs/internal/worker-runtime.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P8-002B-native-outcome-invocation.md`
- `develop/orchestration/reports/P8-002B-native-outcome-invocation.md`
- `develop/STATE.md`

ユーザー回答を含む `develop/decisions/073-skeleton-distribution-publication-boundary.md` とユーザー所有 `LT-blackops-framework.md` は変更、削除、ステージしない。許可外修正が必要なら実装を広げずReportのBlockerへ記録する。

## Standard Signature Contract

Accepted:

```php
public function handle(WelcomeValue $value): WelcomeShown

public function handle(
    GenerateReportValue $value,
    ExecutionContext $context,
): ReportGenerated

public function handle(RebuildIndexValue $value): void
```

Build CompilerはValue ClassとOutcome ClassをNative Signatureから推論する。`void`はMetadata Outcome `EmptyOutcome::class`へ正規化する。

Typed Self-handledのAttribute規則:

- `Accepts`／`Returns`とも未指定: 標準形として受理
- 指定時: 推論Value／Outcomeと完全一致する場合だけ移行互換として受理
- 片方だけ指定: 指定側を検証し、未指定側を推論
- Attribute重複: 拒否
- Existing `handle(...): OperationResult`: `Accepts`／`Returns`を各一つ必要とするCompatibility Mode
- Legacy Self-handled／Separate Handler: Existing Attribute Contractを維持

Invalid Standard Return:

- Missing、Nullable、Union、Intersection
- `void`以外のBuiltin
- `Outcome`を実装しないClass／Interface
- AbstractまたはInterface Outcome Class
- `OperationResult`をAttributeなしで使用

ErrorはClass／Signature責務だけを示し、Payload、Credential、管理番号を含めない。

## Rejection Exception Contract

`BlackOps\Core\Exception\OperationRejectedException` は `#[PublicApi]` のfinal Exceptionとし、`RejectionReason`を保持する。

```php
throw OperationRejectedException::validation('input.invalid');
throw OperationRejectedException::unauthorized('auth.required');
throw OperationRejectedException::forbidden('report.forbidden');
throw OperationRejectedException::notFound('report.not_found');
throw OperationRejectedException::conflict('inventory_unavailable');
throw OperationRejectedException::businessRule('order.cannot_create');
```

- `reason(): RejectionReason` を公開する
- MessageへCode、Payload、Credentialを含めない
- 不正Code Validationは既存 `RejectionReason`へ委譲する
- InvokerはこのExceptionだけをRejected Resultへ変換する
- Retryable／通常Throwableはそのまま再throwする

## Runtime and Manifest Contract

- Existing Handler／Value／Outcome／Strategy Manifest FieldとSchema Versionを維持する
- Native Outcome、Void、Existing Typed ResultをRuntime Reflectionなしで区別できるOptional Modeを保存する
- Mode Field欠落の旧ManifestはCurrent Class Signatureと既存Metadataから安全に復元する
- ManifestのMode／Value／Outcome改変をLoad時に拒否する
- Native Outcome実値はMetadata Outcome Classと完全一致を必須とする
- Void Modeはnullだけを受け `OperationResult::completed()`へ正規化する
- Rejected ExceptionはInline／Deferredとも既存OperationRejected Journal／Stateへ接続する
- Other ThrowableのRetry／Failure／Dead Letter契約を変更しない
- Runtime Source Discovery／Signature Reflectionを追加しない

## Quickstart Migration

Welcome:

```php
public function handle(WelcomeValue $value): WelcomeShown
```

Report:

```php
public function handle(
    GenerateReportValue $value,
    ExecutionContext $context,
): ReportGenerated
```

- `Accepts`／`Returns` ImportとAttributeを削除
- `OperationResult` ImportとCompleted Wrapperを削除
- ReportのTemporary Exceptionは通常どおりSupervision Retryへ渡す
- Rejected Exceptionの利用例をGuideまたはTest Fixtureで示す

## Acceptance Criteria

- [x] Native Outcome Typed Self-handledをAttributeなしでCompile／Invokeできる
- [x] Native Voidを`EmptyOutcome` Completedへ正規化できる
- [x] Optional ContextをInline／Deferredへ渡せる
- [x] Optional Attribute一致を受理し、不一致／重複を拒否する
- [x] Existing Typed `OperationResult`、Legacy Self-handled、Separate Handlerが回帰しない
- [x] Manifest旧形式復元とTamper Defenseが成立する
- [x] Public Rejected Exceptionの全Category FactoryとReason Accessorが成立する
- [x] Rejected ExceptionがInline／Deferred Rejected Lifecycleへ接続される
- [x] Retryable／通常例外がRejectedへ変換されない
- [x] Runtime Outcome／Void不整合を安全に拒否する
- [x] QuickstartからAccepts／Returns／OperationResult Wrapperがなくなる
- [x] Quickstart HTTP／Worker／Retry／Outcome／Consumer E2Eが成功する
- [x] Mago、Focused／Full PHPUnit、Deptrac、Composer、Guardが成功する
- [x] Docs、Report、Checkpointが更新される

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app mago analyze examples/quickstart/app
docker compose run --rm app vendor/bin/phpunit tests/Core tests/Internal/Registry tests/Internal/Execution tests/Internal/DependencyInjection tests/Internal/Runtime tests/Internal/Application/ApplicationConsoleKernelTest.php tests/Internal/Console tests/Http tests/Integration tests/Architecture
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
bash tests/Consumer/quickstart-e2e.sh
! rg -n '#\[(Accepts|Returns)\b|OperationResult(::completed)?' examples/quickstart/app --glob '*.php'
! rg -n 'BlackOps\\Internal' examples/quickstart --glob '*.php'
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P8-002B-native-outcome-invocation.md` に次を記録する。

- Summary
- Native Signature／Attribute Inference Evidence
- Rejection／Failure Boundary Evidence
- Manifest／Runtime Compatibility Evidence
- Quickstart Evidence
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
