# Namespace Dependencies

## 許可する依存方向

```text
Core       -> 外部Adapter Namespaceへ依存しない
Scheduling -> Core
Database   -> Core, Library
Journal    -> Core
Execution  -> Core, Journal
Transport  -> Core, Journal, Execution
Http       -> Core, Execution
Logging    -> Core, Journal
Console    -> Core, Journal, Execution, Transport
StorageProtection -> Core
Internal   -> 対応する公開Namespaceおよび採用Library（Scheduled RuntimeはSchedulingへ依存可能）
```

矢印は左側が右側へ依存できることを表す。記載のない逆向き依存と循環依存は禁止する。

公開APIのSignatureへ `BlackOps\Internal` の型を露出させてはならない。

`BlackOps\Database\Seeder`と`SeederRunner`はCoreの`#[PublicApi]`だけへ依存する。Compiled Locator、Runner実装、Root Runtimeは`BlackOps\Internal`へ置き、Database Public NamespaceからInternalへ逆依存しない。

## 検証

Deptracを開発依存として採用し、NamespaceをLayerとして定義する。

- 設定は `deptrac.yaml` としてRepositoryで管理する
- CIで解析を実行する
- 違反がある場合はCIを失敗させる
- Namespaceを追加する場合はLayerとRulesetも更新する
- `StorageProtection` Public ContractはCoreのTenant Identity／PublicApi Attributeだけへ依存し、暗号実装はInternalへ閉じる

Dependency競合が生じる場合は、PHARまたは分離したComposer Binaryとして導入する。

対象RuntimeであるPHP 8.5の構文を正しく解析できることをCIで継続的に確認する。
