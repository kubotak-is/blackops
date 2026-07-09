# BlackOps Internals

Framework実装者向けのArchitectureと実装ガイドを管理する。

確定仕様の正本は `spec/`、判断経緯は `decisions/` である。このDirectoryでは、それらを実装へ落とし込むための構造、主要な処理フロー、Extension Point、Adapter実装上の注意を説明する。

## Planned Topics

- [Development Setup](development-setup.md)
- [Bootstrap](bootstrap.md)
- [Runtime Dependencies](runtime-dependencies.md)
- [Core Contracts](core-contracts.md)
- [Execution Context](execution-context.md)
- [Operation Envelope](operation-envelope.md)
- [Handler and Result](handler-result.md)
- [Operation Metadata](operation-metadata.md)
- [Operation Registry](operation-registry.md)
- [Inline Dispatcher](inline-dispatcher.md)
- [Journal Contracts](journal-contracts.md)
- [Journal Record](journal-record.md)
- [Inline Journal Factory](inline-journal-factory.md)
- [Journal Ports](journal-ports.md)
- [Sensitive Projection](sensitive-projection.md)
- [JSONL Journal Observer](jsonl-journal-observer.md)
- [Execution Scoped Logger](execution-scoped-logger.md)
- Package and Namespace Architecture
- Operation Dispatch Flow
- Journal and Transaction Flow
- PostgreSQL Adapter
- [PostgreSQL Journal Store](postgresql-journal-store.md)
- [HTTP API Slice](http-api-slice.md)
- Worker and Recovery
- Testing and Architecture Verification
