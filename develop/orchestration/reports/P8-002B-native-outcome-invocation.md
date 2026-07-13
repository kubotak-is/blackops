# P8-002B Native Outcome Invocation Report

## Summary

Typed Self-handled OperationのValueとOutcomeをNative `handle()` Signatureから推論し、具象Outcomeまたは`void`をFramework内部の`OperationResult`へ正規化した。`OperationRejectedException`をPublic APIとして追加し、Inline／Deferredの既存Rejected Lifecycleへ接続した。Legacy Self-handled、Separate Handler、Attribute付きTyped `OperationResult` Returnは互換Modeとして維持した。

Quickstart Welcome／Reportから`Accepts`、`Returns`、`OperationResult::completed()`を削除し、独立Consumer E2EでHTTP、Deferred受付、Retry、Outcome保存まで成功した。

## Native Signature／Attribute Inference Evidence

`TypedSelfHandledSignatureValidator`はRequired Named `OperationValue`、Optional Required `ExecutionContext`、具象`Outcome`／`void`／Compatibility `OperationResult`を識別する。Native Outcomeでは`Accepts`／`Returns`の両方なし、片方だけ、両方ありを受理し、指定値はSignatureと完全一致する場合だけ許可する。`void`はMetadata Outcomeの`EmptyOutcome`へ正規化する。

TestはNative Outcome／Void推論、Optional Attribute一致、不一致、Missing／Builtin／Nullable／Union／Intersection Return、非Outcome、Abstract Outcome、Attributeなし`OperationResult`を検証した。

## Rejection／Failure Boundary Evidence

`OperationRejectedException`は`#[PublicApi]`付きfinal Exceptionで、Validation、Unauthorized、Forbidden、Not Found、Conflict、Business RuleのFactoryと`reason()`を提供する。Messageは固定値で、Code Validationは`RejectionReason`へ委譲する。

共通InvokerはこのExceptionだけをRejected Resultへ変換する。Inline Testは`operation.rejected`終端Journal、Deferred TestはRejected State／JournalとOutcome非保存を確認した。通常ThrowableはInvokerから再throwされ、既存Worker TestでRetry／Failed／Dead Letter／Interrupt境界の回帰がないことを確認した。

## Manifest／Runtime Compatibility Evidence

Existing Manifest FieldとSchema Versionを維持し、Optional `typedSelfHandledMode`に`result`／`outcome`／`void`を保存する。Fieldがない旧ManifestはCurrent Class SignatureからModeを復元し、Value、Outcome、Context、Mode改変をLoad時に拒否する。

RuntimeはCompiled Modeだけを使い、Native OutcomeはMetadata Outcomeと完全一致、Voidはnullだけを受理する。Source DiscoveryとSignature ReflectionはRuntimeへ追加していない。Existing Typed ResultとLegacy Envelope InvocationはFocused／Full Suiteで成功した。

Orchestrator Review後、Public `OperationMetadata`直渡しの境界で未知Typed Modeをfail closedに拒否し、Void ModeのOutcomeを`EmptyOutcome`に限定した。Testは未知ModeとVoid／Outcome不整合のどちらでもHandler Counterが0のままで、業務副作より前に`LogicException`となることを確認した。

## Quickstart Evidence

Welcomeは`handle(WelcomeValue): WelcomeShown`、Reportは`handle(GenerateReportValue, ExecutionContext): ReportGenerated`となった。ReportのTemporary ExceptionはRejectedへ変換されずSupervision Retryへ渡る。Quickstart Guardは`Accepts`、`Returns`、`OperationResult`、Internal ImportがApplication Codeにないことを確認した。

## Changed Files

- Public API: `src/Core/Exception/OperationRejectedException.php`、`src/Core/Registry/OperationMetadata.php`
- Registry: Typed Signature／Parameter Validation、Value／Outcome Compiler、Handler Metadata Compiler、Manifest Codec／Decoder
- Runtime: `HandlerInvoker`、Invocation Metadata Validator、Typed Result Normalizer
- Quickstart: Welcome／Report Operation、README
- Tests: Core Exception、Registry、Invoker、Inline／Deferred Lifecycle
- Docs: Application／Runtime Guide、Architecture／Handler Result／Metadata／Registry／Worker Internals
- Management: `develop/TODO.md`、Task Packet、Report、`develop/STATE.md`

`develop/decisions/073-skeleton-distribution-publication-boundary.md`のユーザー回答差分は変更していない。

## Decisions and Assumptions

- Public `OperationMetadata`の末尾へOptional Modeを追加し、既存Constructor CallはDefault nullで互換にした。Direct Metadataのnull Typed ModeはExisting Result Compatibilityとして扱う。
- Concrete OutcomeはSubclassを許容せずRuntime Class完全一致とした。
- `OperationRejectedException`以外のThrowableは捕捉せず、既存SupervisionとWorker Recoveryに任せる。
- Public Legacy APIの削除、Typed Separate Handler、HTTP Status MappingはScope外とした。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: examples/quickstart/composer.json is valid.

docker compose run --rm app mago format --check src tests examples
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app mago analyze examples/quickstart/app
Result: All commands completed with INFO No issues found.

docker compose run --rm app vendor/bin/phpunit tests/Core tests/Internal/Registry tests/Internal/Execution tests/Internal/DependencyInjection tests/Internal/Runtime tests/Internal/Application/ApplicationConsoleKernelTest.php tests/Internal/Console tests/Http tests/Integration tests/Architecture
Result: OK (471 tests, 1430 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/phpunit
Result: OK (721 tests, 2374 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: 361 files / Violations 0 / Skipped 0 / Uncovered 0 / Allowed 1546 / Warnings 0 / Errors 0.

bash tests/Consumer/quickstart-e2e.sh
Result: Quickstart consumer E2E passed.

Quickstart Accepts／Returns／OperationResult Guard
Quickstart Internal Import Guard
Production／Test Management ID Guard
Result: No matches; all negated commands exited 0.

git diff --check
Result: No output.

Orchestrator Review fail-closed reinforcement:
docker compose run --rm app mago format src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
Result: All commands completed with INFO No issues found.

docker compose run --rm app vendor/bin/phpunit tests/Core tests/Internal/Registry tests/Internal/Execution tests/Internal/DependencyInjection tests/Internal/Runtime tests/Internal/Application/ApplicationConsoleKernelTest.php tests/Internal/Console tests/Http tests/Integration tests/Architecture
Result: OK (473 tests, 1436 assertions). Runtime PHP 8.5.7.

Review reinforcement guards and git diff --check
Result: No matches and no output; all commands exited 0.
```

## Acceptance Criteria

Task PacketのAcceptance Criteriaをすべて満たした。Native Outcome／Void、Optional Context／Attribute、Legacy互換、Manifest復元／Tamper Defense、Public Rejected Exception、Inline／Deferred Lifecycle、Failure Boundary、Runtime Mismatch、Quickstart Consumer E2E、Mago／PHPUnit／Deptrac／Composer／Guard、Docs／Report／Checkpointを完了した。Orchestrator Reviewと再検証を通過し、P8-002Bを受け入れた。

## Remaining Issues

P8-002BのBlockerはない。Public Legacy APIの廃止、Typed Separate Handler、複数Success Outcome、HTTP Rejection Status MappingはScope外である。P8-003開始前にD073のDistribution Repository詳細確定が必要である。

## Suggested Next Action

D073のDistribution Repository詳細を確定し、P8-003へ進む。
