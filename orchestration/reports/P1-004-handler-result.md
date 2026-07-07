# P1-004: Handler and Result - Implementation Report

Status: Accepted

## Summary

D052で確定したOperationHandler、OperationResult、EmptyOutcome、RejectionReasonを実装した。成功、業務拒否、システム例外の境界を型で表現し、Resultの不正状態をprivate Constructorで防止する。

## Changed Files

- OperationHandler、OperationResult、EmptyOutcome
- RejectionCategory、RejectionReason
- 対応するUnit Testと内部文書
- D052、Spec 29、Task、STATE、TODO

## Public API Added

- `OperationHandler<TValue, TOutcome>`
- `OperationResult<TOutcome>`
- `EmptyOutcome`
- `RejectionCategory`
- `RejectionReason`

## Decisions and Assumptions

- OperationResultはCompleted OutcomeまたはRejected Reasonのどちらか一方だけを保持する。
- 状態不一致AccessorはLogicExceptionで拒否する。
- 引数なしCompletedもEmptyOutcomeを保持し、Completed Outcomeをnullにしない。
- RejectionReasonはCategoryと安定Codeだけを保持し、自由文と任意detailsを持たない。
- Static AnalysisでGenericを実値へ結び付けるため、Outcome Propertyとprivate Constructor ParameterへTemplate型を注釈した。

## Commands and Results

| Command | Result |
| --- | --- |
| Composer Validate | 成功 |
| Mago Lint | No issues found |
| Mago Analyze | No issues found |
| PHPUnit | OK (130 tests, 299 assertions) |
| Deptrac | Violations 0、Uncovered 0、Allowed 25 |
| Comment Guardrail | 該当0件 |

初回検査では未使用Generic警告と空Code CaseのRisky Testを検出した。GenericをOutcome Propertyへ結び付け、固定例外MessageのAssertionを追加した後、再検査ですべて解消した。

## Acceptance Criteria

- [x] Public型とGenericがD052に一致する
- [x] `completed()` がEmptyOutcomeを保持する
- [x] Completed／Rejectedの判定とAccessorが正しい
- [x] 状態不一致Accessorを拒否する
- [x] Rejection CategoryとCodeを安全に保持する
- [x] 不正Codeを拒否する
- [x] 品質CommandとComment Guardrailが成功する

## Remaining Issues

- Handler解決、Attribute、Registry、Dispatcherは未実装。
- RejectionReasonから利用者向けResponseを作るResponderは未実装。

## Suggested Next Action

Operation Definitionを関連付けるAttributeとRegistryのPublic APIを確定し、Handler解決の土台を実装する。

## Codex Review

Accepted at `2026-07-06T00:50:12+09:00`。Codexが実装、修正、Test、品質Reviewを完了した。
