# P20-000: Deferred Authoring and Operation Dispatch

Status: Accepted

## Goal

CanonicalなDeferred記法を`#[Deferred]`へ移行し、ApplicationがDI依存を持つchild Operationを構築せず、`Operations::dispatch(OperationClass::class, OperationValue)`でTransactional Outboxへ登録できるPublic APIを実装する。

## Source of Truth

- `develop/decisions/115-deferred-authoring-and-operation-dispatch.md`
- `develop/spec/03-execution.md`
- `develop/spec/11-durable-journal-and-transactions.md`
- `develop/spec/50-operation-authoring-and-build-discovery.md`
- `develop/spec/74-application-ergonomics.md`
- `develop/spec/80-reliability-and-delivery.md`
- `develop/spec/82-operation-dispatch-and-deferred-authoring.md`
- `develop/decisions/108-ray-aop-upstream-and-phase-order.md`
- `develop/decisions/109-phase-18-idempotency-and-outbox.md`

## In Scope

- Public `BlackOps\Core\Attribute\Deferred` marker Attribute
- `OperationMetadataCompiler`のDeferred正規化、重複／競合Attribute Fail-fast
- Existing `ExecuteWith` compatibility
- Public `BlackOps\Execution\Operations` transactional child dispatch interface
- Outbox Record IDを露出しないPublic Dispatch Receipt
- Existing `TransactionalOutboxRuntime`と同じPersistence／Context／Transaction保証を再利用するInternal実装
- Application HTTP／Worker／Operation Console／Command Container compositionへのDI Binding
- Quickstart、Community Board、Generator Fixture、permanent testsの`#[Deferred]`移行
- Community Board AddCommentから`Operations::dispatch(NotifyPostOwner::class, ...)`を使用
- Unit／Integration／PostgreSQL／Consumer／Website regression
- Guide、Internal Reference、Core API、Spec／TODO／Report／STATE同期

## Out of Scope

- `ScheduledBy`実装
- Cron／Timezone／Misfire／Overlap設計
- Framework Maintenance Scheduler変更
- Inline dispatchとDeferred dispatchを統合する汎用Bus
- 任意PHP ContextからのDirect Deferred Acceptance
- Existing `Dispatcher`のSignature／Semantics変更
- `ExecuteWith`削除
- Manifest Schema、Journal Strategy Identity、Transport Payload、Migration変更
- Ray.Aop、Transactional／AfterCommit Proxy、Vendor Dependency変更
- External Publication／Deploy、Stable Release、Tag

## Required Contract

### Deferred Attribute

- `#[Deferred]`だけでMetadata Strategyが`BlackOps\Core\Execution\Deferred::class`になる。
- AttributeなしはInlineのまま。
- Existing `#[ExecuteWith(Deferred::class)]`とliteral class-stringは互換維持。
- `#[Deferred]`と`#[ExecuteWith(...)]`の併置はBuild Error。
- Attribute自体は引数を受け取らずOperation ClassだけをTargetにする。
- Transactional Operationで`#[Deferred]`とtyped `#[Authorize(...::class)]`を併置してもRay.Aop compileが壊れない。

### Operations Dispatch

利用者コードは次の形にする。

```php
$this->operations->dispatch(
    NotifyPostOwner::class,
    new NotifyPostOwnerValue(...),
);
```

- Definition class-stringが未登録なら安全なprogramming error。
- Value型がMetadataと違えば登録前に拒否。
- Inline Operationは登録前に拒否。
- active Operation Contextがなければ拒否。
- Framework-owned root Transactionでなければ拒否。
- Existing Transactional Outboxと同じConnection／Rollback／Context／Identity保証。
- 呼出側はOperation Definitionを`new`しない。
- Public Receiptはchild Operation IDとUTC dispatch時刻だけを公開する。
- Existing `TransactionalOutbox` APIは動作を維持する。

### Consumer

- Community Board `AddComment`は`Operations`をConstructor Injectionする。
- `NotifyPostOwner`はRoute／Consoleなしの`#[Deferred]`内部Operationとして残す。
- Comment Transaction Rollback時にNotification childとOutbox Rowが残らない。
- Relay／Worker／duplicate delivery／source deletion／recipient authorizationの既存Journeyを維持する。

## Files Allowed to Change

- `src/Core/Attribute/**`
- `src/Execution/**`
- `src/Internal/Registry/OperationMetadataCompiler.php`
- `src/Internal/Outbox/**`
- `src/Internal/Application/**`
- `src/Internal/DependencyInjection/RuntimeContainerCompiler.php`
- `tests/Internal/Registry/**`
- `tests/Internal/Outbox/**`
- `tests/Internal/Application/**`
- `tests/Internal/DependencyInjection/**`
- `tests/Integration/**`
- `tests/Transport/PostgreSql/**`
- Deferred syntaxを持つ`tests/**` fixture
- Deferred syntaxを持つ`examples/quickstart/**`
- `examples/community-board/app/Feature/Comment/AddComment/AddComment.php`
- `examples/community-board/app/Feature/Notification/NotifyPostOwner/NotifyPostOwner.php`
- `examples/community-board/app/Feature/Digest/GenerateWeeklyDigest/GenerateWeeklyDigest.php`
- Related Community Board tests／Consumer scripts
- `resources/stubs/**`
- `docs/guide/**`
- `docs/internal/**`
- `docs/website/tests/**`と必要な既存content map／navigation fixture
- `CHANGELOG.md`
- `develop/spec/03-execution.md`
- `develop/spec/11-durable-journal-and-transactions.md`
- `develop/spec/50-operation-authoring-and-build-discovery.md`
- `develop/spec/74-application-ergonomics.md`
- `develop/spec/80-reliability-and-delivery.md`
- `develop/spec/82-operation-dispatch-and-deferred-authoring.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-000-deferred-authoring-and-operation-dispatch.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Required Commands

```bash
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac

bash tests/Consumer/quickstart-e2e.sh
bash tests/Consumer/framework-package-export.sh
bash tests/Consumer/community-board-clean-install.sh
bash tests/Consumer/community-board-post-comment.sh
bash tests/Consumer/community-board-product-journey.sh
bash tests/Consumer/community-board-digest.sh

mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples/quickstart/app examples/community-board/app --glob '*.php'
git diff --check
```

## Acceptance Criteria

- [x] `#[Deferred]`がCanonical reader-facing syntaxとして実装される
- [x] Existing `ExecuteWith`互換とduplicate／conflict guardが通る
- [x] Ray.Aop literal workaroundなしでGenerateWeeklyDigestがcompileする
- [x] `Operations::dispatch()`がClass＋ValueでDeferred childを登録する
- [x] Application側がchild Operation DefinitionのDependencyを構築しない
- [x] Existing Outbox atomicity／context／identity／relay保証を維持する
- [x] Quickstart／Community Board／Generator／Guideが新記法へ同期する
- [x] Framework／Consumer／Website full gateが成功する
- [x] ScheduledBy、汎用Bus、Manifest／Migration、Ray.AopへScopeを広げない
- [x] Management IDとSensitive Artifact guardが成功する
- [x] Report／STATEが実装とEvidenceに一致する
- [x] WorkerはCommitしない

## Completion Report

`develop/orchestration/reports/P20-000-deferred-authoring-and-operation-dispatch.md`へ少なくとも次を記録する。

- Summary
- Changed Files
- Deferred Attribute Contract
- Operations Dispatch Contract
- Transaction／Context／Identity Evidence
- Compatibility and Migration
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
