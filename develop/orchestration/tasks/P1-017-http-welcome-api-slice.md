# P1-017: HTTP Welcome API Slice

Status: Accepted

## Goal

HTTP `GET /welcome` をOperationへBindingし、HandlerとResponderを通してAPI-only Responseを返し、同じ実行のLifecycle JournalをPostgreSQLへ保存する。

## In Scope

- HTTP Route Attributeを追加する
- Exact matchのPSR-15 Operation Request Handlerを追加する
- 最小OperationValue Binderを追加する
- Inline Completed／Rejected用のAPI-only Responderを追加する
- `GET /welcome` の統合Testを追加する
- HTTP経由実行でPostgreSQL JournalへCompleted Lifecycleが残ることを検証する
- Documentation、Task Report、STATEを更新する

## Out of Scope

- HTML Rendering
- Frontend Client Generation
- Operation Manifest CLI
- Dynamic Path Parameter
- Deferred HTTP 202
- Authentication／Middleware
- Runtime DI Container Compile

## Relevant Specifications

- `develop/spec/05-http.md`
- `develop/spec/26-journal-ports.md`
- `develop/spec/28-mvp-lifecycle-events.md`
- `develop/spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `src/Http/**`
- `tests/Http/**`
- `docs/internal/**`
- `docs/guide/**`
- `deptrac.yaml`
- `mago.toml`
- `develop/orchestration/tasks/P1-017-http-welcome-api-slice.md`
- `develop/orchestration/reports/P1-017-http-welcome-api-slice.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- D047が未決のためHTML Response Contractへ踏み込まない
- GET Request Bodyは拒否する

## Acceptance Criteria

- [ ] `#[Route(method: 'GET', path: '/welcome')]` をOperationへ付与できる
- [ ] `GET /welcome` がOperationValueへBindingされる
- [ ] HandlerのCompleted ResultがHTTP 200 JSONになる
- [ ] EmptyOutcomeはHTTP 204になる
- [ ] Rejected Resultが安定したJSON Errorになる
- [ ] `GET /welcome` 実行後、PostgreSQL JournalへCompleted Lifecycle 4 Eventが保存される
- [ ] Formatterを含む必須品質Commandが成功する
- [ ] PHP Comment／DocBlockに管理番号を含めない

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

`develop/orchestration/reports/P1-017-http-welcome-api-slice.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
