# P2-007: Execution Scoped Logger

Status: Accepted

## Goal

PSR-3 Loggerへ委譲しつつ、Execution Scope ProviderからOperation metadataを自動contextとして付与するInternal Logger decoratorを追加する。

## In Scope

- Internal PSR-3 Logger decoratorを追加する
- Execution Scopeがある場合にOperation metadataを自動付与する
- Execution Scopeがない場合はOperation metadataなしで出力する
- User contextを予約Fieldと分離したnamespaceへ格納する
- Logger contextをSensitive Projection Filterへ通す
- PSR Log型解決とDeptrac Library設定を更新する
- Logging Internals Documentation、Task Report、STATEを更新する

## Out of Scope

- Runtime ComposerからLoggerを構成する入口
- Service ProviderからLoggerを配線する設定
- Monolog-specific integration
- OpenTelemetry Context propagation
- Sampling
- JSONL application log encoder

## Relevant Specifications

- `develop/spec/10-logging-and-traceability.md`
- `develop/spec/13-mvp-technical-stack.md`
- `develop/spec/16-namespace-dependencies.md`
- `develop/spec/25-sensitive-projection.md`
- `develop/spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `src/Internal/Logging/**`
- `tests/Internal/Logging/**`
- `docs/internal/**`
- `mago.toml`
- `deptrac.yaml`
- `develop/orchestration/tasks/P2-007-execution-scoped-logger.md`
- `develop/orchestration/reports/P2-007-execution-scoped-logger.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- Public APIへInternal型を露出しない
- User contextでFW予約Fieldを上書きさせない
- Logger contextはAdapterへ渡す前にSensitive Projection Filterを通す

## Acceptance Criteria

- [x] Internal Logger decoratorがPSR-3 LoggerInterfaceとして追加される
- [x] Scope内LogへOperation ID、Type ID、Attempt ID、Correlation ID、Causation ID、Execution Strategyが付与される
- [x] Scope外LogにはOperation metadataを付与しない
- [x] User contextは `context` namespaceへ分離される
- [x] Sensitive keyはUser contextから除外される
- [x] PSR Log型解決とDeptracが成功する
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

`develop/orchestration/reports/P2-007-execution-scoped-logger.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
