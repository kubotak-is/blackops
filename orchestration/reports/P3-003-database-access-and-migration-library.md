# P3-003: Database Access and Migration Library Report

Status: Accepted

## Summary

Deferred受付Orchestrator実装前に、Framework-owned PostgreSQL AdapterのDatabase接続とMigration管理に使うLibraryを確定した。

Doctrine DBALとDoctrine MigrationsをRuntime依存へ追加し、Doctrine ORM、Symfony full-stack、DoctrineBundle、DoctrineMigrationsBundleは採用しない方針をD057へ記録した。Doctrine Migrationsが利用するSymfony Stopwatchは、既存方針に合わせて7.4 LTS系列へRoot Constraintで固定した。

## Changed Files

- `composer.json`
- `composer.lock`
- `mago.toml`
- `deptrac.yaml`
- `spec/README.md`
- `docs/internals/runtime-dependencies.md`
- `decisions/057-database-access-and-migration-library.md`
- `orchestration/tasks/P3-003-database-access-and-migration-library.md`
- `orchestration/reports/P3-003-database-access-and-migration-library.md`
- `orchestration/STATE.md`

## Decisions and Assumptions

- Database接続、Transaction、低レベルSQL実行にはDoctrine DBALを採用する。
- Framework SchemaのMigration管理にはDoctrine Migrationsを採用する。
- Doctrine ORMは採用しない。
- Symfony full-stack、DoctrineBundle、DoctrineMigrationsBundleは必須依存にしない。
- Symfony ComponentのMajor Version不整合を避けるため、Symfony StopwatchはRoot Constraintで7.4 LTS系列へ固定した。
- 既存PDO実装はこのTaskでは移行せず、後続TaskでDBAL Connectionへ段階移行する。

## Commands and Results

```text
docker compose run --rm app composer require doctrine/dbal:^4.4 doctrine/migrations:^3.9 --no-interaction
Result: Success. Locked doctrine/dbal 4.4.3, doctrine/migrations 3.9.7, doctrine/event-manager 2.1.1, psr/cache 3.0.0, symfony/stopwatch v8.1.0.

docker compose run --rm app composer require symfony/stopwatch:^7.4 --no-interaction
Result: Success. Downgraded symfony/stopwatch v8.1.0 to v7.4.8.

docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.

docker compose run --rm app composer audit
Result: No security vulnerability advisories found.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.

docker compose run --rm app mago lint
Result: INFO No issues found.

docker compose run --rm app mago analyze
Result: INFO No issues found.

docker compose run --rm app vendor/bin/phpunit
Result: OK (327 tests, 788 assertions). Runtime PHP 8.5.7.

docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 483 / Warnings 0 / Errors 0.

rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.

git diff --check
Result: No output.
```

## Acceptance Criteria

- [x] Database Access / Migration LibraryのDecisionが追加される
- [x] Doctrine DBALがRuntime依存へ追加される
- [x] Doctrine MigrationsがRuntime依存へ追加される
- [x] Symfony Stopwatchが7.4 LTS系列へ固定される
- [x] Runtime Dependencies Documentationが更新される
- [x] magoのvendor type resolution設定が更新される
- [x] deptracのLibrary layerとTransport依存規則が更新される
- [x] Formatterを含む必須品質Commandが成功する

## Remaining Issues

- 既存PostgreSQL AdapterはまだPDOを直接使っている。
- Migration Commandは未実装。
- Deferred受付Orchestratorは未実装。

## Suggested Next Action

P3-004としてPostgreSQL AdapterをDBAL Connectionへ移行し、Deferred受付OrchestratorでOperation State保存とCanonical Journal記録を同一Transactionへ統合する。
