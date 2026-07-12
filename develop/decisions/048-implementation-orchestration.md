# D048: Implementation Orchestration

Status: Decided

## Context

MVP実装では、Orchestrator Codexが指示出し、Task Packet作成、Review、Acceptance判定を担当し、Production Codeの実装はTask Packet単位でCodex GPT-5.4-mini workerへ依頼する。

2026-07-08改定: OpenCode／GLM-5.2を実装主体とする方式を廃止した。以後、本Decision内の正本はCodex GPT-5.4-mini workerへの依頼方式とする。

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
| Implementation Worker | Codex GPT-5.4-miniへTask Packet単位で依頼する |
| OpenCode CLI | 旧方式の履歴として導入済み。新方式では使用しない |
| GLM-5.2 Provider | 旧方式の履歴として扱う。新方式では使用しない |
| PHP／Composer／Mago | Hostへ導入せず、Docker ComposeのApplication Containerで実行する |
| PHPUnit／Deptrac | Composer Project作成後、Application Containerへ導入する |
| Docker Engine／Compose | WSL2 Ubuntu内部へDocker EngineとCompose Pluginを導入する。Docker Desktopは使用しない |

## Execution Environment

Orchestrator Codexと実装担当Codexは同じWSL2 DistributionとRepository Pathを使用する。PHP、Composer、Mago、PHPUnit、DeptracはDocker ComposeのApplication Container内で実行し、PostgreSQLもCompose Serviceとして起動する。

RepositoryはWSL2のLinux File System上にある次のPathを正本とする。

```text
/home/kubotak/projects/blackops
```

Windows側のOneDriveや `/mnt/c` 上へ別のWorking Treeを用意せず、同時編集もしない。Windows Applicationから参照する必要がある場合も、実装CommandはWSL2 Shellから実行する。

## Resume Sequence

Desktop側の準備手順は行わず、WSL2内のRepository Rootから次の順で再開する。

1. `develop/STATE.md`、現在のTask Packet、Report、Working Treeを確認する
2. 実装が必要な場合は、Task Packet単位でCodex GPT-5.4-mini workerへ依頼する
3. Docker DaemonとDocker Composeの接続を確認する
4. PHP 8.5と開発Toolを実行するApplication Container、PostgreSQL Serviceを定義する
5. `develop/orchestration/tasks/` と `develop/orchestration/reports/` を作成する
6. Phase 0の最初のTask Packetを作成する
7. GPT-5.4-mini workerまたは実装担当Codexが作成したReport、Diff、Test結果をOrchestrator CodexがReviewする

Credential、Token、SecretはRepositoryへ保存しない。外部Serviceの設定値が必要な場合は、環境変数またはTool側のCredential Storeを使用する。

## Roles

### Codex

- 確定仕様とDecisionの管理
- 実装Taskの分割と依存順序の管理
- Task PacketとAcceptance Criteriaの作成
- GPT-5.4-mini workerまたは実装担当Codexの変更内容のReview
- Mago、PHPUnit、Deptracによる受入確認
- 不一致を新しいDecisionへ戻す
- Taskの完了判定

### Codex GPT-5.4-mini Worker

- Task Packetで指定された範囲の実装
- Test追加
- Mago／PHPUnit／Deptracの実行
- 変更File、判断、Test結果、残課題の報告
- 仕様外変更を行わず、疑義をCodexへ返す

Orchestrator Codexは、Userが明示的に依頼した場合、またはGPT-5.4-mini workerへ依頼できない場合にProduction Codeの実装主体を引き継げる。その場合もTask Packet、Report、STATE、Review境界は維持する。

## Task Packet

TaskごとにMarkdownを作成する。

```text
develop/
  STATE.md
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

## Question 1: Implementation Workerへの依頼方式

### Options

- A: Orchestrator CodexがTask Packetを作成し、Codex GPT-5.4-mini workerへ依頼する
- B: Orchestrator CodexがTask Packetを作成し、Userが別の実装担当へ手動投入する
- C: 実装担当が別Git Branch／PRで実装し、Orchestrator CodexがReviewする

### Recommendation

Aを推奨する。

Task Packet、Report、STATEの流れを維持しながら、OpenCode／GLM-5.2 Provider設定に依存しない運用へ移行できる。GPT-5.4-mini workerへ直接依頼できない場合は、Orchestrator Codexが実装を引き継ぐか、UserがTask Packetを手動投入する。

[ANSWER]

A
OpenCode／GLM-5.2方式から、Codex GPT-5.4-mini workerへ依頼する方式へ変更する。
再開できる仕組み、AGENTS.md、内部実装文書、利用者向け文書は引き続き維持する。

[/ANSWER]

## Question 1-2: WSL2 Workspace

### Options

- A: WSL2のLinux File System上にRepositoryを置き、Orchestrator Codexと実装担当Codexが同じPathを使う
- B: 現在のOneDrive WorkspaceをWSL2から `/mnt/c` 経由で使う
- C: 仕様Repositoryと実装Repositoryを分離する

### Recommendation

Aを推奨する。

すでにWSL2側Repositoryへ一本化されており、このPathをOrchestrator Codexと実装担当Codexが共有する。

[ANSWER]

A

Repository Path:

```text
/home/kubotak/projects/blackops
```

[/ANSWER]

## Question 2: 変更の受入

### Options

- A: GPT-5.4-mini workerまたは実装担当Codexの各Task完了後、Orchestrator CodexがDiffとTestを確認してから次Taskを発行する
- B: 全Phaseを一括実装して最後だけReviewする
- C: 実装担当自身の完了判定だけで次へ進む

### Recommendation

Aを推奨する。設計逸脱を小さい単位で止められ、Task間の前提を毎回更新できる。

[ANSWER]

A
トークンの利用上限があるので、再開できるようにstate.mdみたいなチェックポイント保存ができるようにしたいですね

[/ANSWER]

## Question 3: Commit

### Options

- A: GPT-5.4-mini workerまたは実装担当Codexは編集とTestまで行い、CommitはOrchestrator CodexのReview後に行う
- B: 実装担当がTaskごとにCommitする
- C: Phase全体で一つのCommitにする

### Recommendation

Aを推奨する。未Reviewの変更を履歴へ確定せず、Commit Messageと変更単位をCodexがAcceptance Criteriaに合わせて管理できる。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

Orchestrator CodexがTask Packetを作成し、Production Codeの実装をCodex GPT-5.4-mini workerへ依頼する方式を採用する。

Orchestrator Codexと実装担当Codexは、WSL2内の同一Repositoryを使用する。

```text
/home/kubotak/projects/blackops
```

Taskは一括実装せず、次の単位で進める。

1. CodexがTask PacketとAcceptance Criteriaを作成する
2. GPT-5.4-mini workerまたは実装担当Codexが指定範囲を実装し、TestとReportを作成する
3. Orchestrator CodexがDiff、仕様適合性、Test結果をReviewする
4. 不一致があれば同じTaskを修正し、合格後に次Taskへ進む
5. CommitはCodexのReview完了後に行う

Token上限やSession終了後も再開できるよう、`develop/STATE.md` を実装Orchestrationの正本となるCheckpointとして管理する。少なくとも次を記録する。

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

各Task終了時、Block発生時、Sessionを終了する前に、Orchestrator Codexまたは実装担当Codexが `develop/STATE.md` を更新する。再開時は、最初に `AGENTS.md`、`develop/STATE.md`、現在のTask Packet、Reportを読み、状態を再構築する。

Repository Rootに `AGENTS.md` を設け、Orchestrator Codexと実装担当Codexが共通して従う次の規約を記載する。

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

`develop/decisions/` は判断過程、`develop/spec/` は確定仕様の正本として維持し、`docs/internals/` は実装時の理解を助ける説明、`docs/guide/` は公開APIの利用方法を扱う。

[/DECISION]

## Consequences

[CONSEQUENCES]

- GPT-5.4-mini workerまたは実装担当Codexの変更をTask単位でReviewし、設計逸脱を次Taskへ持ち越さない。
- 未Reviewの変更はCommitせず、Acceptance CriteriaとCommit境界を一致させる。
- SessionやToken上限をまたいでも、`develop/STATE.md` から作業を再開できる。
- Task Packet、Report、Checkpointの更新もTask完了条件に含まれる。
- `AGENTS.md` と再開用Checkpointを実装依頼方式の変更に合わせて維持する必要がある。
- 内部実装文書と利用者向け文書を混在させず、それぞれの読者に必要な情報を段階的に整備する。
- Credential、Token、SecretはRepositoryへ保存しない。

[/CONSEQUENCES]
