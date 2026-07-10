# P5-013: Framework Maintenance Scheduler Worker

Status: Completed

## Goal

Retention Taskを既定登録するFramework Maintenance Scheduler Workerを実装する。

## Decision

- `blackops:scheduler:run` は1回だけTaskを実行して終了する。
- 常駐Loopは `blackops:scheduler:daemon --interval=60` として明示Commandに分ける。
- MVPの多重起動制御はApplication / Cron / Container Scheduler / systemd側へ委ね、Framework内Lockは後続拡張にする。
- Retention TaskはComposition Rootが明示登録する。標準Factory/Providerは後続Taskで検討する。

## Files Allowed to Change After Decision

- `src/Core/Retention/**`
- `src/Internal/Console/**`
- `src/Internal/Scheduler/**`
- `tests/Internal/Console/**`
- `tests/Internal/Scheduler/**`
- `docs/internals/**`
- `orchestration/tasks/P5-013-framework-maintenance-scheduler-worker.md`
- `orchestration/reports/P5-013-framework-maintenance-scheduler-worker.md`
- `orchestration/STATE.md`
