# D048: Implementation Orchestration

Status: Decided

## Context

MVP実装では、Codexが指示出しとOrchestrationを担当し、実際の実装はOpenCodeのGLM-5.2へ委譲する。

実装は、すでに起動しているWSL2 Distribution内から行う。Windows Desktop、PowerShell、`wsl.exe` を実装手順の起点にはしない。

このDecisionを再開する時点のWorking Directoryは次とする。

```text
/home/kubotak/projects/blackops
```

WSL2 DistributionとRepositoryは準備済みである。以後の環境確認、Tool導入、設定、実装、Testは、このDirectoryを開いたWSL2 Shellから実行する。

## Current State

2026-07-05にWSL2内で確認した状態は次のとおり。

| Item | State |
| --- | --- |
| WSL2 Distribution | Ubuntu、準備済み |
| Repository | `/home/kubotak/projects/blackops`、準備済み |
| OpenCode CLI | `/home/kubotak/.opencode/bin/opencode`、version `1.17.9` |
| GLM-5.2 Provider | `opencode-go/glm-5.2`、認証済み、非対話実行確認済み |
| PHP／Composer／Mago | Hostへ導入せず、Docker ComposeのApplication Containerで実行する |
| PHPUnit／Deptrac | Composer Project作成後、Application Containerへ導入する |
| Docker Engine／Compose | WSL2 Ubuntu内部へDocker EngineとCompose Pluginを導入する。Docker Desktopは使用しない |

## Execution Environment

CodexとOpenCodeは同じWSL2 DistributionとRepository Pathを使用する。PHP、Composer、Mago、PHPUnit、DeptracはDocker ComposeのApplication Container内で実行し、PostgreSQLもCompose Serviceとして起動する。

RepositoryはWSL2のLinux File System上にある次のPathを正本とする。

```text
/home/kubotak/projects/blackops
```

Windows側のOneDriveや `/mnt/c` 上へ別のWorking Treeを用意せず、同時編集もしない。Windows Applicationから参照する必要がある場合も、実装CommandはWSL2 Shellから実行する。

## Resume Sequence

Desktop側の準備手順は行わず、WSL2内のRepository Rootから次の順で再開する。

1. OpenCodeのGLM-5.2 Provider設定とModel名を確認する
2. `opencode run --model <provider>/<model> ...` による非対話実行を確認する
3. Docker DaemonとDocker Composeの接続を確認する
4. PHP 8.5と開発Toolを実行するApplication Container、PostgreSQL Serviceを定義する
5. `orchestration/tasks/` と `orchestration/reports/` を作成する
6. Phase 0の最初のTask Packetを作成する
7. OpenCodeへTask Packetを渡し、Report、Diff、Test結果をCodexがReviewする

ProviderのCredentialはRepositoryへ保存しない。OpenCodeが対応するCredential StoreまたはWSL2内の環境変数を使用する。

## Roles

### Codex

- 確定仕様とDecisionの管理
- 実装Taskの分割と依存順序の管理
- Task PacketとAcceptance Criteriaの作成
- GLM-5.2の変更内容のReview
- Mago、PHPUnit、Deptracによる受入確認
- 不一致を新しいDecisionへ戻す
- Taskの完了判定

### OpenCode／GLM-5.2

- Task Packetで指定された範囲の実装
- Test追加
- Mago／PHPUnit／Deptracの実行
- 変更File、判断、Test結果、残課題の報告
- 仕様外変更を行わず、疑義をCodexへ返す

Codexは、Userが明示的に上書きしない限り、Production Codeの実装主体にならない。

## Task Packet

TaskごとにMarkdownを作成する。

```text
orchestration/
  tasks/
    P0-001-foundation.md
  reports/
    P0-001-foundation.md
```

Task Packetは次を必須とする。

```text
Goal
In Scope
Out of Scope
Relevant Specifications
Files Allowed to Change
Acceptance Criteria
Required Commands
Expected Report
```

## Question 1: OpenCodeとの実行Bridge

### Options

- A: OpenCode CLIを導入し、CodexがTask Packetを渡して直接実行する
- B: CodexがTask Packetを作成し、UserがOpenCode／GLM-5.2へ手動投入する
- C: OpenCodeが別Git Branch／PRで実装し、CodexがReviewする

### Recommendation

Aを推奨する。

Orchestrationを自動化しやすい。OpenCode CLIはWSL2内へ導入済みのため、残作業はGLM-5.2 Providerと非対話実行の確認である。準備完了まではBを暫定運用にできる。

[ANSWER]

A
簡単に再開できるような仕組みも入れてほしい。
AGENTS.mdも対応が必要かも。
また、内部実装としてのドキュメントと、利用者向けドキュメントを整備してほしい

[/ANSWER]

## Question 1-2: WSL2 Workspace

### Options

- A: WSL2のLinux File System上にRepositoryを置き、CodexとOpenCodeが同じPathを使う
- B: 現在のOneDrive WorkspaceをWSL2から `/mnt/c` 経由で使う
- C: 仕様Repositoryと実装Repositoryを分離する

### Recommendation

Aを推奨する。

すでにWSL2側Repositoryへ一本化されており、このPathをCodexとOpenCodeが共有する。

[ANSWER]

A

Repository Path:

```text
/home/kubotak/projects/blackops
```

[/ANSWER]

## Question 2: 変更の受入

### Options

- A: GLM-5.2の各Task完了後、CodexがDiffとTestを確認してから次Taskを発行する
- B: 全Phaseを一括実装して最後だけReviewする
- C: GLM-5.2自身の完了判定だけで次へ進む

### Recommendation

Aを推奨する。設計逸脱を小さい単位で止められ、Task間の前提を毎回更新できる。

[ANSWER]

A
トークンの利用上限があるので、再開できるようにstate.mdみたいなチェックポイント保存ができるようにしたいですね

[/ANSWER]

## Question 3: Commit

### Options

- A: OpenCodeは編集とTestまで行い、CommitはCodexのReview後に行う
- B: OpenCodeがTaskごとにCommitする
- C: Phase全体で一つのCommitにする

### Recommendation

Aを推奨する。未Reviewの変更を履歴へ確定せず、Commit Messageと変更単位をCodexがAcceptance Criteriaに合わせて管理できる。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

OpenCode CLIを実行Bridgeとし、CodexがTask Packetを渡してGLM-5.2を非対話実行する方式を採用する。

CodexとOpenCodeは、WSL2内の同一Repositoryを使用する。

```text
/home/kubotak/projects/blackops
```

Taskは一括実装せず、次の単位で進める。

1. CodexがTask PacketとAcceptance Criteriaを作成する
2. OpenCode／GLM-5.2が指定範囲を実装し、TestとReportを作成する
3. CodexがDiff、仕様適合性、Test結果をReviewする
4. 不一致があれば同じTaskを修正し、合格後に次Taskへ進む
5. CommitはCodexのReview完了後に行う

Token上限やSession終了後も再開できるよう、`orchestration/STATE.md` を実装Orchestrationの正本となるCheckpointとして管理する。少なくとも次を記録する。

```text
Current Phase
Current Task
Task Status
Last Accepted Task
Pending Decisions
Known Blockers
Required Next Action
Last Verification Commands and Results
Relevant Files
```

各Task終了時、Block発生時、Sessionを終了する前に、CodexまたはOpenCodeが `orchestration/STATE.md` を更新する。再開時は、最初に `AGENTS.md`、`orchestration/STATE.md`、現在のTask Packet、Reportを読み、状態を再構築する。

Repository Rootに `AGENTS.md` を設け、CodexとOpenCodeが共通して従う次の規約を記載する。

- 仕様の正本と参照順序
- Orchestrationの再開手順
- Task Packetで許可された変更範囲
- TestとReportの必須事項
- Review前にCommitしない規則
- 仕様矛盾をProduction Codeだけで解決しない規則

ドキュメントは対象読者ごとに分離する。

```text
docs/
  internals/  Framework実装者向けのArchitecture、設計根拠、Adapter実装情報
  guide/      Framework利用者向けの導入、設定、Operation作成、実行、運用情報
```

`decisions/` は判断過程、`spec/` は確定仕様の正本として維持し、`docs/internals/` は実装時の理解を助ける説明、`docs/guide/` は公開APIの利用方法を扱う。

[/DECISION]

## Consequences

[CONSEQUENCES]

- GLM-5.2の変更をTask単位でReviewし、設計逸脱を次Taskへ持ち越さない。
- 未Reviewの変更はCommitせず、Acceptance CriteriaとCommit境界を一致させる。
- SessionやToken上限をまたいでも、`orchestration/STATE.md` から作業を再開できる。
- Task Packet、Report、Checkpointの更新もTask完了条件に含まれる。
- `AGENTS.md` と再開用CheckpointをPhase 0の実装前に準備する必要がある。
- 内部実装文書と利用者向け文書を混在させず、それぞれの読者に必要な情報を段階的に整備する。
- Provider CredentialはRepositoryへ保存しない。

[/CONSEQUENCES]
