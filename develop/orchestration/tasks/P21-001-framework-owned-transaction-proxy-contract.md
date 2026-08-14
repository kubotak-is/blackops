# P21-001: Framework-owned Transaction Proxy Contract

Status: Accepted

## Goal

Ray.Aopを置き換える前に、`#[Transactional]`／`#[AfterCommit]`専用のFramework-owned Build-time Proxyについて、PHP Signature Matrix、Generated Artifact、Symfony DI統合、Migration、Compatibility Test、Ray.Aop Removal Gateを一つのDecisionへ確定する。

汎用AOP Engineを作らず、現在のOperation LifecycleとDI管理ServiceのTransaction Developer Experienceを維持できる実装可能な境界へ分割する。

## Source of Truth

- `AGENTS.md`
- `develop/decisions/096-phase-13-database-and-transaction-runtime.md`
- `develop/decisions/108-ray-aop-upstream-and-phase-order.md`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/11-durable-journal-and-transactions.md`
- `develop/spec/60-post-phase-10-roadmap.md`
- `develop/spec/64-phase-13-delivery-plan.md`
- `develop/spec/82-operation-dispatch-and-deferred-authoring.md`
- `develop/orchestration/tasks/P17-007A-aop-class-constant-attributes.md`
- `develop/orchestration/reports/P17-007A-aop-class-constant-attributes.md`
- Current `src/Internal/Aop/**`、Symfony DI compiler、Transaction Runtime、Composer dependency Source and Tests

## In Scope

- Current Ray.Aop composition、generated proxy、runtime interceptor、Symfony DI registrationのRead-only audit
- Operation-owned Transaction Lifecycleと任意DI管理Service interceptionの責任分離
- PHP Class／Method／Parameter／Return Signature Matrix
- `final`、`readonly`、abstract、trait、inheritance、visibility、static、generator、reference、variadic、union／intersection／DNF、default value、constant expressionの対応境界
- Class-level／method-level `#[Transactional]` precedenceと`#[AfterCommit]`の専用semantics
- Deterministic Generated Artifact path、Build ID、autoload、atomic replace、stale cleanup、source drift、OPcache境界
- Symfony DI Definitionのclass replacement、alias、visibility、shared scope、tags、method calls、factory／synthetic／lazy service境界
- Build-time failure、runtime fallback禁止、safe diagnosticのContract
- Existing Application migration、compatibility period、generated artifact invalidation
- Composer／lock／namespace／testからRay.Aopを削除できるRemoval Gate
- D137 Question、Recommendation、User回答
- User回答を反映したSpecification 101、Delivery Plan 102、P21-002〜P21-007 Production Task Packet分割
- TODO／STATE／Decision Index／Report同期

## Out of Scope

- Production Code、Test、Fixture、Dependency、Composer lockの変更
- Framework-owned Proxy Generatorの実装
- Ray.Aopの削除またはVersion更新
- Transaction Runtime／Attribute Public APIの変更
- General-purpose AOP、arbitrary interceptor、runtime source scan
- Public Guide／Website変更
- External Issue／PR、Push、Deploy

## Files Allowed to Change

- `develop/decisions/137-framework-owned-transaction-proxy.md`
- `develop/spec/README.md`
- `develop/spec/60-post-phase-10-roadmap.md`
- `develop/spec/101-framework-owned-transaction-proxy.md`
- `develop/spec/102-phase-21-delivery-plan.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P21-001-framework-owned-transaction-proxy-contract.md`
- Phase 21 Production Task Packets P21-002〜P21-007 created after User回答

Production Source／Test／Dependencyまたは上記以外の変更が必要なら実装を広げず、Reportへ記録する。

## Audit Questions

1. 現在のRay.Aop Proxyが受け付けるClass／Method Signatureと、PHP Native Subclassで安全に再現できる集合は何か。
2. 対応不能SignatureをBuild Errorにするか、明示的な非intercept対象にするか。
3. Original Service DefinitionをGenerated Subclassへどう置き換え、alias／visibility／shared scope／tag／callをどう保存するか。
4. Operation自身の`#[Transactional]`をFramework Lifecycleだけで処理し、Service Proxyと二重Interceptしない境界は何か。
5. Generated Artifactをどの入力Hash／Build IDで識別し、atomic replace／stale cleanup／drift detectionするか。
6. Ray.Aop互換期間に同じServiceを二重Proxyせず、Application migrationをどう検証するか。
7. Composer dependency、Ray namespace、proxy artifacts、compatibility fixturesを削除できるRemoval Gateは何か。
8. Phase 21をどのProduction Task順へ分割し、各段階で旧Ray pathへ安全に戻せるか。

## Acceptance Criteria

- [x] Current Ray.Aop／Symfony DI／Transaction RuntimeのcompositionをSourceとTestsで確認する
- [x] PHP Signature Matrixをsupport／reject／not-applicableへ分類する
- [x] Generated Artifact／autoload／cleanup／drift contractを示す
- [x] Symfony DI Definition preservation matrixを示す
- [x] Operation LifecycleとService Proxyの二重Intercept防止を示す
- [x] Migration／Compatibility／Ray.Aop Removal Gateを示す
- [x] D096／D108と既存Public Attribute semanticsを維持する
- [x] D137へQuestion、Options、Recommendation、User回答欄を作成する
- [x] User回答（Questions 1〜7すべてA）を反映してD137をDecidedにする
- [x] Specification 101／Delivery Plan 102／Production Task Packetへ分割する
- [x] Production Code／Test／Dependencyを変更しない
- [x] STATE／TODO／Decision Index／Reportを同期し、WorkerはCommitしない

## Required Commands

```bash
rg -n "Ray\\\\Aop|Ray.Aop|Aop|Transactional|AfterCommit|Proxy|Definition|ContainerBuilder" src tests composer.json composer.lock develop/spec develop/decisions
git diff --check
git status --short
```

Production Code／Testを変更しないDecision／Task planning段階では既存Suiteを再実行しない。確定Specification 101／102でSignature、DI Definition、Artifact、Migration、RemovalのTest Matrixを定義した。

## Completion Report

`develop/orchestration/reports/P21-001-framework-owned-transaction-proxy-contract.md`へSummary、Evidence Inventory、Signature Matrix、DI／Artifact／Migration／Removal Boundaries、Decision Questions、Commands and Results、Remaining Issues、Suggested Next Actionを記録する。
