# P1-035: Installed Composer Provider Discovery

Status: Accepted

## Goal

Composer installed packages metadataからOperation ProviderとService Providerを発見し、Build Artifacts compileへ統合する。

## In Scope

- Internal Installed Composer Provider Discoveryを追加する
- Composer 2形式の `vendor/composer/installed.json` からPackageごとの `extra.blackops` Provider metadataを横断できることを検証する
- 旧形式のPackage list JSONも扱えることを検証する
- 不正なinstalled metadata、不正なPackage entry、不正なProvider entryを拒否できることを検証する
- Build Artifacts compile commandでinstalled packages metadata fileを指定できるようにする
- installed packages metadata fileをBuild fingerprint inputへ含める
- Build/Registry/Runtime Internals Documentation、Task Report、STATEを更新する

## Out of Scope

- Composer Plugin実装
- Composer Runtime API利用
- File Scan、Composer PSR-4/Classmap Scan、Token Scan
- Operation Manifest単体CommandとRuntime Container単体Commandへのinstalled metadata option追加
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
- `docs/internals/**`
- `develop/orchestration/tasks/P1-035-installed-composer-provider-discovery.md`
- `develop/orchestration/reports/P1-035-installed-composer-provider-discovery.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- DiscoveryはInternal実装とし、公開APIへInternal型を露出しない
- DiscoveryはProvider class-stringの発見だけを行い、Build command側で既存Provider生成境界へ渡す
- Handler、Operation Envelope、Domain ServiceへContainerを渡さない

## Acceptance Criteria

- [x] Composer 2形式のinstalled metadataからProvider class-string群を発見できる
- [x] 旧形式のPackage list JSONからProvider class-string群を発見できる
- [x] Provider metadataがないPackageを無視できる
- [x] 不正なinstalled metadata、不正なPackage entry、不正なProvider entryを拒否できる
- [x] Build Artifacts compile commandでinstalled packages metadata fileを指定できる
- [x] installed packages metadata fileがBuild fingerprint inputへ含まれる
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

`develop/orchestration/reports/P1-035-installed-composer-provider-discovery.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
