# P20-016A: Tenant Context and Propagation

Status: Accepted

## Goal

Public `TenantRef`とOptional Tenant Contextを追加し、HTTP、ConsoleCommand、Scheduled Operation、Public Root DispatchからChild、Deferred Worker、Retry、Lease Recovery、Outboxまで同じTenantを不変伝播する。Current SourceのReplay Surfaceを監査し、存在しないTerminal Operation Replay APIは推測で追加しない。

## Source of Truth

- `AGENTS.md`
- `develop/spec/99-tenant-isolation-and-protected-operation-data.md`
- `develop/spec/19-execution-context-api.md`
- `develop/spec/69-deferred-status-and-outcome-api.md`
- `develop/spec/80-reliability-and-delivery.md`
- `develop/spec/98-scheduled-application-operation.md`
- `develop/decisions/135-tenant-isolation-and-protected-operation-data.md`

## Dependencies

- P20-015 Accepted

## In Scope

- Public `TenantRef`
- `ExecutionContext::tenant(): ?TenantRef`
- `AuthenticationResult`とHTTP MiddlewareのOptional Tenant
- `ConsoleTenantProvider`／`ScheduledTenantProvider`
- Public `Dispatcher`末尾Optional Tenant
- Root Context Factory、Scheduled Runtime、Console Runtime
- Child Operation、Transactional Outbox、Deferred Context Codec
- Worker Retry／Lease RecoveryのTenant維持
- Current SourceのReplay Surface監査と、将来のExplicit Terminal Operation Replay不変条件の維持
- Canonical／Observed JournalのSafe Tenant境界
- Application Builder／Runtime Composition
- Unit、Integration、Consumer Evidence

## Out of Scope

- PostgreSQL Tenant Column／Query／Migration
- Status／Journal／Outcome Read Authorization
- Encryption、Storage Key Provider、Protected Adapter
- Key Rotation CLI
- Public Guide／Website

## Files Allowed to Change

- `src/Core/TenantRef.php`
- `src/Core/ExecutionContext.php`
- `src/Core/ActorContext.php`
- `src/Execution/**`
- `src/Http/Authentication/**`
- `src/Http/DeferredOperationAcceptor.php`
- `src/Http/OperationRequestHandler.php`
- `src/Console/ConsoleTenantProvider.php`
- `src/Scheduling/ScheduledTenantProvider.php`
- `src/Internal/ExecutionContext/**`
- `src/Internal/Codec/**`
- `src/Internal/Execution/**`
- `src/Internal/Http/**`
- `src/Internal/Console/**`
- `src/Internal/Scheduling/**`
- `src/Internal/Outbox/**`
- `src/Internal/Replay/**`
- `src/Internal/Application/**`
- `src/Application/ApplicationBuilder.php`
- `src/Journal/JournalOperation.php`
- `src/Internal/Journal/**`
- `src/Internal/Projection/ObservedJournalRecordProjector.php`
- `src/Transport/InMemory/**`
- `src/Transport/PostgreSql/PostgreSqlJournalRecordCodec.php`
- Corresponding files under `tests/**`
- Consumer fixtures/scripts required for root／child／worker propagation
- `deptrac.yaml`
- `develop/spec/16-namespace-dependencies.md`
- `develop/spec/99-tenant-isolation-and-protected-operation-data.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-016A-tenant-context-and-propagation.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Implementation Constraints

- `TenantRef` Constructorは`type, id`順で、Trim後の空値を拒否する
- TenantRefへCredential、Display Name、Role、Permission、Planを追加しない
- `ExecutionContext`のTenantは末尾Optional ParameterとGetterだけで、Public Mutatorを追加しない
- Actor、OperationValue、Header文字列からFrameworkがTenantを推測しない
- HTTP AnonymousはTenantなし。Invalid Authentication ResultへTenantを保持しない
- Console／Scheduled Tenant ProviderはActor Providerと別Portにする
- Child DispatchはTenant Overrideを追加せず親Tenantを継承する
- Retry／Lease Recovery／Workerは受理時Tenantを再解決しない
- Explicit Terminal Operation Replay Runtimeが追加される場合は、Authorization後だけ元Tenantを新Rootへ渡す
- Current SourceにTerminal Operation Replay Runtimeが存在しない場合、推測したPublic APIを追加しない
- Observed Journal／Default LogへRaw Tenant IDを追加しない
- Credential、Raw Claim、Tenant Provider DetailをContext／Journal／Errorへ保存しない
- New Dependencyを追加しない
- WorkerはReview前にCommitしない

## Acceptance Criteria

- [x] TenantRefのPositive／empty／whitespace Matrixが固定される
- [x] Tenantあり／なしExecutionContextが不変である
- [x] Authenticated HTTPが検証済みTenantをRoot Contextへ渡し、Anonymous／Invalidは渡さない
- [x] Console／Scheduled Tenant ProviderがActor Providerから独立している
- [x] Public Root Dispatcherが末尾Optional Tenantを受ける
- [x] Child／Outboxが親Tenantを継承し、Overrideできない
- [x] Deferred ContextがTenantをround-tripし、Worker／Retry／Lease Recoveryで維持する
- [x] Current SourceにTerminal Operation Replay Runtimeが存在しないことを監査し、推測APIを追加せずSpecification 99の将来不変条件を維持する
- [x] Provider FailureがTenantなしへFallbackしない
- [x] Tenant Raw IDがObserved Journal／Log／Errorへ露出しない
- [x] Existing TenantなしJourneyとFull Suiteを維持する
- [x] Report／STATE／TODOを同期し、WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Completion Report

`develop/orchestration/reports/P20-016A-tenant-context-and-propagation.md`へSummary、Changed Files、Decisions and Assumptions、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
