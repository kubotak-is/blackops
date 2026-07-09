# P3-002: PostgreSQL Deferred Sender

Status: Accepted

## Goal

PostgreSQLへDeferred Operation MessageをDurable保存し、保存成功時に`DeferredAcknowledgement`を返すOperationSender実装を追加する。

## In Scope

- PostgreSQL Deferred Operation Schemaを追加する
- `DeferredOperationMessage` をPostgreSQLへ保存するSenderを追加する
- 保存成功時に `DeferredAcknowledgement` を返す
- 保存失敗時に `DeferredTransportException` を投げる
- PayloadとContextを不透明な `bytea` として保存する
- State、Version、Sequence、Available At、Accepted Atの初期値を保存する
- PostgreSQL Integration Testを追加する
- Deferred Transport Internals Documentation、Task Report、STATEを更新する

## Out of Scope

- Claim、Heartbeat、Acknowledge、Release実装
- Worker Runtime
- Deferred Strategy Dispatcher
- HTTP 202 Response変換
- `operation.received` / `operation.accepted` Journalを同一Transactionで書く受付Orchestrator
- Canonical Codec実装
- OpenAPI生成

## Relevant Specifications

- `spec/03-execution.md`
- `spec/05-http.md`
- `spec/27-journal-sequence-allocation.md`
- `spec/31-deferred-claim-and-attempt.md`
- `spec/33-execution-transport-contract.md`
- `spec/36-postgresql-transaction-boundaries.md`
- `spec/40-mvp-delivery-plan.md`
- `decisions/039-execution-transport-contract.md`
- `decisions/041-postgresql-transport-schema.md`
- `decisions/042-postgresql-transaction-boundaries.md`

## Files Allowed to Change

- `src/Transport/PostgreSql/**`
- `tests/Transport/PostgreSql/**`
- `docs/internals/**`
- `orchestration/tasks/P3-002-postgresql-deferred-sender.md`
- `orchestration/reports/P3-002-postgresql-deferred-sender.md`
- `orchestration/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- Public APIへInternal型を露出しない
- PayloadとContextは不透明な `bytea` として保存する
- Stateは `text` とCHECK Constraintで保存する
- 時刻は `timestamptz` として保存する
- Production起動時の暗黙DDL方針は広げず、既存PostgreSQL Adapterと同じ明示的 `migrate()` 方式にする
- Claim用のSQLは実装しない

## Acceptance Criteria

- [x] PostgreSQL Deferred Operation Schemaが作成される
- [x] SchemaにPayload、Context、Content Type、Encoding、Key ID、State、Version、Sequence、Available At、Accepted Atが含まれる
- [x] `OperationSender::enqueue()` がMessageを保存し、`DeferredAcknowledgement`を返す
- [x] 保存されたPayloadとContextが `bytea` である
- [x] 初期Stateが `accepted`、初期Versionが1、初期Sequenceが1として保存される
- [x] Duplicate Operation ID等の保存失敗が `DeferredTransportException` へ変換される
- [x] PostgreSQL Integration Testが追加される
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

`orchestration/reports/P3-002-postgresql-deferred-sender.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
