# Agent Instructions

このRepositoryでは、CodexがOrchestrator兼Reviewerとなり、Production Codeの実装はTask Packet単位でCodex GPT-5.6 Luna High workerへ依頼する。

Model／Profileの正本はRepository内設定とする。

- Orchestrator: `.codex/config.toml`の`gpt-5.6-sol`／`high`
- Worker: `.codex/agents/worker.toml`の`gpt-5.6-luna`／`high`

Worker ProcessへModel Metadataが環境変数として公開されないことだけをBlockerにしない。Codexが設定値を拒否した場合、指定Modelへアクセスできない場合、または別ModelへFallbackしたことを実行環境が明示した場合だけ、OrchestratorへBlockerとして返す。

## Source of Truth

作業前に、次の順で必要な文書を確認する。

1. `AGENTS.md`
2. `develop/STATE.md`
3. 現在の `develop/orchestration/tasks/<task-id>.md`
4. `develop/spec/README.md` とTaskから参照された `develop/spec/*.md`
5. 必要な判断経緯を記録した `develop/decisions/*.md`

確定仕様の正本は `develop/spec/` である。`develop/decisions/` は判断経緯、`develop/TODO.md` は未決事項と作業、`docs/` は読者向けの説明を扱う。

仕様と実装が矛盾する場合、Production Codeだけで解決しない。Reportへ記録し、Orchestrator Codexへ判断を返す。

## Workspace

すべてのCommandはWSL2内のRepository Rootで実行する。

```text
/home/kubotak/projects/blackops
```

Windows側や `/mnt/c` に別のWorking Treeを作らない。Credential、Token、SecretをRepositoryへ保存しない。

## Task Workflow

1. Task PacketのGoal、Scope、変更可能File、Acceptance Criteriaを確認する
2. Task Packetで許可された範囲だけを変更する
3. 必須Testと品質Commandを実行する
4. `develop/orchestration/reports/<task-id>.md` を作成または更新する
5. `develop/STATE.md` を更新する
6. Orchestrator CodexのReviewを待つ

GPT-5.6 Luna High workerはReview前にCommitしない。範囲外の修正が必要な場合は実装を広げず、ReportのBlockerとして返す。別Modelへの暗黙Fallbackは禁止するが、Metadata非公開を理由に自己判定で停止しない。

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

## Documentation

- `docs/internal/`: Framework実装者向け
- `docs/guide/`: Framework利用者向け

公開APIや利用方法が変わるTaskでは `docs/guide/`、ArchitectureやAdapter実装が変わるTaskでは `docs/internal/` の更新要否を確認する。

## Code Comments

Production CodeとTestのComment／DocBlockへ、次のような指示書、設計書、Taskの管理番号を書かない。

- `Spec 19`、`Specification 19`
- `D050`
- `P1-002`
- `develop/TODO.md:303`

Commentは現在のCodeだけで理解できる責務、Invariant、理由を説明する。仕様との対応関係や判断経緯は `develop/spec/`、`develop/decisions/`、`docs/`、Task Reportへ記録する。

Task完了前に、少なくとも次のCheckを実行する。

```bash
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
```

既存違反を発見した場合、そのTaskで変更可能なFileは修正する。範囲外のFileは勝手に変更せずReportへ記録する。
