# P5-013: Framework Maintenance Scheduler Worker

Status: Blocked

## Goal

Retention Taskを既定登録するFramework Maintenance Scheduler Workerを実装する。

## Blocker

Scheduler Workerの実行方式が未確定である。

## Required Decision

次を決める必要がある。

1. `blackops:scheduler:run` の実行モード
   - 1回だけDue Taskを実行して終了する
   - 常駐Loopとしてintervalごとに実行する
   - 両方を持ち、既定を1回実行にする

2. 多重起動制御
   - MVPではApplication / Cron / Container Scheduler側へ委ねる
   - Framework内にDB Lockを持つ
   - Framework内にFile Lockを持つ

3. Retention Taskの既定登録
   - Composition Rootが明示登録する
   - SchedulerがRetention Purge Serviceを必須依存として既定登録する

## Recommended Option

- `blackops:scheduler:run` は既定で1回だけDue Taskを実行して終了する
- 常駐Loopは `--loop --interval=60` のような明示Optionにする
- MVPの多重起動制御はApplication / Cron / Container Scheduler側へ委ね、Framework内Lockは後続拡張にする
- Retention TaskはComposition Rootが明示登録するが、標準Factory/Providerを後続Taskで用意する

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
