# P5-013: Framework Maintenance Scheduler Worker

Status: Completed

## Summary

Framework Maintenance Scheduler Workerを実装した。

採用済みの方針どおり、1回実行の `blackops:scheduler:run` と明示的な常駐Loopの `blackops:scheduler:daemon` を分けた。多重起動制御はFramework内では持たず、Application / Cron / Container Scheduler / systemdなどの外部実行基盤に委ねる。

Retention TaskはComposition Rootが明示登録する形にし、`RetentionMaintenanceTask` がRetention Purge Serviceを呼び出す。

## Changed Files

- `src/Internal/Scheduler/MaintenanceTask.php`
- `src/Internal/Scheduler/MaintenanceTaskResult.php`
- `src/Internal/Scheduler/MaintenanceScheduler.php`
- `src/Internal/Scheduler/MaintenanceSchedulerResult.php`
- `src/Internal/Scheduler/RetentionMaintenanceTask.php`
- `src/Internal/Scheduler/Sleeper.php`
- `src/Internal/Scheduler/NativeSleeper.php`
- `src/Internal/Console/SchedulerRunCommand.php`
- `src/Internal/Console/SchedulerDaemonCommand.php`
- `tests/Internal/Scheduler/MaintenanceSchedulerTest.php`
- `tests/Internal/Console/SchedulerRunCommandTest.php`
- `tests/Internal/Console/SchedulerDaemonCommandTest.php`
- `docs/internals/maintenance-scheduler.md`
- `docs/internals/README.md`
- `docs/internals/bootstrap.md`
- `develop/orchestration/tasks/P5-013-framework-maintenance-scheduler-worker.md`
- `develop/orchestration/reports/P5-013-framework-maintenance-scheduler-worker.md`
- `develop/STATE.md`

## Decisions and Assumptions

- `blackops:scheduler:run` は1回だけ登録Taskを実行して終了する。
- `blackops:scheduler:daemon` は `--interval=60` を既定にした明示的な常駐Loopとした。
- `blackops:scheduler:daemon` はテストと制御された手動確認用に `--iterations` を持つ。未指定時はプロセス停止までLoopする。
- Framework内のDB Lock / File Lockは実装しない。多重起動制御は外部実行基盤の責務とする。
- Retention TaskはComposition Rootで明示登録する。標準Factory/Providerは後続Taskの検討対象とする。
- `NativeSleeper` はMagoの型要求に合わせ、負数秒を拒否してから `sleep()` を呼ぶ。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'MaintenanceSchedulerTest|SchedulerRunCommandTest|SchedulerDaemonCommandTest'
Result: OK (6 tests, 27 assertions). Runtime PHP 8.5.7.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (444 tests, 1368 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 1043 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] Scheduler Workerの実行方式が確定している。
- [x] `blackops:scheduler:run` が1回だけ登録Taskを実行して終了する。
- [x] `blackops:scheduler:daemon` が明示的なinterval Loopとして実行できる。
- [x] 多重起動制御の責務境界が外部実行基盤として記録されている。
- [x] Retention Taskの登録境界がComposition Rootとして記録されている。
- [x] Retention TaskがRetention Purge Serviceを呼び出す。
- [x] 必須Commandがすべて成功している。

## Remaining Issues

- Framework標準Factory/ProviderによるRetention Task登録は未実装。
- Framework内DB Lock / File Lockは未実装。必要になった場合はScheduler実行前のGuardとして追加する。
- Journal / Outcomeの実削除はRetention Purge Serviceの後続拡張対象として残る。

## Suggested Next Action

P5-014またはPhase 5 closeoutで、Retention SchedulerまでのPhase 5成果と残課題を整理する。
