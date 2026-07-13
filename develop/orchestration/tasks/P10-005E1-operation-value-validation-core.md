# P10-005E1: OperationValue Validation Core

Status: Blocked by P10-005C

## Goal

BlackOps所有の7つのValidation Attribute、Raw Valueを保持しないViolation Model、OperationValue Validatorを実装し、HTTPやExecutionへ接続する前の独立したCore Contractを完成させる。

## In Scope

- `BlackOps\Core\Validation\Attribute` Public API
- `NotBlank`、`Length`、`Range`、`Email`、`Regex`、`Count`、`Choice`
- Attribute Constructor Validation
- Field／Rule／Codeだけを持つViolation Model
- OperationValueのConstructor Promotion Propertyを検証するInternal Validator
- 全Violation集約
- Sensitive Raw Value非保持
- Public API／Deptrac／Unit Test
- Internal Documentation、Report、STATE

## Out of Scope

- HTTP Binding／Status／Response
- Journal／Lifecycle接続
- Deferred Worker接続
- Nested Object、DB照合、Cross-field、Custom Callback
- Website Content

## Relevant Specifications and Decisions

- `develop/decisions/005-operation-value-and-validation.md`
- `develop/decisions/086-operation-value-validation-runtime.md`
- `develop/spec/04-handler-and-result.md`
- `develop/spec/15-source-layout.md`
- `develop/spec/16-namespace-dependencies.md`

## Files Allowed to Change

- `src/Core/Validation/**`
- `src/Internal/Validation/**`
- `tests/Core/Validation/**`
- `tests/Internal/Validation/**`
- `tests/Architecture/**`
- `deptrac.yaml`
- `docs/internal/**`
- `develop/orchestration/reports/P10-005E1-operation-value-validation-core.md`
- `develop/STATE.md`

## Constraints

- 原則GPT-5.6 Luna High workerが実装し、Review前にCommitしない
- Userは2026-07-13の回答`Y`により、本TaskでModel／Profile Metadataを確認できない現在利用可能なWorkerを使う例外を承認済み
- `Range`は数値、`Length`は文字列、`Count`は配列要素数だけを扱う
- 曖昧な`Min`／`Max` Attributeを追加しない
- ViolationへRaw／Normalized Input Valueを保持しない
- Attribute／Validatorは`BlackOps\Internal`型をPublic Signatureへ露出しない
- Runtime Dependencyを追加しない

## Acceptance Criteria

- [ ] 7 AttributeがPublic APIとして正しいTarget／Constructor Contractを持つ
- [ ] 不正Rule設定をAttribute生成時に拒否する
- [ ] 全Ruleの成功／失敗／境界値をUnit Testする
- [ ] 複数Property／複数RuleのViolationを決定的順序で集約する
- [ ] ViolationがField、Rule、Codeだけを持ちRaw Valueを持たない
- [ ] Sensitive Propertyの入力値がException／Dump／Test Outputへ露出しない
- [ ] DeptracとPublic API Guardが成功する

## Required Commands

```bash
docker compose run --rm app mago format --check src tests
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit tests/Core/Validation tests/Internal/Validation tests/Architecture
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P10-005E1-operation-value-validation-core.md`へSummary、Public API、Rule Matrix、Sensitive Boundary、Changed Files、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
