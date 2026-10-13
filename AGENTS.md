# Agent Instructions

このRepositoryでは、CodexがOrchestrator兼Reviewerとなり、Production Codeの実装はTask Packet単位でCodex GPT-5.6 Luna Max workerへ依頼する。

Model／Profileの正本はRepository内設定とする。

- Orchestrator／調査／Orchestrator Review: `.codex/config.toml`の`gpt-6-astra`／`max`
- Production Implementation Worker: `.codex/agents/worker.toml`の`gpt-5.6-luna`／`max`
- Documentation Reviewer: `.codex/agents/documentation-reviewer.toml`の`gpt-6-astra`／`max`

Worker ProcessへModel Metadataが環境変数として公開されないことだけをBlockerにしない。CodexがModelまたはReasoning Effortの設定値を拒否した場合、指定ModelまたはReasoning Effortへアクセスできない場合、または別Model／Reasoning EffortへFallbackしたことを実行環境が明示した場合だけ、OrchestratorへBlockerとして返す。

Documentation ReviewerはRead-onlyで、Documentation本文やProduction Codeを修正しない。FindingをEvidence付きでOrchestratorへ返し、Task Packet化とAcceptanceはOrchestratorが行う。Review Contractの正本は`develop/spec/92-documentation-review-agent.md`とする。

検証の選択、資料管理、チーム運用の正本は`develop/spec/109-ai-development-workflow.md`とする。個別Taskの凍結済みAcceptanceを暗黙に省略しない。

## Source of Truth

作業前に、次の順で必要な文書を確認する。

1. `AGENTS.md`
2. `develop/STATE.md`
3. 現在の `develop/orchestration/tasks/<task-id>.md`
4. `develop/spec/README.md` とTaskから参照された `develop/spec/*.md`
5. 必要な判断経緯を記録した `develop/decisions/*.md`

確定仕様の正本は `develop/spec/` である。`develop/decisions/` は判断経緯、`develop/TODO.md` は未決事項と作業、`docs/` は読者向けの説明を扱う。

仕様と実装が矛盾する場合、Production Codeだけで解決しない。Reportへ記録し、Orchestrator Codexへ判断を返す。

最初は現在のSTATEと担当Taskだけを読み、Indexから関連する仕様・Decisionへ進む。履歴・全Report・巨大仕様を一括で読まず、`rg`で必要な節を絞る。過去のModel値やRelease値は現在のProfile／`develop/spec/release-authority.json`を上書きしない。

## Workspace

すべてのCommandはWSL2内のRepository Rootで実行する。

```text
/home/kubotak/projects/blackops
```

Windows側や `/mnt/c` に別のWorking Treeを作らない。Credential、Token、SecretをRepositoryへ保存しない。

## Task Workflow

1. Task PacketのGoal、Scope、変更可能File、Acceptance Criteriaを確認する
2. Task Packetで許可された範囲だけを変更する
3. TaskのVerification Planで割り当てられたTestと品質Commandを実行する
4. `develop/orchestration/reports/<task-id>.md` を作成または更新する
5. OrchestratorがReportを確認し、`develop/STATE.md` を更新する
6. Orchestrator CodexのReviewを待つ

GPT-5.6 Luna Max workerはReview前にCommitしない。範囲外の修正が必要な場合は実装を広げず、ReportのBlockerとして返す。別Model／Reasoning Effortへの暗黙Fallbackは禁止するが、Metadata非公開を理由に自己判定で停止しない。

## Team and Verification

- 通常はOrchestratorとLuna workerの2名。管理文書だけの小変更はOrchestratorが担当する。
- 独立した実装・調査が並行できるときは、担当Fileと成果物を分けてsub-agentへ委譲する。目安は合計4名まで、実行環境の上限を優先する。同じ疑問を複数Agentへ重複調査させない。
- TaskにFile／Report／検証の担当と共有DB・生成先の使用者を明記する。STATE・統合・AcceptanceはOrchestratorだけが所有する。
- Reviewerは変更範囲と未解決Findingを確認する。Transaction／Security、Documentation等の専門Reviewerは影響がある場合または個別Taskが要求する場合に追加する。
- 各Commandの入力・環境・結果・所要時間・logをReportに残す。同じ入力の成功した検証は担当者を替えただけで再実行しない。
- 作業中は関連Testを使い、全件Testは広い影響がある変更の確定時に一度実行する。全件に含まれる同条件の部分TestをFinalで重ねない。Testだけの変更で解析対象が不変なら静的解析を再実行しない。
- 不明な影響、失敗、新しい変更があれば必要な検証を広げる。管理上の文言修正ではProduct検証を失効させない。Releaseのsame-SHA CI／export条件は維持する。
- 長いCommandは開始時にlogと実行状態を共有する。停滞時は失敗箇所・実行中process・入力差分を調べ、根拠なく全工程を再起動しない。

## Commit Checkpoints

Ownerの明示的な留保がない変更作業では、Orchestratorがまとまった作業単位ごとにコミットする。
単位に割り当てた検証とOrchestrator Reviewを終えたら、その時点でコミットし、上位TaskやRelease全体の完了まで待たない。
仕様・Decision・Task/Reportを揃えた管理変更も、一つの完結した成果として扱う。

- 次の独立した作業単位へ進む前とSession終了前に、現在のworktree/branch、index、未コミット差分を確認する。
- 完了した複数のTaskをまとめて後回しにしない。未完了の検証やFindingがある変更は、完了した別単位へ混ぜない。
- stagingは対象pathまたはhunkを明示し、既存の無関係差分を保護する。混在worktree全体の一括stagingは禁止する。
- 別worktreeでコミットした場合は、そのpath/branch/commit SHAを報告し、元worktreeの差分も整理済みであるかのように扱わない。
- 未コミット差分を残す場合は、対象Task/ファイル群、残す具体的理由、コミットに必要な次のActionをReport/STATEとUserへの報告に記録する。
- コミット済みかはGitで確認する。完了報告には作業単位とcommit SHAを含め、push済みはremoteで確認できた場合だけ記載する。

WorkerのReview前コミット禁止は維持する。Commit、Acceptance、push、tag、release、deployは別の状態として扱う。
このルール自体はpushや公開の追加許可を意味しない。既存のOwner指示で許可された範囲は継続して実行する。
詳細は[仕様109](develop/spec/109-ai-development-workflow.md#commit-boundaries)を正本とする。

## Checkpoint

次の時点で `develop/STATE.md` を更新する。

- Task開始時
- Task完了時
- Blocker発生時
- Session終了前

`Updated At` は、秒とUTC Offsetを含むISO 8601形式で記録する。

```text
2026-07-05T16:18:27+09:00
```

再開時はCheckpointを推測で上書きせず、Task Packet、Report、Working Treeと照合する。

STATEは現在の要約だけを200行かつ24KiB以内で保持し、過去の本文を追記しない。Orchestratorが`develop/orchestration/STATE.template.md`を参考に次の要約を作り、`node develop/orchestration/state.mjs --write /tmp/blackops-state-next.md --expect-sha <現在のSHA256>`で更新する。旧本文は自動archiveされる。更新前後の`--check`、履歴の探し方は`develop/orchestration/README.md`を参照する。WorkerはSTATEへ直接書き込まない。

## Completion Report

Reportには少なくとも次を記載する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action

Commandを実行できなかった場合は、未実行であることと理由を明記する。

Task完了時のUser向け最終報告でも、次を必ず明記する。

- 残り工程: 完了したTaskより上位のGoalを達成するまでに残る工程。残りがない場合は`なし`と明記する
- Next Action: 次に取る具体的なAction。必要に応じて担当、前提条件、開始条件を含める

Task単体の完了と上位Goal全体の完了を混同しない。Taskを完了した時点で上位Goalに作業が残る場合、残り工程を省略して完了と報告しない。

## Documentation

- `docs/internal/`: Framework実装者向け
- `docs/guide/`: Framework利用者向け

公開APIや利用方法が変わるTaskでは `docs/guide/`、ArchitectureやAdapter実装が変わるTaskでは `docs/internal/` の更新要否を確認する。

## Release Documentation Impact

公開API、Capability、Release、Example、Command、Configurationを変更するTaskは、Task開始時に次を記録する。

- Release Authority tupleと変更Capability ID
- 影響する公開Source／route inventory
- 旧Version occurrenceのbefore／after分類とhistorical allowlist
- Source／Search／LLM artifactの検証結果とpositive／negative fixture
- same-SHA CI／Documentation delivery、Production deploy有無、残り工程、Next Action

Documentation-only Taskでも、影響なしと判断した理由をTask Reportへ記録する。Release closeoutはchanged-pagesだけで完了扱いにせず、全公開Sourceと生成Artifactを確認する。

## Code Comments

Production CodeとTestのComment／DocBlockへ、次のような指示書、設計書、Taskの管理番号を書かない。

- `Spec 19`、`Specification 19`
- `D050`
- `P1-002`
- `develop/TODO.md:303`

Commentは現在のCodeだけで理解できる責務、Invariant、理由を説明する。仕様との対応関係や判断経緯は `develop/spec/`、`develop/decisions/`、`docs/`、Task Reportへ記録する。

PHPのProduction Code／Testを変更したTaskは完了前に次のCheckを実行する。入力が不変の成功ログは再利用できる。管理文書・Node toolingだけの変更には、その変更に対応した検証をTaskへ定める。

```bash
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
```

既存違反を発見した場合、そのTaskで変更可能なFileは修正する。範囲外のFileは勝手に変更せずReportへ記録する。
