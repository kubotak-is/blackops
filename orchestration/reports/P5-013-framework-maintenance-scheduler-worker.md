# P5-013: Framework Maintenance Scheduler Worker

Status: Blocked

## Summary

P5-013は実装開始前の仕様照合でBlockerになった。

Retention Runtimeでは `blackops scheduler:run` とRetention Taskの既定登録が決定済みだが、Workerの実行方式、Loopの扱い、多重起動制御、Retention Task登録境界が未確定である。Production挙動に直結するため、実装前に判断を返す。

## Changed Files

- `orchestration/tasks/P5-013-framework-maintenance-scheduler-worker.md`
- `orchestration/reports/P5-013-framework-maintenance-scheduler-worker.md`
- `orchestration/STATE.md`

## Decisions and Assumptions

- Scheduler WorkerはScheduled Operation Strategyとは別のFramework Maintenance Runtimeである。
- Retention TaskをScheduler Workerへ既定登録する方針は決定済み。
- 実行モードと多重起動制御は未確定と判断した。

## Commands and Results

Production Code変更前にBlockerとなったため、品質Commandは未実行。

## Acceptance Criteria

- [ ] Scheduler Workerの実行方式が確定している
- [ ] 多重起動制御の責務境界が確定している
- [ ] Retention Taskの登録境界が確定している
- [ ] 必須Commandがすべて成功している

## Remaining Issues

次を確定する必要がある。

1. `blackops:scheduler:run` の実行モード
2. 多重起動制御の責務境界
3. Retention Taskの既定登録方法

## Suggested Next Action

推奨案:

- `blackops:scheduler:run` は既定で1回だけDue Taskを実行して終了する
- 常駐Loopは `--loop --interval=60` のような明示Optionにする
- MVPの多重起動制御はApplication / Cron / Container Scheduler側へ委ね、Framework内Lockは後続拡張にする
- Retention TaskはComposition Rootが明示登録するが、標準Factory/Providerを後続Taskで用意する

この推奨案を採用する場合、P5-013実装を開始する。
