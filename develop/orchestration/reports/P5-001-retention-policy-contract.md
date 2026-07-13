# P5-001: Retention Policy Contract

Status: Completed

## Summary

Retention Policy Contractを実装した。

Retention対象をTransport Payload / Journal / Outcome / Dead Letterに分離し、保持期間は明示的な正の期間だけを受け入れるPublic APIとして追加した。`RetentionPolicy` は4対象すべての期間をConstructorで要求し、暗黙の既定値を持たない。

## Changed Files

- `develop/orchestration/tasks/P5-001-retention-policy-contract.md`
- `develop/orchestration/reports/P5-001-retention-policy-contract.md`
- `develop/STATE.md`
- `develop/TODO.md`
- `docs/internal/retention-policy.md`
- `src/Core/Retention/RetentionPeriod.php`
- `src/Core/Retention/RetentionPolicy.php`
- `src/Core/Retention/RetentionTarget.php`
- `tests/Core/Retention/RetentionPolicyTest.php`

## Decisions and Assumptions

- P5-001はRetention対象と保持期間のPublic Contractに限定する。
- Retention期間に暗黙の既定値を設けない方針を、4対象すべてを必須Constructor引数にすることで表現する。
- Retention期間は秒数を内部表現とし、日数Factoryは明示指定を補助するだけにする。
- Hold、Audit、Scheduler、PostgreSQL Schemaは後続Taskへ送る。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter RetentionPolicyTest
Result: OK (6 tests, 19 assertions). Runtime PHP 8.5.7.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (379 tests, 1112 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 852 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] Retention対象がTransport Payload / Journal / Outcome / Dead Letterで表現される
- [x] Retention期間は明示的な正の期間だけを受け入れる
- [x] Retention Policyは4対象すべての期間を持つ
- [x] Public APIが`#[PublicApi]`で示される
- [x] 必須Commandがすべて成功している

## Remaining Issues

- Retention Hold Portは未実装。
- PostgreSQL Retention Schemaは未実装。
- Tombstone / Purge Plan / Purge Serviceは未実装。
- Retention CLIとFramework Maintenance Scheduler Workerは未実装。

## Suggested Next Action

P5-002へ進み、Retention Hold PortまたはPostgreSQL Retention Schemaを実装する。
