# P8-002A: Typed Self-handled Invocation

Status: Completed

## Goal

Self-handled OperationがNative Typed `OperationValue` とOptional `ExecutionContext` を直接受け取れるBuild／Manifest／Runtime Invocationを実装し、Quickstartから `OperationHandler`、Generic DocBlock、Envelope Value Narrowing Guardを削除する。

## In Scope

- Typed Self-handled Signature Reflection／Validation
- Value-only／Value＋ExecutionContext Invocation Mode
- Build MetadataとManifest LoadのSignature Validation
- Handler Metadata／ResolverのTyped Object対応
- Inline／Deferred Worker Runtimeの共通Invoker
- Self-handled Definition InstanceのContainer共有
- Legacy Self-handled／Separate `OperationHandler` 互換
- Runtime Value／Result防御
- Typed HandlerのContainer Autowire／Explicit Binding尊重
- Quickstart Welcome／Report移行
- Quickstart Applicationを通常Mago Analysisへ追加
- Integration／Architecture／Guide／Internals更新

## Out of Scope

- Typed Separate Handler
- `OperationHandler` Public Interface削除／非推奨化
- `#[Accepts]`／`#[Returns]` 削除またはNative Typeからの自動推論
- Concrete Outcome直接Return
- Runtime Source Discovery
- D073 Distribution Publication

## Relevant Specifications and Decisions

- `develop/decisions/071-operation-authoring-and-discovery.md`
- `develop/decisions/074-typed-self-handled-operation-signature.md`
- `develop/spec/17-core-api.md`
- `develop/spec/50-operation-authoring-and-build-discovery.md`
- `develop/spec/53-typed-self-handled-operation-invocation.md`

## Files Allowed to Change

- `src/Core/Registry/OperationMetadata.php`
- `src/Internal/Registry/**`
- `src/Internal/Execution/**`
- `src/Internal/DependencyInjection/**`
- `src/Internal/Runtime/ProductionRuntimeComposer.php`
- `src/Internal/Application/ApplicationWorkerComposer.php`
- `tests/Internal/Registry/**`
- `tests/Internal/Execution/**`
- `tests/Internal/DependencyInjection/**`
- `tests/Internal/Runtime/**`
- `tests/Internal/Application/ApplicationConsoleKernelTest.php`
- `tests/Internal/Console/**`
- `tests/Http/**`
- `tests/Integration/**`
- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- `examples/quickstart/app/Feature/Welcome/ShowWelcome/ShowWelcome.php`
- `examples/quickstart/app/Feature/Welcome/ShowWelcome/WelcomeValue.php`
- `examples/quickstart/app/Feature/Report/GenerateReport/GenerateReport.php`
- `examples/quickstart/app/Feature/Report/GenerateReport/GenerateReportValue.php`
- `examples/quickstart/README.md`
- `mago.toml`
- `docs/guide/application-bootstrap.md`
- `docs/guide/mvp-sample.md`
- `docs/guide/runtime-bootstrap.md`
- `docs/internals/bootstrap.md`
- `docs/internals/operation-registry.md`
- `docs/internals/runtime-container.md`
- `docs/internals/worker-runtime.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P8-002A-typed-self-handled-invocation.md`
- `develop/orchestration/reports/P8-002A-typed-self-handled-invocation.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は実装を広げず、ReportのBlockerとして返す。

## Signature Contract

Accepted Typed Self-handled:

```php
public function handle(WelcomeValue $value): OperationResult

public function handle(GenerateReportValue $value, ExecutionContext $context): OperationResult
```

Build時に次を拒否する。

- Missing／Private／Protected／Static `handle`
- Parameter 0個または3個以上
- Untyped／Builtin／Union／Intersection／Nullable Value
- `OperationValue` 非実装Value
- `#[Accepts]` と異なるValue Class
- Optional／By-reference／Variadic Value
- 第二引数が `ExecutionContext` 以外
- Optional／By-reference／Variadic Context
- Missing／Builtin／Union／Intersection／Nullable／`OperationResult` 以外のReturn
- Typed Self-handledと `#[HandledBy]` の同時指定

ErrorはOperation ClassとSignature責務を示し、管理番号、Payload、Credentialを含めない。

## Compatibility Contract

- `Operation`＋Typed `handle()`＋No `#[HandledBy]`: Typed Self-handled
- `Operation`＋`OperationHandler`＋No `#[HandledBy]`: Legacy Self-handled
- `Operation`＋`#[HandledBy]`、Handlerが `OperationHandler`: Separate Handler
- Existing Manifest Field名とBuild ID Formatを維持
- Legacy Handlerは引き続きEnvelopeを受け取る
- Typed Separate Handlerは拒否する

## Runtime Contract

- Handler ResolverはContainerからObjectを解決し、Metadata Handler Classとの一致を確認する
- 共通InvokerがLegacy／Typed Modeを選択する
- Typed ModeはEnvelope ValueがMetadata Value Classであることを確認する
- Context ParameterなしはValueだけ、ありはValue＋Envelope Contextを渡す
- Invocation結果が `OperationResult` でなければ安全に失敗する
- InlineとDeferred Workerが同じInvocation Contractを使う
- Lease RecoveryはSelf-handled DefinitionをContainer解決できるがHandlerを実行しない
- Runtime Source Discoveryを行わない

## Quickstart Migration

Welcome:

```php
public function handle(WelcomeValue $value): OperationResult
```

Report:

```php
public function handle(GenerateReportValue $value, ExecutionContext $context): OperationResult
```

- `OperationHandler`／`OperationEnvelope`／`LogicException` Importを削除
- `@implements` DocBlockを削除
- Value `instanceof` Guardを削除
- ReportはContextのAttempt nullだけを業務前提として安全に確認する
- Quickstart app全体が通常 `mago analyze` で成功する

## Acceptance Criteria

- [x] Value-only Typed Self-handledをCompile／Invokeできる
- [x] Value＋Context Typed Self-handledをInline／DeferredでInvokeできる
- [x] Inline ContextのOperation IDとnull Attemptを取得できる
- [x] Deferred ContextのOperation IDとAttemptを取得できる
- [x] 全Invalid SignatureをBuild時に拒否する
- [x] Manifest LoadがTyped Signature／Value不整合を拒否する
- [x] Runtime Value／Result不整合を安全に拒否する
- [x] Legacy Self-handled／Separate Handlerが回帰しない
- [x] Container AutowireとExplicit Service BindingがTyped Handlerで成立する
- [x] QuickstartからInterface／Generic DocBlock／Value Guardがなくなる
- [x] Quickstart HTTP／Worker／Retry／Outcome／Consumer E2Eが成功する
- [x] 通常Mago AnalysisがQuickstart appを含み成功する
- [x] Focused／Full Test、Deptrac、Composer、Guardが成功する
- [x] Docs、Report、Checkpointが更新される

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app mago analyze examples/quickstart/app
docker compose run --rm app vendor/bin/phpunit tests/Internal/Registry tests/Internal/Execution tests/Internal/DependencyInjection tests/Internal/Runtime tests/Internal/Application/ApplicationConsoleKernelTest.php tests/Internal/Console tests/Http tests/Integration tests/Architecture/QuickstartApplicationArchitectureTest.php
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
bash tests/Consumer/quickstart-e2e.sh
! rg -n 'OperationHandler|OperationEnvelope|@implements[[:space:]]+OperationHandler|instanceof[[:space:]]+(WelcomeValue|GenerateReportValue)' examples/quickstart/app --glob '*.php'
! rg -n 'BlackOps\\Internal' examples/quickstart --glob '*.php'
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P8-002A-typed-self-handled-invocation.md` に次を記録する。

- Summary
- Signature Validation Evidence
- Inline／Deferred Context Evidence
- Legacy Compatibility Evidence
- Runtime／Manifest Defense Evidence
- Quickstart／Mago Evidence
- Changed Files
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
