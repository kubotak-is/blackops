# <Task ID>: <Task Name>

Status: Ready

## Goal

<!-- 一つの成果と上位Goalとの関係。 -->

## Scope and Authority

- In Scope / Out of Scope:
- Relevant Specifications / 必要な節:
- Decision / 承認された前提:
- Files Allowed to Change:

許可Fileが足りない場合は範囲を広げず、必要な差分と理由を返す。
開始時の既存変更を記録し、他Taskの変更を保持する。

## Team and Ownership

| 担当 | 成果物 / 所有File | 検証 | 共有resource |
| --- | --- | --- | --- |
| Orchestrator | Task、STATE、統合、Acceptance | 差分と証拠の監査 | STATE唯一のwriter |
| Worker |  |  |  |

<!-- 通常2名。独立作業がある場合だけ追加。専門Reviewの範囲と理由を明示。 -->
Profile値はRepository設定を参照。長いCommandはlog/sessionを共有する。
Workerは担当Reportまたはchatでhandoffし、STATEを直接更新しない。
WorkerはReview前にcommitしない。

## Acceptance Criteria

- [ ]

## Verification Plan

- Change class / 影響範囲:
- 選択理由 / 全件が必要な理由または不要な理由:
- Input: source/test/fixture/bootstrap/config/lockとhashの記録先:
- Environment: runtime/image/tool、DB、memory/timeout、排他条件:
- Review: 担当領域、対象候補、追加laneの理由:

| 段階 | literal Command | 担当 | 再実行を必要にする変更 |
| --- | --- | --- | --- |
| Iteration |  |  |  |
| Final |  |  |  |

同じ入力の成功結果は参照し、Finalで同条件の部分Testと全件Testを重ねない。
管理記録の変更でProduct検証を失効させない。失敗・環境差・契約変更の影響は再評価する。
PHP変更には `docker compose run --rm app mago format --check src tests` と以下を含める。

```bash
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

管理文書だけならリンク/形式/STATE check、開発Toolなら専用Testを選ぶ。
自作検証器や全構文を網羅するTestは、必要な契約と既存Toolで不足する理由を確認してから追加する。

## Failure and Handoff

- 停止条件 / Orchestratorへ返す情報:
- 失敗Command、exit、log、実行中process、未実行項目:
- 再開の前提と次のliteral Command:

## Release Documentation Impact

- Authority tuple / Capability ID: 影響なしも理由を記録
- Public Source / route inventory:
- Version occurrence before/after、historical allowlist:
- Source/Search/LLM artifact、positive/negative fixture:
- same-SHA CI/Documentation delivery、Production deploy有無:

## Completion

Report: `develop/orchestration/reports/<task-id>.md`

- Summary、Changed Files、Decisions and Assumptions
- Commands and Results（入力/環境/担当/exit/時間/log/再利用元）
- Acceptance Criteria、Remaining Issues
- 残り工程、Suggested Next Action

STATEはOrchestratorがcheckpoint commandで更新する。
