# P1-036: Production Runtime Artifact Loader

Status: Accepted

## Goal

Build済みOperation Manifest、HTTP Manifest、Runtime Containerを本番Runtime向けに読み込むInternal Bootstrap境界を追加する。

## In Scope

- Internal Production Runtime Artifact Loaderを追加する
- Operation Manifest fileを読み込んでOperation Registryを返せることを検証する
- HTTP Manifest fileを読み込んでHTTP Operation Manifestを返せることを検証する
- Runtime Container dump fileを読み込み、指定されたContainer classをPSR-11 Containerとして返せることを検証する
- 不足Artifact、不正Container class、不正Container instanceを拒否できることを検証する
- Runtime Internals Documentation、Task Report、STATEを更新する

## Out of Scope

- Public API化
- HTTP RequestHandlerやDispatcherの完全Composition
- Journal StoreやTransport wiring
- Production front-controller script
- Command Registration Bootstrap Documentation
- Environment variable loader

## Relevant Specifications

- `develop/spec/08-registry-and-manifest.md`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/15-source-layout.md`
- `develop/spec/16-namespace-dependencies.md`
- `develop/spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `src/Internal/**`
- `tests/Internal/**`
- `docs/internals/**`
- `develop/orchestration/tasks/P1-036-production-runtime-artifact-loader.md`
- `develop/orchestration/reports/P1-036-production-runtime-artifact-loader.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- Runtime loaderはInternal実装とし、公開APIへInternal型を露出しない
- Production Runtimeでは生成済みArtifactを読み込み、動的ScanへFallbackしない
- Handler、Operation Envelope、Domain ServiceへContainerを渡さない

## Acceptance Criteria

- [x] Operation Manifest fileからOperation Registryを読み込める
- [x] HTTP Manifest fileからHTTP Operation Manifestを読み込める
- [x] Runtime Container dump fileからPSR-11 Containerを生成できる
- [x] 不足Artifactを拒否できる
- [x] 不正Container classを拒否できる
- [x] PSR-11 ContainerではないContainer instanceを拒否できる
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

`develop/orchestration/reports/P1-036-production-runtime-artifact-loader.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
