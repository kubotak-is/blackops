# P12-001: Middleware and Authorization Design

Status: Accepted

## Goal

Current Operation Authoring／RuntimeとD010／Spec 06を照合し、Phase 12で必要なMiddleware、Authentication、Actor、Authorization、Deferred再認可のPublic API／Security／Failure境界をDecisionで確定する。

## In Scope

- D010／Spec 06とCurrent RuntimeのGap Audit
- Current Typed Self-handled／Native Outcome APIとMiddleware APIの整合
- Phase 12 Adapter ScopeとOperation Model
- Middleware登録／除外／順序／Compile Guard
- Framework／ApplicationのAuthentication責任境界
- Actor Durable ModelとDeferred Codec／Journal境界
- Authorization DenialとInfrastructure FailureのLifecycle分類
- D095たたき台とUser Answerの反映

## Out of Scope

- Production Code／Test／Workflowの実装
- Public APIの追加
- Spec 06の確定更新
- Phase 12 Delivery Plan／実装Task Packetの固定
- Session／JWT／External IdPの具体Library選定
- Console／Message Adapterの実装

## Relevant Specifications and Decisions

- `develop/decisions/009-execution-context.md`
- `develop/decisions/010-authentication-and-middleware.md`
- `develop/decisions/011-project-structure.md`
- `develop/decisions/071-operation-authoring-and-discovery.md`
- `develop/decisions/074-typed-self-handled-operation-signature.md`
- `develop/decisions/075-native-outcome-and-rejection-exception.md`
- `develop/decisions/093-post-phase-10-roadmap.md`
- `develop/spec/06-auth-and-middleware.md`
- `develop/spec/19-execution-context-api.md`
- `develop/spec/60-post-phase-10-roadmap.md`

## Files Allowed to Change

- `develop/decisions/095-phase-12-middleware-and-authorization-runtime.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P12-001-middleware-authorization-design.md`
- `develop/orchestration/reports/P12-001-middleware-authorization-design.md`
- `develop/STATE.md`

## Acceptance Criteria

- [x] D010／Spec 06とCurrent Runtime／Public APIのGapを監査した
- [x] 実装前にUser判断が必要な項目をD095の6問に絞った
- [x] 各QuestionにOptions、Recommendation、理由を記録した
- [x] User AnswerをD095へ反映し、DecisionをDecidedにする
- [x] D010のSuperseded範囲とSpec 06の更新入力を固定する
- [x] Report／STATEをAcceptedへ更新する

## Required Commands

```bash
git diff --check
```

## Expected Report

`develop/orchestration/reports/P12-001-middleware-authorization-design.md`へ次を記録する。

- Summary
- Current Runtime Audit
- Superseded／Compatible Decision Surface
- User Decision Questions
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
