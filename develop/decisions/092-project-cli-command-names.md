# D092: Project CLI Command Names

Status: Partially Superseded by D094

## Supersession

D094は`1.1.0`で旧`blackops:*` Project CLI Aliasとその予約を削除する。PrefixなしCanonical Command、Project Root `blackops` Entrypoint、重複Prefixを避ける命名方針は維持する。

## Context

D083でInstalled ApplicationのConsole EntrypointをProject Rootの`blackops`へ移し、公式形式を`php blackops <command>`とした。一方、Framework標準Commandは引き続き`blackops:build:compile`のようなVendor Prefixを持つため、`php blackops blackops:build:compile`となり、入口とCommand Namespaceが重複している。

UserはProject CLIのCanonical表記を`php blackops build:compile`へ短縮することを決定した。

## Decision

[DECISION]

1. Project Rootの`blackops`が公開するFramework標準CommandのCanonical名から`blackops:` Prefixを除く。
2. Canonical Command Setは`build:compile`、`operation:list`、`database:status`、`database:migrate`、`worker:run`、`retention:plan`、`retention:purge`、`scheduler:run`、`scheduler:daemon`とする。
3. `make:operation`と`make:migration`はすでに重複がないため変更しない。
4. Stable `1.0.0`からFrameworkをUpdateする既存ApplicationのScriptを壊さないため、従来の`blackops:*`名は互換Aliasとして維持する。新規Guide、Example、Skeleton、Help例ではCanonical名だけを案内する。
5. Canonical名と互換AliasはどちらもFramework予約名とし、Application独自Commandによる上書きを拒否する。
6. Installed Applicationへ登録しない低レベルCompiler Commandの公開範囲は変更しない。Project CLIの利用者向けCommandだけを本Decisionの対象とする。

[/DECISION]

## Consequences

[CONSEQUENCES]

- 新規Applicationでは`php blackops build:compile`のように入口名を一度だけ書けばよい。
- 既存ApplicationはFramework Update後も`blackops:*` Aliasを使える。
- Command一覧、Help、README、Guide、Quickstart、Compose Process、Setup、Consumer Testを同じ変更単位で更新する。
- 将来の新しいProject CLI Commandへ`blackops:` Prefixを付けない。

[/CONSEQUENCES]

## Supersedes

Command名に限り、D068、D080、D083の`blackops:*`表記を置き換える。Entrypoint、Lazy Composition、Generator、Migrationの責務境界は維持する。

## References

- [D068 Public Console Kernel Composition](068-public-console-kernel-composition.md)
- [D080 Project Generator Command Contract](080-project-generator-command-contract.md)
- [D083 Project Root BlackOps Entrypoint](083-project-root-blackops-entrypoint.md)
- [Public Console Kernel Composition](../spec/48-public-console-kernel-composition.md)
