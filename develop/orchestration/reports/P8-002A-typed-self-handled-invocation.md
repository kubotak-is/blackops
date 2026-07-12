# P8-002A Typed Self-handled Invocation Report

## Summary

Native `OperationValue`だけ、またはValueと`ExecutionContext`を受け取るTyped Self-handled OperationをBuild、Manifest、Runtimeへ接続した。BuildはSignatureを厳密に検証し、ManifestはInvocation Modeを保存してLoad時に現在のSignatureと再照合する。InlineとDeferred Workerは共通Invokerを使用し、Legacy Self-handledとSeparate `OperationHandler`のEnvelope Invocationを維持する。

Quickstart Welcome／ReportをTyped Self-handledへ移行し、Application Sourceを通常Mago対象へ追加した。Consumer E2EでFramework Mirror Install、Artifact Build、Migration、Inline HTTP、Deferred受付、初回Retry、再起動相当Worker成功、Outcome、Cleanupまで成功した。

## Signature Validation Evidence

`TypedSelfHandledSignatureValidator`とParameter Validatorは、Public Non-static `handle`、1または2引数、`#[Accepts]`と完全一致するRequired Named `OperationValue`、Optionalな第二Required `ExecutionContext`、Native `OperationResult` Returnを検証する。

Data Provider TestはPrivate／Protected／Static／Abstract、0／3引数、Untyped／Builtin／Union／Intersection／Nullable／非OperationValue／不一致Value、Optional／Reference／Variadic Value、誤型／Optional／Reference／Variadic Context、Missing／Builtin／Union／Intersection／Nullable／誤Returnの26ケースをBuild境界で拒否した。Typed Self-handledと`#[HandledBy]`の併用もCompiler Testで拒否した。Abstract Typed DefinitionはBuild CompilerとManifest Loadの両方でInstantiableでないClassとして拒否する。

## Inline／Deferred Context Evidence

共通`HandlerInvoker`はCompiled Metadataに従いValue-onlyまたはValue＋Contextを呼び分ける。Inline Dispatcher Testは受信時のOperation IDが渡り、`attempt()`がnullであることを確認した。Deferred Worker Runtime TestはClaimのOperation IDとAttempt 1が渡ることを確認した。

Lease RecoveryはContainerからSelf-handled Definition Objectを解決できるがHandlerを実行しない既存Contractを維持した。

## Legacy Compatibility Evidence

Legacy Self-handled `OperationHandler`とSeparate `#[HandledBy]` HandlerはMetadata上Typed Flagを持たず、共通Invokerから従来どおり`OperationEnvelope`を受け取る。Focused SuiteとFull Suiteで既存HTTP、Worker、Console、Application、Integration Runtimeの回帰がないことを確認した。

Operation Manifestの既存Handler Field名とSchema Versionは維持した。新しいInvocation Flagがない旧ManifestはDefinition／Handler／SignatureからModeを安全に復元するTestを追加した。

## Runtime／Manifest Defense Evidence

Handler ResolverはPSR-11からObjectを解決し、Metadata Handler Classとの一致を確認する。InvokerはHandler Class、Envelope Value Class、Typed Callable、Legacy Interface、Return `OperationResult`を防御する。

Orchestrator Review後、InvokerはContext Flagだけがtrueの不正Mode、DefinitionとHandlerが異なるTyped Separate偽装、Typed Handler Objectが`Operation`でない状態も呼出前に明示拒否するよう補強した。Inline DispatcherもdispatchごとのInvoker生成をやめ、Deferred Workerと同様にConstructorから既定Invokerを注入・保持する。

Manifest LoadはTyped SignatureとManifest Valueの不一致、改変されたContext Mode、Separate HandlerのLegacy Interface不一致を拒否する。ReflectionはBuildとManifest Validationに限定し、Inline／Worker RuntimeでSource DiscoveryまたはSignature推論を行わない。

## Quickstart／Mago Evidence

Welcomeは`handle(WelcomeValue): OperationResult`、Reportは`handle(GenerateReportValue, ExecutionContext): OperationResult`へ移行した。`OperationHandler`、`OperationEnvelope`、Generic DocBlock、Value Narrowing Guardを削除した。ReportはDeferred Attemptがnullでないことだけを業務前提として確認する。

Quickstart ValueのCredential Parameterには既存Framework `#[Sensitive]`に加えてNative `#[SensitiveParameter]`を付与し、通常Mago Sourceへ`examples/quickstart/app`を追加した。Root AnalysisとQuickstart直接AnalysisはいずれもIssueなしで成功した。

## Changed Files

- Core Metadata: `src/Core/Registry/OperationMetadata.php`
- Registry: `src/Internal/Registry/OperationMetadataCompiler.php`、`OperationHandlerMetadataCompiler.php`、`TypedSelfHandledSignatureValidator.php`、`TypedSelfHandledParameterValidator.php`、`OperationManifestMetadataCodec.php`、`OperationManifestHandlerDecoder.php`、`OperationDefinitionFactory.php`
- Runtime: `src/Internal/Execution/HandlerInvoker.php`、`HandlerResolver.php`、`InlineDispatcher.php`、`DeferredWorkerRuntime.php`
- Quickstart: Welcome／Report OperationとValue、`examples/quickstart/README.md`、`mago.toml`
- Tests: Registry、Execution、DependencyInjection、Runtime、Quickstart Architecture
- Docs: Application／MVP／Runtime Guide、Bootstrap／Registry／Container／Worker Internals
- Management: `develop/TODO.md`、Task Packet、Report、`develop/STATE.md`

ユーザー所有の未追跡`LT-blackops-framework.md`は変更対象外であり、本Taskの差分に含めない。

## Decisions and Assumptions

- Existing Manifest FieldとSchema Versionを維持し、Optional Bool Flagだけを追加した。Flag欠落時は現在のClass Signatureから復元し、Flag存在時は一致を必須とした。
- Inline HandlerへはAttempt Start後のEnvelopeではなく受信時Contextを渡し、Operation IDを維持しつつAttemptをnullにした。Legacy Handlerは従来のAttempt Envelopeを受け取る。Inline／Deferredとも共通InvokerをConstructor Dependencyとして保持する。
- Typed Separate Handlerは実装せず、Separate Handlerは引き続き`OperationHandler`を必須とした。
- Runtime InvokerはCompiled Metadataだけを使用し、ReflectionとSource Discoveryを行わない。

## Commands and Results

```text
docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app composer validate --strict examples/quickstart/composer.json
Result: examples/quickstart/composer.json is valid.

docker compose run --rm app mago format --check src tests examples
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app mago analyze examples/quickstart/app
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit tests/Internal/Registry tests/Internal/Execution tests/Internal/DependencyInjection tests/Internal/Runtime tests/Internal/Application/ApplicationConsoleKernelTest.php tests/Internal/Console tests/Http tests/Integration tests/Architecture/QuickstartApplicationArchitectureTest.php
Result: OK (245 tests, 871 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/phpunit
Result: OK (693 tests, 2299 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: 355 files / Violations 0 / Skipped 0 / Uncovered 0 / Allowed 1508 / Warnings 0 / Errors 0.

bash tests/Consumer/quickstart-e2e.sh
Result: Quickstart consumer E2E passed. Framework mirror install, build, migration, HTTP, worker retry, outcome, and cleanup succeeded.

Quickstart Legacy Interface／Envelope／Generic／Value Guard
Quickstart Internal Import Guard
Production/Test Management ID Guard
Result: No matches; all negated commands exited 0.

git diff --check
Result: No output.
```

## Acceptance Criteria

Task Packetの14項目をすべて満たした。Value-only／Value＋Context CompileとInvocation、Inline null Attempt、Deferred Attempt、Invalid SignatureとInstantiable検証、Manifest／Runtime Mode Defense、Legacy互換、Container Autowire／Explicit Binding、Quickstart移行とConsumer E2E、Mago、Focused／Full／Deptrac／Composer／Guard、Docs／Report／Checkpointを完了した。Orchestrator Review後の防御補強を含む最終Focused `245 tests / 871 assertions`、Full `693 tests / 2299 assertions`、Deptrac `Allowed 1508 / Violations 0 / Errors 0`、Consumer E2E、Mago、全Guardが成功した。Orchestrator Reviewを通過し、P8-002Aを受け入れた。

## Remaining Issues

P8-002AのBlockerはない。Typed Separate HandlerとPublic `OperationHandler`の削除／非推奨化はScope外である。P8-003 Distribution PublicationはD073のRepository、Credential、Packagist境界回答待ちである。

## Suggested Next Action

D073のRepository、Credential、Packagist境界回答後にP8-003へ進む。
