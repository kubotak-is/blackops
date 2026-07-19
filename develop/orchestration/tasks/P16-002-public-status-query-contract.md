# P16-002: Public Status Query Contract

Status: Ready

## Goal

Database、HTTP、Frontendに依存しないPublic Status Query Contractを実装し、Operation IDから認可前の最小Subjectを読み、専用Query AuthorizerでAllowされた場合だけStatus Detailへ進むFail-closed境界を固定する。

## In Scope

- `BlackOps\Status` Public Namespace
- 7 StateのEnum、Status Aggregate、State別Invariant
- Found／Unavailable／Expired Result
- Typed OutcomeとSafe Terminal Error
- Safe Query Exceptionと安定Code
- Status専用Authorizer、Authorization Request／Decision、既定Deny
- 認可前Subjectと認可後Detailを分けるInternal Source Port／DTO
- Source-neutralなDefault Query Service
- Unknown／Denyの同一Unavailable、Allow後だけExpiredとなる順序
- Public API Architecture、Deptrac、Unit Test、Internal Documentation
- Report、TODO、STATE同期

## Out of Scope

- PostgreSQL／Doctrine DBAL実装
- Canonical Journal、Outcome Store、Dead Letter、Purge Auditへの接続
- HTTP Route、Responder、Configuration、Application Composition
- Deferred 202の`Location`／`Retry-After`
- Frontend Contract／Generator／TypeScript／`.status()`／`.wait()`
- Quickstart、Skeleton、Guide、Website、Consumer E2E
- Migration、Database Schema
- List、Search、Cancel、Retry、Tenant Framework

## Relevant Specifications and Decisions

- `develop/spec/01-core-model.md`
- `develop/spec/06-auth-and-middleware.md`
- `develop/spec/17-core-api.md`
- `develop/spec/25-sensitive-projection.md`
- `develop/spec/29-handler-result-contract.md`
- `develop/spec/30-lifecycle-state-machine.md`
- `develop/spec/38-data-retention-and-deletion.md`
- `develop/spec/65-operation-diagnostics.md`
- `develop/spec/69-deferred-status-and-outcome-api.md`
- `develop/spec/70-phase-16-delivery-plan.md`
- `develop/decisions/102-phase-16-deferred-status-and-outcome-api.md`

## Files Allowed to Change

### Production

- New `src/Status/**`
- New `src/Internal/Status/**`
- `deptrac.yaml`

### Tests and Fixtures

- New `tests/Status/**`
- New `tests/Internal/Status/**`
- `tests/Architecture/PublicApiArchitectureTest.php`（新Public型の共通Guardが不足する場合だけ）
- New `tests/Architecture/Fixture/**`（Status Contract専用Fixtureだけ）

### Documentation and Orchestration

- New `docs/internal/status-query.md`
- `docs/internal/README.md`
- `develop/spec/69-deferred-status-and-outcome-api.md`（実装不能な矛盾を発見した場合だけ）
- `develop/spec/70-phase-16-delivery-plan.md`（Task境界の誤りを発見した場合だけ）
- `develop/TODO.md`
- `develop/orchestration/reports/P16-002-public-status-query-contract.md`
- `develop/STATE.md`

上記以外の変更が必要な場合は実装を広げず、ReportのBlockerとしてOrchestratorへ返す。

## Public Contract

最低限、次をPublic APIとして実装する。命名を変える必要がある場合はProduction Codeへ進まずBlockerを返す。

```text
BlackOps\Status\OperationStatusState
BlackOps\Status\OperationStatus
BlackOps\Status\OperationStatusError
BlackOps\Status\OperationStatusResult
BlackOps\Status\OperationStatusFound
BlackOps\Status\OperationStatusUnavailable
BlackOps\Status\OperationStatusExpired
BlackOps\Status\OperationStatusQuery
BlackOps\Status\OperationStatusAuthorizer
BlackOps\Status\OperationStatusAuthorizationRequest
BlackOps\Status\OperationStatusAuthorizationDecision
BlackOps\Status\Exception\OperationStatusQueryException
```

- Stateは`accepted`、`running`、`retry_scheduled`、`completed`、`rejected`、`failed`、`dead_lettered`
- `OperationStatus`はOperation ID、Operation Type、Stateと、Stateに許可されたFieldだけを持つ
- Attemptは1始まり、Retry AtはUTCへ正規化する
- Completedだけが`Outcome`を持つ。Void完了は`EmptyOutcome`を使う
- RejectedだけがSafe Category／Codeを持つ
- Failed／Dead Letteredは固定Public Codeだけを持つ
- Public ResultへActor、Payload、Context、Journal、Throwable、Source DTOを露出しない
- 全Public型へ`#[PublicApi]`を付け、Public SignatureからInternal型を露出しない

## Authorization and Query Order

- AuthorizerはOperation ID、Operation Type、Current Actorまたはnull、Origin Actorまたはnullを受け取る
- DecisionはAllow／Denyだけを表現し、Deny理由をResultへ出さない
- Frameworkの既定Authorizerは常にDenyする
- QueryはSubject取得、Authorizer評価、Detail取得の順に実行する
- SubjectなしはAuthorizer／Detailを呼ばずUnavailable
- DenyはDetailを呼ばずUnavailable
- AllowされたExpired SubjectだけExpired
- AllowされたAvailable SubjectだけDetailを読む
- SubjectとDetailのOperation ID／Type不一致はIntegrity Failure
- Authorizer Throwableは`status_query.authorization_failed`
- Source Throwableは種類に応じて`status_query.storage_failed`、`status_query.decode_failed`、`status_query.integrity_failed`へ安全に正規化する

Internal Source Contractは認可前にOutcomeやTerminal Detailを返せない型へ分離する。In-memory Fakeで呼出順と非呼出を検証できる形にする。

## Acceptance Criteria

- [ ] 7 Stateが安定したString-backed Enumで表現される
- [ ] Status AggregateがState別Fieldの組合せを強制する
- [ ] Typed OutcomeとSafe Terminal Errorを表現できる
- [ ] Found／Unavailable／Expiredが排他的なResultになる
- [ ] 専用Query Authorizerと既定DenyがPublic Contractになる
- [ ] Subjectなし／DenyでDetail Sourceを呼ばない
- [ ] Allow後だけStatus DetailまたはExpiredを返す
- [ ] Authorizer／Storage／Decode／Integrity FailureがSafe Codeへ正規化される
- [ ] Actor、Payload、Context、Raw Error、Internal型をPublic Resultへ露出しない
- [ ] PostgreSQL、HTTP、Frontend、Migrationを変更しない
- [ ] Required PHP Quality Gateが成功する
- [ ] WorkerはCommitしない

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations \
  tests/Status \
  tests/Internal/Status \
  tests/Architecture/PublicApiArchitectureTest.php
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P16-002-public-status-query-contract.md`へ少なくとも次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Public API Shape
- State Invariant Matrix
- Authorization／Source Call Order Matrix
- Safe Failure Matrix
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
