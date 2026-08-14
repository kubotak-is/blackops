# P21-001 Completion Report: Framework-owned Transaction Proxy Contract

Status: Accepted

## Summary

P21-001のread-only auditを完了し、User回答（Questions 1〜7すべてA）を反映してDecision／Specification／Delivery Planへ確定した。Current Ray.Aop 2.20.0 composition、Symfony DI Definition mutation、Operation Transaction Lifecycle、PHP signature guards、artifact/build behavior、migration and removal risksをSource／Tests／vendor contractへ照合し、[D137](../../decisions/137-framework-owned-transaction-proxy.md)へQuestion、Options、Recommendation、明示的な`[ANSWER]`欄を記録した。

UserはD137 Questions 1〜7をすべてAと回答した。D137をDecidedへ更新し、Specification 101、Phase 21 Delivery Plan 102、P21-002〜P21-007 Production Task Packetを作成した。Production Code、Test、Fixture、`composer.json`、`composer.lock`、Public docsは変更していない。WorkerはCommit／Push／Deployしていない。Orchestrator independent reviewを完了し、P21-001をAcceptedとした。

## Changed Files

- `develop/decisions/137-framework-owned-transaction-proxy.md`（Decision確定、監査Options／回答履歴を保持）
- `develop/spec/README.md`（D137／Specification 101／102 index同期）
- `develop/spec/60-post-phase-10-roadmap.md`（Phase 21 dependency order／Ray retention同期）
- `develop/orchestration/tasks/P21-001-framework-owned-transaction-proxy-contract.md`（StatusをReview Pendingへ更新）
- `develop/TODO.md`（Phase 21 contract完了、P21-002〜P21-007依存順同期）
- `develop/STATE.md`（下記Checkpointを追加）
- `develop/orchestration/reports/P21-001-framework-owned-transaction-proxy-contract.md`（本Report）
- `develop/spec/101-framework-owned-transaction-proxy.md`（確定normative contract）
- `develop/spec/102-phase-21-delivery-plan.md`（確定delivery order）
- `develop/orchestration/tasks/P21-002-framework-proxy-contract-guard.md`
- `develop/orchestration/tasks/P21-003-framework-proxy-generator-artifacts.md`
- `develop/orchestration/tasks/P21-004-framework-proxy-symfony-di-preservation.md`
- `develop/orchestration/tasks/P21-005-framework-proxy-runtime-ownership.md`
- `develop/orchestration/tasks/P21-006-framework-proxy-compatibility-migration.md`
- `develop/orchestration/tasks/P21-007-framework-proxy-ray-removal-closeout.md`

## Evidence Inventory

| Contract | Source／Test evidence | Result |
| --- | --- | --- |
| Ray.Aop composition | `src/Internal/Aop/RuntimeAopCompiler.php:18-50`; `AopServiceDefinitionCompiler.php:20-61`; `tests/Internal/Aop/RuntimeAopCompilerTest.php` | Build-time `ContainerBuilder` definition scan、Subclass generation、Definition class replacement、runtime binding registrationを確認。Plain serviceは untouched、Direct `new`は非intercept、Operation bindingはpass-through、readonly／AfterCommit／failure／stale cleanupを既存Testで確認。 |
| Attribute／signature | `AopBindingFactory.php`; `AopMethodBindingFactory.php`; `AopClassValidator.php`; `AopMethodValidator.php`; `tests/Internal/Aop/RuntimeAopCompilerTest.php:151-210` | Class／Method precedence、known connection、final/private/static/constructor/destructor rejection、AfterCommit void／reference／generator guardを確認。Class-level Transactionalのfinal methodが静かにskipされる差をD137でBuild Error候補として指摘。 |
| Symfony DI | `AopServiceDefinitionCompiler.php:58-59`; `AopRuntimeBindingRegistrar.php`; `vendor/symfony/dependency-injection/Definition.php`; `tests/Internal/DependencyInjection/RuntimeContainerDumperTest.php` | Same Definition objectへの`setClass`／`addMethodCall`は既存arguments／visibility／shared／tags／calls等を保持する構造。Alias、factory、lazy、synthetic、abstract、decorationのproxy互換性を裏付ける回帰Testは未実装。 |
| Operation lifecycle | `OperationMetadataCompiler.php:194-227`; `InlineDispatcher.php:545-575`; `DeferredWorkerRuntime.php:170-215`; `HandlerInvoker.php:22-44` | Operation Transactionはmetadata＋固定Lifecycleで一回だけ所有。Service proxyが同じOperationへTransactional interceptorを重ねない設計が必要。 |
| Artifact／build | `AopArtifactDirectory.php:13-66`; `vendor/ray/aop/src/AopPostfixClassName.php`; `vendor/ray/aop/src/Compiler.php`; `RuntimeContainerDumper.php:72-89`; `BuildFingerprint.php:14-42`; `BuildArtifactFreshnessChecker.php:27-50` | AOP dirは事前全削除、Ray proxyは直接write／mtime-binding CRC、Containerだけtmp＋rename。既存fingerprintはmtime／sizeでAOP source content／generator／Build IDを識別しない。 |
| Dependency | `composer.json:28`; `composer.lock:1276-1334` | `ray/aop` `^2.19`、lock resolved 2.20.0、`ext-tokenizer` requirement。Removal gate未定義。 |

## PHP Signature Matrix

D137は次をsupport／reject／not-applicableへ分類した。

- Support候補: instantiable non-final concrete class、readonly class、inherited public non-final instance method、variadic、union／intersection／DNF、nullable／never／mixed／static／self／parent、scalar／array／constant default、無関係なPHP Attribute（いずれもLSP／PHP 8.5 fixture必須）。
- Reject候補: final class、abstract/interface/trait target、public final method、protected/private、constructor/destructor、static、generator、reference return／reference parameter、AfterCommit non-void、同一MethodのTransactional＋AfterCommit、property／parameter／repeated Attribute。Generator／referenceは初期Framework-owned releaseでは両Attributeとも拒否し、後続Decisionでfixture証拠を確認して拡張する。
- Not applicable: synthetic Definition、非具象テンプレート、DI管理外のDirect `new`／Static invocation。既存ContractどおりRuntime Scan／implicit interceptionへfallbackしない。

## Symfony DI Definition Preservation

D137のMatrixは、arguments／autowiring／bindings／properties、public/private、shared、tags／autoconfigured／instanceof、method calls／configurator、alias graph、file／deprecationをsupport（同一ID／aliasと値を保持）とし、lazy／factory／decorationは初期Releaseでreject、syntheticはnot-applicable、abstractはrejectとした。未対応Feature付きAttributeはBuild Errorにし、unproxied fallbackを許可しない。

## Generated Artifact / Build ID / Drift

Current evidenceはAOP artifactの事前削除・直接書込み・mtime／binding CRCであり、Application Build ID、content hash、generator version、atomic proxy publish、post-success stale cleanup、source drift verificationを提供しない。D137は次をRecommendationとした。

- Build ID、generator／PHP version、source／signature／proxy file hashをmanifestへ記録
- content-hash inputsとstaged generation、parse／class／Definition verification後のatomic publish
- 失敗時はactive last-known-goodを残し、成功後にmanifest比較でstale cleanup
- Runtime loaderはmanifest／Build ID／hashだけを検証し、Source Scan／Temporary Proxy generationを行わない
- Artifact path／Build ID変更でOPcache stale classを選ばない。Diagnosticsはstable code／service ID／source class／Build IDだけ

## Operation Lifecycle / Double Intercept

`OperationMetadataCompiler`がDefinition／`handle()` Attributeをmetadataへ取り込み、Inline／Deferred runtimeがAuthorization後にTransactionを開始し、shared ConnectionならTerminal／Outcomeまで同一Commitへ含める。`AopMethodBindingFactory`がOperationへ`FoundationMethodInterceptor`を使う既存境界はこの保証を維持するための重要なevidenceである。Framework-owned generatorでも、Operation source／metadata ownershipを先に判定し、Operation Transactionalはpass-through、一般Serviceだけをproxy intercept対象とする。Ray／Framework proxyは一つのDefinitionへ同時に適用せず、marker／manifest不整合はBuild Errorとする。

## Migration / Compatibility / Removal Boundary

Migrationはbuild profileで`ray`または`framework`を一方だけ選ぶ方式を推奨する。各fixtureは両modeでTransaction／AfterCommit／Signature／DI／Operation terminal／Failure diagnosticを比較し、rollbackはmatching Container＋manifestを含むprevious complete buildの切替とする。Removal gateはSignature／DI／Lifecycle／Artifact／Migration／Consumer package-export／clean install／namespace scanを全PASSし、別Production TaskでSource／Fixture／Composerを削除して初めて成立する。

## Decision Questions and Recommendation

D137には以下7問を記録し、Userは全問Aを選択した。Recommendationと回答をD137へ反映し、(1) Transaction／AfterCommit専用Framework-owned Build-time Proxy、(2) unsupported AttributeはBuild Error、(3) Operation Lifecycle owner分離と同一Methodの二属性拒否、(4) DIの安全な保存とfactory／lazy等の初期拒否、(5) content-hashed staged atomic artifact、(6) Ray／Frameworkのmutually-exclusive compatibility profile、(7) 全ゲート後のRay removalを確定した。

## Commands and Results

Required commands executed from repository root (final Decision/Delivery sync):

```text
rg -n "Ray\\\\Aop|Ray.Aop|Aop|Transactional|AfterCommit|Proxy|Definition|ContainerBuilder" src tests composer.json composer.lock develop/spec develop/decisions
PASS (exit 0; 1,063 matching lines written to a temporary diagnostic file; no secret values copied to this Report)

git diff --check
PASS (exit 0)

git status --short
PASS (read-only status; only allowed Decision/Task/Report/STATE/TODO/index documentation changes are present)

docker compose run --rm app mago format --check src tests
PASS (`INFO All files are already formatted.`)

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
PASS (no management-ID comments found)
```

Per Task Packet, existing PHPUnit suites were not rerun because Production Code／Test remain unchanged and this turn only finalized Decision/Specification/Task planning artifacts. The read-only Mago format and management-ID checks above were run as the AGENTS minimum quality checks.

## Acceptance Criteria

- [x] Current Ray.Aop／Symfony DI／Transaction Runtime composition confirmed from Source and Tests.
- [x] PHP Signature Matrix classified support／reject／not-applicable in D137 and this Report.
- [x] Generated Artifact／autoload／cleanup／drift contract documented.
- [x] Symfony DI Definition preservation matrix documented.
- [x] Operation Lifecycle／Service Proxy double-intercept boundary documented.
- [x] Migration／Compatibility／Ray.Aop Removal Gate documented.
- [x] D096／D108 and existing Attribute semantics carried forward.
- [x] D137 Questions、Options、Recommendations、`[ANSWER]` fields created.
- [x] User answers (all seven A) reflected and D137 changed to Decided.
- [x] Specification 101／Delivery Plan 102／Production Task Packets P21-002〜P21-007 created.
- [x] Production Code／Test／Dependency unchanged.
- [x] STATE／TODO／Decision Index／Task／Report synchronized; Worker did not commit.

## Remaining Issues

1. Production implementation evidence does not yet exist for the generator, content-hash manifest, atomic artifact publish, Definition preservation fixtures, or Ray/framework compatibility mode. These are P21-002〜P21-006 scope.
2. Existing Ray path intentionally remains in `composer.json`／`composer.lock` and Source until P21-007 removal acceptance.

## Orchestrator Acceptance

Accepted at `2026-08-09T22:49:56+09:00`. The Orchestrator independently confirmed all seven D137 answers are Option A, D137 and Specifications 101／102 are Decided, P21-002 is the only Ready production packet, and P21-003〜P21-007 remain dependency-gated Planned packets. Two review rounds corrected the write boundary around legacy Ray validators, made the Application-aware profile selector and manifest-aware RuntimeContainerDumper integration explicit, excluded the non-AOP legacy standalone command, and isolated the P21-007 Composer clean-install gate from the main worktree.

Final independent evidence passed: required source/spec inventory (`1,064` matching lines), `git diff --check`, the management-ID guard, and `docker compose run --rm app mago format --check src tests`. `git status --short` showed only the P21-001 allowed Decision／Specification／Task／Report／STATE／TODO／roadmap files; Production Source, Tests, Composer files, and Public docs remained unchanged. Existing PHPUnit suites were intentionally not rerun because this Task changed no executable code.

## Contract Ambiguity

No unresolved contract choice remains after all seven User answers selected A. Implementation details remain intentionally task-scoped: P21-002 owns the new FrameworkProxyContract seam while legacy Ray validators stay read-only; P21-003 consumes that seam without duplicating ownership; P21-006 owns central Application-aware `build:compile --proxy-profile=ray|framework` wiring (default `ray`), manifest-aware RuntimeContainerDumper, Application help evidence, and migration docs after P21-004／P21-005 seams; the standalone legacy `blackops:build:compile` command is outside this surface; P21-007 consumes the exact P21-006 removal manifest and performs clean-install only through an isolated Consumer script.

## Suggested Next Action

Commit the accepted P21-001 contract artifacts, confirm a clean working tree, then start P21-002. Keep P21-003〜P21-007 Planned behind their dependencies and retain Ray until P21-007 is independently accepted.

Final boundary correction checks: initial Docker Mago invocation was blocked by sandbox Docker socket permission; the same read-only command was rerun with approved Docker access and passed (`INFO All files are already formatted`). Management-ID guard and `git diff --check` both passed. No Composer install was run in the main worktree.
