# D066: Development Metadata Layout

Status: Decided

Updated by: [D067 Legacy Setup Helper Removal](067-legacy-setup-helper-removal.md)

D067はDecision項目10と11、および「DB Smoke TestとDocker Install Scriptは使用中」というConsequenceを更新する。`docker/` と `scripts/` は通常のBuild、Test、Runtimeから利用されていないため削除した。

## Context

Framework利用者向けのRuntime／Guideと、Framework開発時だけ利用する仕様、Decision、Task Packet、CheckpointがRepository Rootに混在していた。

開発Metadataを一箇所へ集約し、Repository RootではProduction Code、Runtime、Example、Build設定を見つけやすくする。

あわせて `docker/`、`migrations/`、`scripts/`、`SPECIFICATION.md` の利用状況を監査した。

## Decision

[DECISION]

1. Framework開発Metadataを `develop/` へ集約する。
2. `decisions/` は `develop/decisions/` へ移動する。
3. `spec/` は `develop/spec/` へ移動する。
4. `orchestration/` は `develop/orchestration/` へ移動する。
5. Checkpointは `develop/orchestration/` から分離し、`develop/STATE.md` とする。
6. `DOCS.md` と `TODO.md` は `develop/DOCS.md`、`develop/TODO.md` へ移動する。
7. `AGENTS.md` はRepository Rootに残し、新しいSource of Truth Pathを示す。
8. `SPECIFICATION.md` は `develop/spec/README.md` と役割が重複するため削除する。
9. `migrations/` はFramework-owned Doctrine Migrationの実体であり、Runtime Codeから読み込まれるためRepository Rootに残す。
10. `docker/` のDB Smoke TestはDevelopment Setupから使用されるため残す。
11. `scripts/` のWSL2 Docker Install ScriptはDevelopment Setupから使用されるため残す。
12. `docs/` はFramework利用者／実装者向けDocumentationとして今回の `develop/` 集約対象に含めない。

[/DECISION]

## Consequences

[CONSEQUENCES]

- Framework開発の再開導線は `AGENTS.md` -> `develop/STATE.md` -> `develop/orchestration/tasks/` となる。
- 確定仕様と判断履歴は `develop/spec/` と `develop/decisions/` にまとまる。
- Rootの薄い `SPECIFICATION.md` を経由せず、`develop/spec/README.md` を直接参照する。
- Docker Build Contextでは `develop/` 全体を除外できる。
- Migration、DB Smoke Test、Docker Install Scriptは使用中のため削除しない。
- Historical Task／Report内のRepository Pathも新しい配置へ同期する。

[/CONSEQUENCES]
