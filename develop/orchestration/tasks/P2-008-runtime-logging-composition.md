# P2-008: Runtime Logging Composition

Status: Accepted

## Goal

Production Runtime ComposerでExecution Scope ProviderとJournal Observation Pipelineを共有できる入口を追加する。

## In Scope

- Production Runtime Composerへoptional Execution Scope Providerを渡せるようにする
- Production Runtime Composerへoptional Journal Observation Pipelineを渡せるようにする
- ProductionRuntimeCompositionからExecution Scope Providerを参照できるようにする
- HTTP runtime経由のInline dispatchでJSONL Journal Observerへ配送できることを検証する
- Handlerへ注入されたExecutionScopedLoggerが同じScope ProviderからOperation metadataを読めることを検証する
- Runtime Internals Documentation、Task Report、STATEを更新する

## Out of Scope

- Service Provider configでLoggerを自動登録する仕組み
- Monolog-specific integration
- JSONL file path config
- OpenTelemetry Context propagation
- Sampling
- Public runtime bootstrap API

## Relevant Specifications

- `develop/spec/10-logging-and-traceability.md`
- `develop/spec/25-sensitive-projection.md`
- `develop/spec/26-journal-ports.md`
- `develop/spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `src/Internal/Runtime/**`
- `tests/Internal/Runtime/**`
- `docs/internals/**`
- `develop/orchestration/tasks/P2-008-runtime-logging-composition.md`
- `develop/orchestration/reports/P2-008-runtime-logging-composition.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- Public APIへInternal型を露出しない
- 既存のProductionRuntimeComposer呼び出し互換を保つ
- Runtime Composerはcontainerをhandlerへ渡さない

## Acceptance Criteria

- [x] Production Runtime Composerへoptional Execution Scope Providerを渡せる
- [x] Production Runtime Composerへoptional Journal Observation Pipelineを渡せる
- [x] ProductionRuntimeCompositionからExecution Scope Providerを参照できる
- [x] HTTP runtime経由でJSONL Journal ObserverへLifecycle Journalが出力される
- [x] Handlerへ注入されたExecutionScopedLoggerがOperation metadataを付与できる
- [x] 既存ProductionRuntimeComposer呼び出し互換を保つ
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

`develop/orchestration/reports/P2-008-runtime-logging-composition.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
