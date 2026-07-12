# D057: Database Access and Migration Library

Status: Decided

## Context

Phase 3ではPostgreSQL上でOperation State、Canonical Journal、Claim、Fencing、Worker実行を同一Transaction境界で扱う必要がある。

これまでのPostgreSQL Adapterは生PDOで実装しているが、今後はMigration管理、Transaction境界、Schema状態確認、Deployment時の明示的なSchema適用が重要になる。

BlackOpsはSymfony Componentを採用しているが、Symfony full-stack Applicationを前提にしないHeadless Frameworkである。

## Decision

[DECISION]

BlackOpsのFramework-owned PostgreSQL Adapter内部では、Database接続、Transaction、低レベルSQL実行の基盤としてDoctrine DBALを採用する。

Framework SchemaのMigration管理にはDoctrine Migrationsを採用する。

Doctrine ORMは採用しない。Operation State、Journal、Claim、Fencingは業務EntityではなくFramework内部の耐障害Stateであり、明示SQLとTransaction境界を優先する。

Symfony full-stack、DoctrineBundle、DoctrineMigrationsBundleは必須依存にしない。Symfony Applicationで利用する場合のIntegrationは後続AdapterまたはGuideで扱う。

Symfony ComponentのMajor Version不整合を避けるため、Doctrine Migrationsが利用するSymfony StopwatchはRoot Constraintで7.4 LTS系列へ固定する。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Migration versioning、status、dry-run、deployment時の明示適用をDoctrine Migrationsへ寄せられる。
- DB接続とTransaction制御をPDO直書きから段階的にDBALへ移行できる。
- PostgreSQL固有のSQL、`FOR UPDATE SKIP LOCKED`、`bytea`、`timestamptz` は引き続き明示SQLで扱える。
- ORMを避けることで、Framework内部StateのSchemaとLocking規則を曖昧にしない。
- Symfony full-stackに依存しないため、Next/Nuxt/SvelteKit/Astro BFFや任意PHP Runtimeから利用しやすい。
- deptracではTransportからLibraryへの依存を許可する必要がある。
- 既存PDO実装は即時削除せず、後続TaskでDBAL Connectionへ段階移行する。

[/CONSEQUENCES]
