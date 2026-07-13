# MVP Status

BlackOps MVP is complete. This milestone proves the operation model, Inline and Deferred HTTP execution, lifecycle journal, worker recovery, compiled runtime artifacts, typed outcomes, and retention on PHP 8.5 with PostgreSQL.

MVP Complete is not the same as Production Ready. Framework／Skeleton Stable `1.0.0`はMVP Closeout後のPhase 8で公開した。Applications still own composition, database credentials, deployment, process supervision, access control, and operational policy.

## Definition of Done

| Requirement | Status | Evidence |
| --- | --- | --- |
| PHP 8.5で実行できる | Satisfied | Docker ComposeのPHP 8.5.7でSample E2Eと全Testを実行。`composer.json`もPHP `>=8.5`を要求する。 |
| SampleのInline／Deferred Operationが動く | Satisfied | `MvpSampleEndToEndTest::testCompiledSampleRunsInlineAndDeferredAcrossWorkerRestart` が `GET /welcome` と `POST /reports` を実行する。 |
| 全Lifecycle JournalをOperation IDで追跡できる | Satisfied | Sample E2EがInline／Deferred lifecycle順を検証し、`LifecycleStateMachineTest` と `DeferredWorkerRuntimeTest` がRejected、Failed、Retry、Dead Letterを含む標準eventを検証する。 |
| HTTP 200／202とOperation IDが返る | Satisfied | Inline WelcomeはHTTP 200、Deferred ReportはHTTP 202とOperation IDを返す。Operation ID返却はDeferred acknowledgementのContractである。 |
| Worker再起動後も未処理Deferred Operationを実行できる | Satisfied | Sample E2EがHTTP、初回Worker、再起動Workerで別DBAL Connectionと別DI Containerを使い、PostgreSQL上の同じOperationを再claimする。 |
| Handler例外をAttemptFailedとして記録できる | Satisfied | Sampleの初回Report attemptと`DeferredWorkerRuntimeTest`が `attempt.failed` をCanonical Journalへ保存する。 |
| 最低一回のRetryを実行できる | Satisfied | SampleがAttempt 1のretryable failure後に `attempt.retry_scheduled` を記録し、Attempt 2で完了する。 |
| Sensitive Filterの最小実装がある | Satisfied | SampleはCanonical Received Recordに再現用tokenを保持し、Observed Projection／JSONLでは平文をmaskする。`SensitiveProjectionFilterTest`もOmit／Mask／HMACを検証する。 |
| Manifest／Container Compileが成功する | Satisfied | Sample E2EがOperation Manifest、HTTP Manifest、Symfony DI Containerを同じBuild IDでcompileし、Production Artifact Loaderからのみ起動する。 |
| Unit TestとIntegration Testが通る | Satisfied | MVP CloseoutでSample E2E、全PHPUnit、Mago、Deptrac、Composer validationを再実行する。結果はCloseout Reportと`develop/STATE.md`に保存する。 |

## Implemented MVP Surface

- PSR-7／15／17 HTTP boundary and FastRoute compiled dispatcher data
- Typed Operation, OperationValue, Handler, OperationResult, and Outcome contracts
- Inline lifecycle execution and JSON responses
- PostgreSQL Deferred acceptance, claim, retry, heartbeat, fencing, crash recovery, dead letter, and typed outcome storage
- Canonical Journal with operation-scoped sequence numbers
- Sensitive Observed Projection and Monolog JSONL application/system logs
- Versioned Operation／HTTP manifests and compiled Symfony DI container
- Doctrine versioned PostgreSQL migrations
- Retention plan, dry-run／confirm purge, holds, payload tombstones, journal／outcome／dead-letter deletion, audit, and maintenance scheduler
- FrankenPHP reference HTTP runtime and explicit worker CLI

## Reproduce

From the repository root in WSL2:

```bash
docker compose run --rm app vendor/bin/phpunit --filter MvpSample
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
```

Build, migration, worker, and runtime composition are described in:

- [MVP Sample](mvp-sample.md)
- [Runtime Bootstrap](runtime-bootstrap.md)
- [Database Migrations](database-migrations.md)
- [Data Retention](retention.md)
- [Architecture](../internals/architecture.md)

## Sensitive Data Boundary

Canonical Journal is the reproducible source of truth and may retain a sensitive operation value according to application access, encryption, and retention policy. Observed projections, application logs, and retention system logs receive filtered or explicitly payload-free data. Do not describe this boundary as “sensitive data is never stored in Journal.”

## Known MVP Constraints

- No authentication or authorization implementation
- No Deferred status/outcome HTTP endpoint or generated client SDK
- No transactional outbox persistence/relay
- No encryption adapter for Canonical Journal or transport payloads
- No remote OpenTelemetry, CloudWatch, SQS, Kafka, SQLite, or MySQL adapter
- No observer replay CLI, generator, admin UI, or Scheduled Operation Strategy
- No production certification or compatibility promise beyond the marked Public API and Semantic Versioning contract

These remain post-MVP work. Their absence does not invalidate the agreed MVP Definition of Done.
