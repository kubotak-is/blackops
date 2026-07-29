# P20-016D: Operation Data Read Authorization

Status: Ready

## Goal

Status AuthorizationをTenant-awareにし、ApplicationからCanonical Journal／Outcomeを直接読む場合のDefault-deny Authorizationと型付きQuery Resultを提供する。Raw Storage PortはInfrastructure SPIへ分離する。

## Source of Truth

- `AGENTS.md`
- `develop/spec/99-tenant-isolation-and-protected-operation-data.md`
- `develop/spec/69-deferred-status-and-outcome-api.md`
- `develop/spec/65-operation-diagnostics.md`
- `develop/decisions/135-tenant-isolation-and-protected-operation-data.md`

## Dependencies

- P20-016C Accepted

## In Scope

- Status Authorization RequestのCurrent／Origin Tenant
- Operation Data Resource／Purpose／Authorization Request／Decision／Authorizer
- Journal／OutcomeのPublic Authorized Query／Found／Unavailable／Safe Exception
- Default-deny Binding
- Subject→Authorize→Decrypt／Decode順序
- Raw `CanonicalJournalReader`／`OutcomeReader`のInfrastructure DI分離
- `operation:inspect` Safe Projection維持
- Application Builder／Runtime Composition
- Unit／PostgreSQL Integration／HTTP Status Regression

## Out of Scope

- Blob Encryption Adapter配線
- Public Raw HTTP Endpoint
- Admin UI、List、Search、Bulk Read
- Rotation CLI
- Public Guide／Website

## Files Allowed to Change

- `src/Status/**`
- `src/OperationData/**`
- `src/Journal/CanonicalJournalReader.php`
- `src/Outcome/OutcomeReader.php`
- `src/Internal/Status/**`
- `src/Internal/OperationData/**`
- `src/Internal/Http/OperationStatusAuthorizerResolver.php`
- `src/Internal/Diagnostics/**`
- `src/Internal/Application/**`
- `src/Application/ApplicationBuilder.php`
- `src/Http/Status/**`
- `src/Transport/PostgreSql/PostgreSqlStatusReader.php`
- Corresponding files under `tests/Status/**`
- Corresponding files under `tests/OperationData/**`
- Corresponding files under `tests/Internal/Status/**`
- Corresponding files under `tests/Internal/OperationData/**`
- Corresponding files under `tests/Internal/Diagnostics/**`
- Corresponding files under `tests/Internal/Application/**`
- Corresponding files under `tests/Http/Status/**`
- Corresponding PostgreSQL query tests
- `deptrac.yaml`
- `develop/spec/16-namespace-dependencies.md`
- `develop/spec/99-tenant-isolation-and-protected-operation-data.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-016D-operation-data-read-authorization.md`

上記以外が必要なら実装を広げずReportのBlockerとして返す。

## Implementation Constraints

- Default Status AuthorizerとOperation Data Read AuthorizerはDenyを維持する
- Purpose CodeはSpecificationのGrammar／Lengthを検証し、自由記述Reasonを許可しない
- RequestへRaw Value／Outcome／Credentialを含めない
- Unknown／Tenant不一致／Deny／Retention削除はResource別Unavailableにする
- Authorizer Throwable、Storage、Protection、Decode、Integrity FailureをUnavailableへ丸めない
- Allow前にJournal／Outcome BlobをSELECT／Decodeしない
- Status Unknown／Unauthorizedは同じ404 Surfaceを維持する
- Raw ReaderをApplication Queryと同じBindingへ公開しない
- Existing Status APIをTyped Outcome標準Surfaceとして維持する
- `operation:inspect`へRaw／Decrypted Dumpを追加しない
- Tenant／Actor Raw ID、Deny Reason、SQL、PayloadをSafe Errorへ出さない
- WorkerはReview前にCommitしない

## Acceptance Criteria

- [ ] Status AuthorizerがCurrent／Origin Tenantを受ける
- [ ] Tenantなし／あり、Same／Cross TenantのStatus Authorization Matrixが固定される
- [ ] Journal／Outcome QueryがFound／Unavailableを型で区別する
- [ ] 未Binding／Deny／Unknown／Tenant不一致でDecoderが呼ばれない
- [ ] Authorizer ThrowableとStorage／Decode／Integrity FailureがStable Safe Codeになる
- [ ] Raw ReaderがInfrastructure Runtimeでは使え、Application Reader Bindingから取得できない
- [ ] Status HTTPの401／404／410／500とTyped Outcomeを維持する
- [ ] `operation:inspect`がSafe Projectionだけを返す
- [ ] Cross-tenant Information LeakがResponse／Timing対策可能Pathで抑制される
- [ ] Full Suite／Architecture Guardが成功する
- [ ] Report／STATE／TODOを同期し、WorkerはCommitしない

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

`develop/orchestration/reports/P20-016D-operation-data-read-authorization.md`へ必須項目とAuthorization／Decode-order Evidenceを記録する。
