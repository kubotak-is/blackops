# Phase 13 Delivery Plan

## Goal

ApplicationがNamed Doctrine DBAL ConnectionをConstructor Injectionで利用し、Operation、Command Service、Application Serviceへ明示Transactionを適用できるRuntimeを提供する。Transaction、After Commit、Journal／Outcome、Long-running Processの保証差を隠さず、Build時に検証する。

ORM、Repository基底Class、Query Builder Wrapper、Transactional OutboxはPhase 13へ含めない。

## P13-001: Database Configuration and DI Foundation

- `config/database.php`をCanonical Named形式へ正規化する
- Legacyの単一`connection`／`schema`を互換Shorthandとして維持する
- Public `DatabaseManager`とDefault DBAL ConnectionのConstructor Injectionを追加する
- Connectionを名前ごとにLazy生成し、Unknown NameをSecret非露出で拒否する
- Framework Store ConnectionとApplication Default Connectionを同じManagerから解決する
- Runtime ServiceをSynthetic ServiceとしてCompiled Containerへ注入する

## P13-002: Build-time AOP Foundation

- `ray/aop`をRuntime Dependencyへ追加する
- Symfony DIのBuild時CompileへRay.Aop Proxy生成を統合する
- Production RuntimeのTemporary Proxy生成とSource Scanを禁止する
- Public `#[Transactional]`／`#[AfterCommit]` Attributeを追加する
- Class／Method Attribute、Connection Name、Public／非`final`、`void`等のSignatureをBuild時検証する
- Direct `new`とContainer管理Instanceの境界をTestとGuideへ固定する

## P13-003: Generic Transaction and After Commit Scope

- Named ConnectionごとのRequired Transaction Scopeを実装する
- 同一ConnectionのNested ScopeとRollback-onlyを実装する
- 異なるConnectionの独立Scopeと非原子性を明示する
- 開始済みManual Transactionとの混在をFail-fastする
- 一般Service MethodへTransactional Interceptorを接続する
- After Commit Invocation Queue、Rollback破棄、Transaction外即時実行を実装する
- Callback Failure Reporterと相関Logを実装し、自動Retryしない

## P13-004: Operation Transaction Lifecycle

- Operation Definition／`handle()`のTransactional MetadataをManifestへ保存する
- Authorization後にInline／Deferred共通の固定Transaction Stageを開始する
- 同一Connectionで業務更新と成功Terminal Journal／Outcomeを一つのCommitへ含める
- Rejected／Throwable時はRollback後に既存Lifecycleへ記録する
- Transactional Commandだけを呼ぶOperationとの保証差をTestする
- Fencing、Retry、Dead LetterでCommit済み業務更新を誤って再実行しない境界を検証する

## P13-005: Long-running Connection Safety

- Named ConnectionをRequest／Attempt開始時にHealth Checkする
- 正常終了時のTransaction Leakを検査してConnectionを再利用する
- Throwable、Rollback失敗、Leak、Health Check失敗時にCloseし、次回利用時に再接続する
- HTTP Worker ModeとDeferred WorkerのApplication Connection Scopeを同期する
- Heartbeat ConnectionをApplication Serviceへ公開しない
- PostgreSQL停止／復旧、複数Request／Attempt、失敗後継続をConsumer E2Eで検証する

## P13-006: Consumer Experience and Closeout

- QuickstartへRepository、Transactional Command、Operation、After Commitの動作例を追加する
- Named Connection、Default DI、OperationとCommandの保証差をGuide／Referenceへ同期する
- After CommitがBest-effortで、Reliable DeliveryはOutboxであることを明示する
- Framework UpdateとSkeleton Create-project Consumer Testを更新する
- Full PHP／Consumer／Website Quality Gateを実行する
- TODO、Report、STATEを同期してPhase 13をCloseする

Documentation WebsiteのCloudflare公開は実行せず、Repository内Source、Build、Search、Artifact Guardだけを維持する。

## Dependency Order

```text
P13-001 Database Configuration and DI Foundation
  -> P13-002 Build-time AOP Foundation
    -> P13-003 Generic Transaction and After Commit Scope
      -> P13-004 Operation Transaction Lifecycle
        -> P13-005 Long-running Connection Safety
          -> P13-006 Consumer Experience and Closeout
```

## Phase Acceptance Criteria

- [x] Canonical Named Database ConfigとLegacy Shorthandが同じ内部Modelへ正規化される
- [x] DatabaseManagerとDefault DBAL ConnectionをApplication ServiceへConstructor Injectionできる
- [x] CredentialをBuild Artifact、Error、Logへ保存しない
- [x] Ray.Aop ProxyをBuild時に生成し、Production RuntimeでSource Scanしない
- [x] `#[Transactional]`をOperationと一般Serviceへ適用でき、無効な対象をBuildで拒否する
- [x] Nested Required、Rollback-only、Manual Transaction、複数Connectionの境界がTestされる
- [x] `#[AfterCommit]`がCommit後に実行され、Rollback時に破棄され、失敗で自動Retryしない
- [x] 同一ConnectionのTransactional Operationが業務更新とTerminal Journal／Outcomeを原子的にCommitする
- [x] Transactional Commandだけを使うOperationと別Connectionの保証差が公開される
- [x] HTTP／Deferred Long-running ProcessがConnectionを安全に再利用／破棄／再接続する
- [x] Skeleton、Quickstart、Guide、Consumer E2EがPublic Contractを再現する
- [x] Full PHP／Consumer／Website Quality Gateが成功する

## Traceability

- Decision: [D096 Phase 13 Database and Transaction Runtime](../decisions/096-phase-13-database-and-transaction-runtime.md)
- Runtime and DI: [PHP Runtime and Dependency Injection](09-runtime-and-di.md)
- Transaction Contract: [Durable Journal and Transactions](11-durable-journal-and-transactions.md)
- PostgreSQL Boundaries: [PostgreSQL Transaction Boundaries](36-postgresql-transaction-boundaries.md)
- Roadmap: [Post Phase 10 Roadmap](60-post-phase-10-roadmap.md)
