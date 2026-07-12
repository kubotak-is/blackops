# D072: Skeleton Empty Directory Policy

Status: Decided

## Context

Phase 7 CloseoutでInstalled Treeを照合したところ、上位Layout仕様は `app/Infrastructure/` と `migrations/` をTreeへ記載していたが、後発の具体Quickstart仕様と実Source Treeはどちらも配布していなかった。

`app/Infrastructure/` はApplicationがPersistenceやExternal Serviceの実装を必要とする場合の任意配置先であり、Starter FeatureはInfrastructure Adapterを必要としない。Application固有MigrationもQuickstartにはなく、Framework-owned MigrationはFramework Package内部からPublic Migration Commandが実行する。

未使用Directory／Fileを配布せず、必要になった時点でApplicationが追加する既存方針に合わせ、公式Skeletonの必須Treeを明確化する。

## Decision

[DECISION]

1. `app/Infrastructure/` は推奨可能な任意配置先であり、公式Skeletonの必須配布Directoryに含めない。
2. Application固有の `migrations/` は任意配置先であり、Migrationを含まない公式Skeletonへ空Directoryを配布しない。
3. Framework-owned MigrationはFramework Package内部に保持し、Public Database Migration Commandから実行する。
4. 必要になったApplicationは `app/Infrastructure/` と `migrations/` を追加できる。
5. Installed Treeの一致確認は、任意Directoryを除いた具体Quickstart Treeを対象にする。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Install直後のApplication Treeに責務を持たない空Directoryを置かない。
- Infrastructure AdapterやApplication Migrationを追加する将来のApplication Layoutを制限しない。
- 上位Layout仕様とPhase 7の具体Quickstart Treeの差異が解消される。
- Phase 8は現行Quickstart TreeをそのままSkeleton Package Sourceとして扱える。

[/CONSEQUENCES]

## References

- [Installed Application Layout and Bootstrap](../spec/43-installed-application-layout-and-bootstrap.md)
- [Feature-first Quickstart Application](../spec/49-feature-first-quickstart-application.md)
- [Installed Application Status](../../docs/guide/installed-application-status.md)
