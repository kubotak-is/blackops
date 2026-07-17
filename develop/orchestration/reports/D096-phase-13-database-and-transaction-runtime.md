# D096: Phase 13 Database and Transaction Runtime Report

Status: Accepted

## Summary

Phase 13の既存Database Configuration、Symfony DI Build／Runtime境界、HTTP／Deferred Connection Lifecycle、Transaction仕様を監査し、Named Connection、DatabaseManager、AOP型Transaction、Operation Transaction、Nested／Manual Transaction、After Commit、Long-running Connection、Outbox ScopeをD096で確定した。

Production CodeとDependencyは変更していない。確定内容をRuntime／Transaction／PostgreSQL仕様、Roadmap、TODO、Phase 13 Delivery Plan、P13-001 Task Packetへ反映した。

## Current Runtime Audit

- Current Configは単一`connection`／`schema`だけを受理する
- HTTP、Worker、Migration、Retentionが個別にDBAL Connectionを生成する
- Compiled ContainerへRuntime Connectionを設定するSynthetic Service境界がない
- Application RepositoryへDatabaseManagerまたはDefault ConnectionをAutowireできない
- Deferred Workerは通常Handler実行中にFramework Store Transactionを保持しない
- Current HTTP Connection Lifecycleは一つのFramework ConnectionだけをHealth Check／Closeする
- Operation／ServiceのTransaction AttributeとAfter Commit Scopeは存在しない
- Generic Operation MiddlewareはD095で不採用である

## Dependency Investigation

Ray.Aop 2.19.1はPHP 8.2以上、PHP 8 Attribute、Public Method Interceptor、`readonly` Classをサポートし、Ray.Diを必須としない。生成Proxyは対象Classを継承してMethodをOverrideするため、`final class`／`final method`をInterceptできない。標準APIはTemporary DirectoryへRuntime生成するため、BlackOpsではBuild時Symfony DI Compilerへ統合する必要がある。

Doctrine DBALのNested Transactionは単一の実Transactionを共有し、Inner RollbackがOuterをRollback-onlyにするRequired Semanticsを構成できる。Savepointは異なる業務Semanticsのため暗黙Defaultにしない。

## User Decisions

UserはD096 Question 1から10ですべてAを選択した。Question 3／4の初回回答で、Operation専用ではないCommand／Application Service Transactionと、Laravel After Commitに相当するMethod Invocation Queueを追加要件とした。

## Changed Files

- `develop/decisions/016-durable-journal-transaction.md`
- `develop/decisions/096-phase-13-database-and-transaction-runtime.md`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/11-durable-journal-and-transactions.md`
- `develop/spec/36-postgresql-transaction-boundaries.md`
- `develop/spec/43-installed-application-layout-and-bootstrap.md`
- `develop/spec/47-public-http-runtime-configuration.md`
- `develop/spec/48-public-console-kernel-composition.md`
- `develop/spec/55-project-generators-and-application-migrations.md`
- `develop/spec/60-post-phase-10-roadmap.md`
- `develop/spec/64-phase-13-delivery-plan.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P13-001-database-configuration-and-di-foundation.md`
- `develop/orchestration/reports/D096-phase-13-database-and-transaction-runtime.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Ray.Diへ移行せずSymfony DIを正本として維持する。
- Ray.Aop ProxyはBuild Artifactとし、Production Runtimeで生成しない。
- Operation Transactionと一般Service Transactionは同じAttributeを使うが、Terminal Journal／OutcomeとのCommit保証は異なる。
- After Commitは同期Best-effortであり、Durable Deliveryではない。
- `Unit of Work`という語をORMのIdentity Map／Change Trackingには使わず、Transaction Scope Callback Queueとして説明する。
- ORMとTransactional OutboxはPhase 13へ含めない。

## Commands and Results

| Command | Result |
| --- | --- |
| Source of Truth、D013／D016／D057／D085／D095、Spec 09／11／36／60 read | Current Contractと衝突を確認 |
| Current Config、DI Compiler、HTTP／Worker／Migration／Retention Runtime read | Named ConnectionとSynthetic ServiceのGapを確認 |
| Ray.Aop公式Repository／Source／Test／composer.json read | Version、Dependency、Build方式、readonly／final制約を確認 |
| Doctrine DBAL公式Transaction Documentation read | Required／Rollback-only／Savepoint境界を確認 |
| `git diff --check` | Success |

## Acceptance Criteria

- [x] Named Connection ConfigとLegacy互換を決定した
- [x] DatabaseManagerとDefault Connection DIを決定した
- [x] Ray.Aopの採用とBuild-time Symfony DI統合を決定した
- [x] Operationと一般ServiceのTransaction保証差を決定した
- [x] Nested Required、Manual Transaction、複数Connection境界を決定した
- [x] After Commit Invocation／Failure／Durability Contractを決定した
- [x] Long-running Connection Lifecycleを決定した
- [x] OutboxをPhase 17へ維持した
- [x] Specification、Delivery Plan、TODO、Task Packet、STATEを同期した

## Remaining Issues

実装は未着手である。Ray.Aop Integrationの具体Proxy Artifact FormatはP13-002で、Operation成功TransactionへのTerminal Store参加はP13-004で、Connection RecoveryはP13-005で実装・検証する。

## Suggested Next Action

P13-001でNamed Database Configuration、DatabaseManager、Default Connection DI、Synthetic Runtime Serviceを実装する。
