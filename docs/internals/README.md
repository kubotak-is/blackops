# BlackOps Internals

Framework実装者向けのArchitectureと実装ガイドを管理する。

確定仕様の正本は `develop/spec/`、判断経緯は `develop/decisions/` である。このDirectoryでは、それらを実装へ落とし込むための構造、主要な処理フロー、Extension Point、Adapter実装上の注意を説明する。

## Topics

- [Architecture](architecture.md)
- [Development Setup](development-setup.md)
- [Bootstrap](bootstrap.md)
- [Application Bootstrap](application-bootstrap.md)
- [Project Generators and Framework Update Boundary](project-generators.md)
- [FrankenPHP Reference Runtime](frankenphp-runtime.md)
- [Runtime Dependencies](runtime-dependencies.md)
- [Core Contracts](core-contracts.md)
- [Execution Context](execution-context.md)
- [Operation Envelope](operation-envelope.md)
- [Handler and Result](handler-result.md)
- [Operation Metadata](operation-metadata.md)
- [Operation Registry](operation-registry.md)
- [In-Memory Execution Transport](in-memory-execution-transport.md)
- [Inline Dispatcher](inline-dispatcher.md)
- [Journal Contracts](journal-contracts.md)
- [Journal Record](journal-record.md)
- [Inline Journal Factory](inline-journal-factory.md)
- [Journal Ports](journal-ports.md)
- [Sensitive Projection](sensitive-projection.md)
- [JSONL Journal Observer](jsonl-journal-observer.md)
- [Execution Scoped Logger](execution-scoped-logger.md)
- [Monolog JSONL Backend](monolog-jsonl-backend.md)
- Package and Namespace Architecture
- Operation Dispatch Flow
- Journal and Transaction Flow
- PostgreSQL Adapter
- [PostgreSQL Journal Store](postgresql-journal-store.md)
- [Database Migrations](database-migrations.md)
- [MVP End-to-End](mvp-e2e.md)
- [Installed Application Status and Phase 7 Evidence](../guide/installed-application-status.md)
- [Skeleton Publication](skeleton-publication.md)
- [Typed Outcome Store](outcome-store.md)
- [HTTP API Slice](http-api-slice.md)
- [Retention Policy](retention-policy.md)
- [Retention Hold](retention-hold.md)
- [Retention Purge Audit](retention-purge-audit.md)
- [Retention Plan](retention-plan.md)
- [Maintenance Scheduler](maintenance-scheduler.md)
- [Worker and Recovery](worker-runtime.md)
- [Testing and Architecture Verification](core-contracts.md#public-api-architecture-guard)
