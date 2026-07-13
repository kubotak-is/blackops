# P6-007: Monolog JSONL Backend

Status: Accepted

## Goal

MVP標準Logging BackendとしてMonolog 3を実際に構成し、`ExecutionScopedLogger`が付与・Filterした構造化Contextを一行JSONとしてStreamへ出力できるFramework内部Factoryを提供する。

## In Scope

- Monolog 3 `Logger`、`StreamHandler`、`JsonFormatter`の標準構成
- PSR-3 `LoggerInterface`として返す内部Factory
- Channel、最低Log Level、Stream PathまたはStream Resourceの設定
- JSON Linesとして一Record一行を保証するFormatter設定
- `ExecutionScopedLogger`とのComposition Test
- Execution ContextとSensitive Filter後のContextがJSONへ保持されることのTest
- 最低Level未満のRecordをMonologが除外することのTest
- Stream初期化・書込み失敗をMonologの例外として隠蔽しないこと
- Framework実装者向けDocumentation

## Out of Scope

- Public Logging API変更
- Canonical Journal Observer変更
- Production Runtime全体のDI Wiring
- Buffer、Rotation、Network Handler、OpenTelemetry
- Monolog固有型のCore／Operation API露出
- Worker CLI、HTTP Runtime変更

## Relevant Specifications

- `develop/spec/13-mvp-technical-stack.md`
- `develop/spec/10-logging-and-traceability.md`
- `develop/decisions/014-logging-and-traceability.md`
- `develop/decisions/018-mvp-technical-stack.md`

## Files Allowed to Change

- `src/Internal/Logging/**`
- `tests/Internal/Logging/**`
- `mago.toml`
- `deptrac.yaml`
- `docs/internal/monolog-jsonl-backend.md`
- `docs/internal/README.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P6-007-monolog-jsonl-backend.md`
- `develop/orchestration/reports/P6-007-monolog-jsonl-backend.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- Production Code／TestのCommentへSpec、Decision、Task、TODOの管理番号を書かない
- Coreと公開Operation APIへMonolog型を露出しない
- FrameworkのSensitive Filter前のUser Contextを別Fieldへ複製しない
- JSON Recordは一件につき一行とし、Messageと構造化Contextを失わない
- File出力とLevel判定は独自再実装せずMonologへ委譲する
- Default ChannelとDefault Levelを決定的にする

## Acceptance Criteria

- [x] Internal FactoryがMonolog 3のPSR-3 Loggerを構成する
- [x] 一件のLogが改行終端された有効なJSON一行として出力される
- [x] Channel、Level、Message、ContextがJSONへ保持される
- [x] 最低Level未満のRecordが出力されない
- [x] `ExecutionScopedLogger`のOperation Contextが出力される
- [x] Sensitive Keyが出力JSONへ現れない
- [x] Core／公開APIにMonolog型が追加されない
- [x] 利用方法と拡張境界がDocumentationへ記録される
- [x] 必須Commandがすべて成功する

## Required Commands

```bash
docker compose run --rm app vendor/bin/phpunit --filter 'MonologJsonl|ExecutionScopedLogger'
docker compose run --rm app composer validate --strict
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P6-007-monolog-jsonl-backend.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
