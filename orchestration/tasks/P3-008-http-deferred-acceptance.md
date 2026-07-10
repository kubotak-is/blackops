# P3-008: HTTP Deferred Acceptance

Status: Accepted

## Goal

Routeを持つDeferred OperationをHTTPから受け付け、Operation CodecでMessage化し、Deferred Acceptance Orchestratorへ渡してHTTP 202 Acknowledgement Responseを返す。

## In Scope

- HTTP層のDeferred受付Portを追加する
- OperationRequestHandlerがDeferred Routeを受付Portへ委譲できるようにする
- Internal Deferred HTTP Acceptorを追加し、Registry、ExecutionContextFactory、OperationCodec、DeferredAcceptanceOrchestratorを接続する
- JsonOperationResponderへDeferredAcknowledgementのHTTP 202 JSON変換を追加する
- PostgreSQL Integration TestでHTTP 202、Operation State、Journalを検証する
- Deferred Transport / HTTP Documentation、Task Report、STATEを更新する

## Out of Scope

- Worker Runtime
- Worker Claim / Heartbeat / Settlement
- Deferred Operation Handler実行
- Deferred Outcome取得API
- Location Header生成
- OpenAPI / ManifestのDeferred応答拡張

## Relevant Specifications

- `spec/05-http.md`
- `spec/13-mvp-technical-stack.md`
- `spec/19-execution-context-api.md`
- `spec/33-execution-transport-contract.md`
- `spec/35-postgresql-transport-schema.md`
- `spec/36-postgresql-transaction-boundaries.md`
- `decisions/006-handler-and-outcome.md`
- `decisions/017-mvp-scope.md`
- `decisions/039-execution-transport-contract.md`
- `decisions/041-postgresql-transport-schema.md`

## Files Allowed to Change

- `src/Http/**`
- `src/Internal/Http/**`
- `src/Internal/Execution/**`
- `tests/Http/**`
- `tests/Internal/Http/**`
- `docs/internals/**`
- `orchestration/tasks/P3-008-http-deferred-acceptance.md`
- `orchestration/reports/P3-008-http-deferred-acceptance.md`
- `orchestration/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- HTTP層からInternal実装へ直接依存しない
- Public APIへInternal型を露出しない
- Routeを持つDeferred OperationはHandler完了を待たずにHTTP 202を返す
- GET / HEADのRequest Body禁止は維持する
- Handler実行、Observer配送、Worker実行は行わない

## Acceptance Criteria

- [x] HTTP Deferred受付Portが追加される
- [x] OperationRequestHandlerがDeferred Routeを受付Portへ委譲できる
- [x] Internal Deferred HTTP AcceptorがCodec済みDeferredOperationMessageを生成する
- [x] Deferred Acceptance Orchestratorへ受付が渡される
- [x] 成功時にHTTP 202 JSON Acknowledgementが返る
- [x] HTTP 202 BodyにOperation IDとAccepted Atが含まれる
- [x] PostgreSQLへOperation Stateと`operation.received` / `operation.accepted` Journalが保存される
- [x] Inline Routeの既存挙動が維持される
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
git diff --check
```

## Expected Report

`orchestration/reports/P3-008-http-deferred-acceptance.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
