# P1-018: HTTP Binding and Route Manifest Foundation

Status: Accepted

## Goal

HTTP Binding AttributeとRoute Manifestの土台を追加し、Runtime DI Container Compileへ進む前にHTTP構成の境界を固める。

## In Scope

- `FromPath`、`FromQuery`、`FromHeader`、`FromBody` Attributeを追加する
- OperationValue Binderが入力元Attributeを解釈できるようにする
- 単純JSON BodyでAttributeなしConstructor Parameterを同名キーからBindingする
- `{name}` 形式の最小Path Parameter matchingを追加する
- Route Compilerが軽量Operation Manifestを生成できるようにする
- HTTP Internals Documentation、Task Report、STATEを更新する

## Out of Scope

- Operation Manifest CLI
- Manifest PHP file出力／Loader
- FastRoute統合
- Authentication／Middleware
- Deferred HTTP 202
- Request Body Validationの詳細Error Contract

## Relevant Specifications

- `develop/spec/05-http.md`
- `develop/spec/08-registry-and-manifest.md`
- `develop/spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `src/Http/**`
- `tests/Http/**`
- `docs/internal/**`
- `develop/orchestration/tasks/P1-018-http-binding-and-route-manifest-foundation.md`
- `develop/orchestration/reports/P1-018-http-binding-and-route-manifest-foundation.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- Manifest CLIと生成Fileは次Task以降へ回す

## Acceptance Criteria

- [x] `FromPath` でPath ParameterをOperationValueへBindingできる
- [x] `FromQuery` でQuery ParameterをOperationValueへBindingできる
- [x] `FromHeader` でHeader値をOperationValueへBindingできる
- [x] `FromBody` でJSON BodyのFieldをOperationValueへBindingできる
- [x] AttributeなしParameterはJSON Bodyの同名FieldからBindingできる
- [x] `{name}` Pathを最小限matchし、Path ParameterをBinderへ渡せる
- [x] Route CompilerがRoute Manifest配列を生成できる
- [x] Formatterを含む必須品質Commandが成功する
- [x] PHP Comment／DocBlockに管理番号を含めない

## Required Commands

```bash
docker compose run --rm app mago format --check src tests
docker compose run --rm app composer validate --strict
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
```

## Expected Report

`develop/orchestration/reports/P1-018-http-binding-and-route-manifest-foundation.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
