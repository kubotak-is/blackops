# P1-034: Composer Provider Build Integration

Status: Accepted

## Goal

Composer metadataで発見したOperation ProviderとService ProviderをBuild Artifacts compileへ統合する。

## In Scope

- Build Artifacts compile commandでComposer metadata fileを指定できるようにする
- Composer metadata由来のOperation Provider class-stringをOperation Manifest compileへ含める
- Composer metadata由来のService Provider class-stringをRuntime Container compileへ含める
- 既存の明示Provider configとComposer metadata由来Providerを同一Buildで併用できることを検証する
- Composer metadata fileをBuild fingerprint inputへ含める
- Build Internals Documentation、Task Report、STATEを更新する

## Out of Scope

- `vendor/composer/installed.json` 横断
- Composer Plugin実装
- File Scan、Composer PSR-4/Classmap Scan、Token Scan
- Operation Manifest単体CommandとRuntime Container単体CommandへのComposer metadata option追加
- Provider instance生成以外のDI自動探索
- Production bootstrap script

## Relevant Specifications

- `develop/spec/08-registry-and-manifest.md`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/15-source-layout.md`
- `develop/spec/16-namespace-dependencies.md`
- `develop/spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `src/Internal/**`
- `tests/Internal/**`
- `docs/internal/**`
- `develop/orchestration/tasks/P1-034-composer-provider-build-integration.md`
- `develop/orchestration/reports/P1-034-composer-provider-build-integration.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- Composer DiscoveryはInternal実装とし、公開APIへInternal型を露出しない
- DiscoveryはProvider class-stringの発見だけを行い、Build command側で既存Provider生成境界へ渡す
- Handler、Operation Envelope、Domain ServiceへContainerを渡さない

## Acceptance Criteria

- [x] Build Artifacts compile commandでComposer metadata fileを指定できる
- [x] Composer metadata由来Operation ProviderがOperation Manifestへ反映される
- [x] Composer metadata由来Service ProviderがRuntime Containerへ反映される
- [x] 明示Provider configとComposer metadata由来Providerを同一Buildで併用できる
- [x] Composer metadata fileがBuild fingerprint inputへ含まれる
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

`develop/orchestration/reports/P1-034-composer-provider-build-integration.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
