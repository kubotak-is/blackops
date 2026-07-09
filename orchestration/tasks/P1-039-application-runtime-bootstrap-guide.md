# P1-039: Application Runtime Bootstrap Guide

Status: Accepted

## Goal

Phase 1で利用可能なBuildからProduction Runtime起動までの利用者向けGuideを追加する。

## In Scope

- `docs/guide/runtime-bootstrap.md` を追加する
- Provider config、Composer metadata、Build artifacts compile、Production artifact loading、Runtime compositionの利用手順を説明する
- HTTP Inline OperationをProduction runtimeで扱う最小導線を説明する
- `docs/guide/README.md` へGuide topicを追加する
- Task Report、STATEを更新する

## Out of Scope

- Production Code変更
- Public API追加
- Deferred/Worker Runtime
- PostgreSQL接続Factory
- Environment variable loader
- Front-controller scriptの実装

## Relevant Specifications

- `spec/05-http.md`
- `spec/08-registry-and-manifest.md`
- `spec/09-runtime-and-di.md`
- `spec/12-mvp-scope.md`
- `spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `docs/guide/**`
- `orchestration/tasks/P1-039-application-runtime-bootstrap-guide.md`
- `orchestration/reports/P1-039-application-runtime-bootstrap-guide.md`
- `orchestration/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- Documentationでは現在利用可能なPhase 1機能と未実装機能を区別する
- 本番Runtimeでは生成済みArtifactを読み込み、動的ScanへFallbackしないことを明記する
- Credential、Token、Secret、環境固有Secretを記載しない

## Acceptance Criteria

- [x] Runtime Bootstrap Guideが追加される
- [x] Provider configとComposer metadataの利用方法が説明される
- [x] Build artifacts compileの入力、出力、optionが説明される
- [x] Production artifact loadingとRuntime compositionの利用方法が説明される
- [x] Phase 1で未実装のDeferred/Worker等が明示される
- [x] Guide READMEからRuntime Bootstrap Guideへ辿れる
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

`orchestration/reports/P1-039-application-runtime-bootstrap-guide.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
