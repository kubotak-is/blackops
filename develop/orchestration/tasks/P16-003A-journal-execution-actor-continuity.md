# P16-003A: Journal Execution Actor Continuity Correction

Status: Ready

## Goal

Canonical JournalのOperation Identity検証を既存Actor Context仕様へ整合させる。

HTTP受付、Deferred Attempt、Retry、Lease Expiry Recoveryでは、同じOperationのorigin Actorとauthorization Actorを維持したままexecution Actorだけが正規に変わる。Status／Diagnostics Queryがこの変化をIntegrity Failureとして拒否しないよう修正し、P16-007のReal HTTP Journeyを再開可能にする。

## In Scope

- Status JournalのOperation Identity Fingerprint補正
- Diagnostics Journalの同一Fingerprint補正
- origin／authorization Actorの継続性検証維持
- HTTP受付ActorからWorker execution Actorへの変化の回帰
- Retry Attempt間およびLease Expiry Recoveryによるexecution Actor変化の回帰
- 不正なOperation Identity、origin Actor、authorization Actor変化のIntegrity Failure維持
- PostgreSQL Status／Diagnostics Queryの実Database回帰
- Internal Documentation、Report、STATE同期

## Out of Scope

- Actor Context、Journal Record、Public Status／Diagnostics DTOのShape変更
- Journal Schema、Database Migration、Retention Policy変更
- Current Actor／Origin Actorを使うStatus Authorization Contract変更
- ActorをPublic HTTP Response、Generated Client、Diagnostics Safe Projectionへ追加すること
- Authentication、Authorization、Worker Actor設定の変更
- P16-007のQuickstart、Guide、Website、Skeleton統合

## Relevant Specifications and Decisions

- `develop/spec/06-auth-and-middleware.md`
- `develop/spec/19-execution-context-api.md`
- `develop/spec/25-sensitive-projection.md`
- `develop/spec/30-lifecycle-state-machine.md`
- `develop/spec/65-operation-diagnostics.md`
- `develop/spec/69-deferred-status-and-outcome-api.md`
- `develop/decisions/095-phase-12-middleware-and-authorization-runtime.md`
- `develop/decisions/102-phase-16-deferred-status-and-outcome-api.md`
- `develop/orchestration/reports/P16-003-postgresql-status-projection.md`
- `develop/orchestration/reports/P16-007-consumer-experience-and-closeout.md`

## Files Allowed to Change

### Production

- `src/Internal/Status/OperationStatusJournalValidator.php`
- `src/Internal/Diagnostics/OperationDiagnosticsQuery.php`

### Tests

- New `tests/Internal/Status/OperationStatusJournalValidatorTest.php`
- `tests/Internal/Diagnostics/OperationDiagnosticsQueryTest.php`
- `tests/Transport/PostgreSql/PostgreSqlStatusQueryIntegrationTest.php`
- `tests/Transport/PostgreSql/PostgreSqlDiagnosticsQueryIntegrationTest.php`

### Documentation and Orchestration

- `docs/internal/status-query.md`
- `docs/internal/operation-diagnostics.md`（実在する場合だけ）
- `docs/internal/worker-runtime.md`（必要な補足だけ）
- `develop/spec/65-operation-diagnostics.md`（既存Invariantの明確化だけ）
- `develop/spec/69-deferred-status-and-outcome-api.md`（既存Invariantの明確化だけ）
- `develop/STATE.md`
- New `develop/orchestration/reports/P16-003A-journal-execution-actor-continuity.md`

上記以外の変更が必要な場合は実装を広げずReportへ記録する。P16-007の未Commit差分は保持するが、このTaskでは変更しない。

## Correct Identity Invariant

全Canonical Journal Recordで次を厳密に一致させる。

- Journal Record Schema Version
- Operation ID
- Operation Type
- Operation Schema Version
- Execution Strategy
- Correlation ID
- Causation IDのnull性と値
- origin Actorのnull性、ID、Type
- authorization Actorのnull性、ID、Type

execution ActorはOperation Identityへ含めない。これは各Recordを生成した実行主体であり、次の正規境界で変化できる。

- HTTP受付からDeferred Worker Attemptへの移行
- Retry後に別Workerが開始するAttempt
- Expired Leaseを別WorkerがRecoveryする経路

execution Actorを全Record同一、Attempt単位同一、または最初のWorkerと同一には制約しない。Lifecycle、Sequence、Attempt ID／Number／Started At、Retry参照は既存検証を維持する。Actor IDをPublic Resultへ投影しない。

## Regression Contract

- `operation.received -> operation.accepted`がHTTP execution Actorを持ち、続くAttempt RecordがWorker execution Actorを持つDeferred Journalを有効とする
- Retry後の次Attemptが別Worker execution Actorでも有効とする
- Lease Expiry Recoveryが同じAttemptへ別execution ActorでFailure／Retry／Terminal Recordを追加しても有効とする
- origin Actorまたはauthorization Actorが途中で変化したJournalは引き続きIntegrity Failureとする
- Operation ID、Type、Schema、Strategy、Correlation／Causation、Sequence、Lifecycle、Attempt整合性の既存Failureを弱めない
- Status Queryは正規Actor変化を含む`retry_scheduled`／`completed`をFoundへ投影する
- Diagnostics Queryも同じJournalをFoundへ投影し、Safe Actor Maskを維持する

## Acceptance Criteria

- [ ] Status Validatorがexecution Actorの正規変化を受理する
- [ ] Diagnostics Validatorが同じ正規変化を受理する
- [ ] origin／authorization Actor継続性を引き続き検証する
- [ ] Retry Attempt間とLease Recoveryのexecution Actor変化を回帰で固定する
- [ ] PostgreSQL Status Queryが正規Deferred Journalを500にしない
- [ ] PostgreSQL Diagnostics Queryの同じRegressionが成功する
- [ ] Public DTO／HTTP Response／Schema／Migrationに変更がない
- [ ] Target／Full PHPUnit、Mago、Deptrac、Guardが成功する
- [ ] P16-007の途中差分を変更していない
- [ ] WorkerはCommitしていない

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit --display-deprecations \
  tests/Internal/Status \
  tests/Internal/Diagnostics/OperationDiagnosticsQueryTest.php \
  tests/Transport/PostgreSql/PostgreSqlStatusQueryIntegrationTest.php \
  tests/Transport/PostgreSql/PostgreSqlDiagnosticsQueryIntegrationTest.php
docker compose run --rm app vendor/bin/phpunit --display-deprecations
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' \
  src tests --glob '*.php'
git diff --check
```
