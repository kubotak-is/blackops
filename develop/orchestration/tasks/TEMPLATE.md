# <Task ID>: <Task Name>

Status: Ready

## Goal

<!-- このTaskで達成する一つの成果。 -->

## In Scope

- 

## Out of Scope

- 

## Relevant Specifications

- 

## Files Allowed to Change

- 

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する

## Release Documentation Impact

- Authority tuple／Capability ID: 影響なしの場合も理由を記録する
- Public Source／route inventory:
- Version occurrence before／after分類、historical allowlist:
- Source／Search／LLM artifact、positive／negative fixture:
- same-SHA CI／Documentation delivery、Production deploy有無:
- 残り工程、Next Action:

## Acceptance Criteria

- [ ] 

## Required Commands

```bash
# 実行必須のTest、Lint、Static Analysisを記載する。
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
```

## Expected Report

`develop/orchestration/reports/<task-id>.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
