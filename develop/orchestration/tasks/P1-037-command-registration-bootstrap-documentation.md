# P1-037: Command Registration Bootstrap Documentation

Status: Accepted

## Goal

Build command registrationとProduction artifact bootstrapのInternal Documentationを整備する。

## In Scope

- `docs/internal/bootstrap.md` を追加する
- Build時に登録するInternal commandと公開HTTP commandの責務を整理する
- Build artifacts compile commandの推奨入力、出力、optionを説明する
- Provider discovery metadata、build lock、fingerprint、production artifact loadingの関係を説明する
- `docs/internal/README.md` へBootstrap topicを追加する
- Task Report、STATEを更新する

## Out of Scope

- Production Code変更
- Console Application Factory実装
- Public API化
- HTTP front-controller script
- Dispatcher、Journal Store、TransportのFull Runtime Composition
- Environment variable loader

## Relevant Specifications

- `develop/spec/08-registry-and-manifest.md`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/13-mvp-technical-stack.md`
- `develop/spec/15-source-layout.md`
- `develop/spec/16-namespace-dependencies.md`
- `develop/spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `docs/internal/**`
- `develop/orchestration/tasks/P1-037-command-registration-bootstrap-documentation.md`
- `develop/orchestration/reports/P1-037-command-registration-bootstrap-documentation.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- Documentationでは確定仕様と現在の実装境界を区別する
- 本番Runtimeでは生成済みArtifactを読み込み、動的ScanへFallbackしないことを明記する
- Credential、Token、Secret、環境固有Secretを記載しない

## Acceptance Criteria

- [x] Bootstrap documentationが追加される
- [x] Build command registration対象と責務が整理される
- [x] Build artifacts compile commandの入力、出力、optionが説明される
- [x] Provider discovery、lock、fingerprint、production artifact loadingの関係が説明される
- [x] Internals READMEからBootstrap documentationへ辿れる
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

`develop/orchestration/reports/P1-037-command-registration-bootstrap-documentation.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
