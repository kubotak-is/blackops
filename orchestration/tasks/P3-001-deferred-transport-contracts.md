# P3-001: Deferred Transport Contracts

Status: Accepted

## Goal

Deferred Vertical Sliceの土台として、Deferred Strategy、Durable受付Acknowledgement、Transport Message、Claim、Transport PortのPublic Contractを追加する。

## In Scope

- Public `Deferred` Execution Strategyを追加する
- Public `DeferredOperationMessage` を追加する
- Public `DeferredAcknowledgement` を追加する
- Public `ClaimRequest` と `OperationClaim` を追加する
- Public Transport Portを責務別Interfaceとして追加する
- Transport失敗時のPublic Exceptionを追加する
- Core ContractのUnit Testを追加する
- Deferred Transport ContractのInternals Documentation、Task Report、STATEを更新する

## Out of Scope

- PostgreSQL Execution Transport実装
- Deferred Strategy Dispatcher実装
- HTTP 202 Response変換
- Worker Runtime
- Retry、Heartbeat実装の実体、Crash Recovery、Dead Letter
- Operation ManifestやOpenAPI生成の拡張

## Relevant Specifications

- `spec/03-execution.md`
- `spec/05-http.md`
- `spec/12-mvp-scope.md`
- `spec/31-deferred-claim-and-attempt.md`
- `spec/33-execution-transport-contract.md`
- `spec/36-postgresql-transaction-boundaries.md`
- `spec/40-mvp-delivery-plan.md`
- `decisions/037-deferred-claim-and-attempt.md`
- `decisions/039-execution-transport-contract.md`
- `decisions/047-frontend-integration.md`

## Files Allowed to Change

- `src/Core/**`
- `tests/Core/**`
- `docs/internals/**`
- `orchestration/tasks/P3-001-deferred-transport-contracts.md`
- `orchestration/reports/P3-001-deferred-transport-contracts.md`
- `orchestration/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- Public APIへInternal型を露出しない
- Transport MessageはPHP Serializationへ依存しない
- Lease Owner、Lease期限、Fencing Token等のInfrastructure固有Metadataを業務Handler向けContextへ露出しない
- HTTP ProcessがSenderだけへ依存できるよう、Portを責務別Interfaceへ分離する
- Batch Claimは実装しない

## Acceptance Criteria

- [x] `Deferred` がPublic `ExecutionStrategy` として追加される
- [x] `DeferredOperationMessage` がOperation ID、Operation Type、Schema Version、Encoded Payload、Encoded Context、Available Atを保持する
- [x] `DeferredAcknowledgement` がOperation IDとAccepted Atを保持する
- [x] `ClaimRequest` と `OperationClaim` がPublic Contractとして追加される
- [x] `OperationSender`、`OperationReceiver`、`ClaimHeartbeat`、`ClaimSettlement`、`ExecutionTransport` が責務別Public Interfaceとして追加される
- [x] Transport失敗時のPublic Exceptionが追加される
- [x] Core ContractのUnit Testが追加される
- [x] Internals Documentationが更新される
- [x] Formatterを含む必須品質Commandが成功する
- [x] PHP Comment／DocBlockに管理番号を含めない

## Required Commands

```bash
docker compose run --rm app mago format --check src tests
docker compose run --rm app composer validate --strict
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
```

## Expected Report

`orchestration/reports/P3-001-deferred-transport-contracts.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
