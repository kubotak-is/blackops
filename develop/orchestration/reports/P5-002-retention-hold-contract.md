# P5-002: Retention Hold Contract

Status: Completed

## Summary

Retention Hold Contractを実装した。

`RetentionHoldId`を専用UUIDv7 Value Objectとして追加し、`RetentionActorRef`を非空文字列のActor Referenceとして追加した。Hold Category、Hold Record、Hold PortをPublic Contractとして定義し、Hold解除は同一Recordの`released_at` / `released_by`を埋めた状態として表す。

## Changed Files

- `develop/orchestration/tasks/P5-002-retention-hold-contract.md`
- `develop/orchestration/reports/P5-002-retention-hold-contract.md`
- `develop/STATE.md`
- `develop/TODO.md`
- `docs/internal/retention-hold.md`
- `src/Core/Identifier/RetentionHoldId.php`
- `src/Core/Retention/RetentionActorRef.php`
- `src/Core/Retention/RetentionHold.php`
- `src/Core/Retention/RetentionHoldCategory.php`
- `src/Core/Retention/RetentionHoldPort.php`
- `src/Internal/Identifier/IdentifierFactory.php`
- `tests/Core/Identifier/IdentifierTest.php`
- `tests/Core/Retention/RetentionHoldTest.php`
- `tests/Internal/Identifier/IdentifierFactoryTest.php`

## Decisions and Assumptions

- P5-002はRetention Hold Contractとして切る。
- `RetentionHoldId`を専用UUIDv7 Value Objectとして追加した。
- `RetentionActorRef`を将来のActor Modelと疎結合な非空文字列Referenceとして追加した。
- Hold CategoryはLegal / Security / Audit / Support / Otherで確定済みと判断した。
- Holdは権限を持つActorまたは外部Compliance Systemによる明示設定だけとする。
- Failed / Dead Lettered時の自動Holdは実装しない。
- Hold解除は同一Hold Recordの`released_at` / `released_by`更新として表す。
- Store実装、PostgreSQL Schema、CLI、Purge Serviceとの接続は後続Taskへ送る。

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'RetentionHoldTest|IdentifierTest|IdentifierFactoryTest'
Result: OK (69 tests, 155 assertions). Runtime PHP 8.5.7.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (394 tests, 1156 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped violations 0 / Uncovered 0 / Allowed 854 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] Hold IDとActor ReferenceのPublic API方針が確定している
- [x] CategoryがLegal / Security / Audit / Support / Otherで表現される
- [x] Hold設定と解除を表すContractがある
- [x] Failed / Dead Letteredによる自動HoldをContractが要求しない
- [x] 必須Commandがすべて成功している

## Remaining Issues

- PostgreSQL Retention Schemaは未実装。
- Retention Hold Storeは未実装。
- Purge Plan / Purge Serviceは未実装。
- Retention CLIとFramework Maintenance Scheduler Workerは未実装。

## Suggested Next Action

P5-003へ進み、PostgreSQL Retention SchemaまたはRetention Hold Storeを実装する。
