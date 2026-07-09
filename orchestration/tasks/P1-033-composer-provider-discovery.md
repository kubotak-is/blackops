# P1-033: Composer Provider Discovery

Status: Accepted

## Goal

Composer metadataからOperation ProviderとService Providerを発見する最小の内部Discovery境界を追加する。

## In Scope

- Internal Composer Provider Discoveryを追加する
- Composer `extra.blackops.operation-providers` からOperation Provider class-string群を読み込めることを検証する
- Composer `extra.blackops.service-providers` からService Provider class-string群を読み込めることを検証する
- 不正なComposer JSON、不正なProvider entry、Contract不一致Providerを拒否できることを検証する
- Provider metadataがないComposer JSONを空として扱えることを検証する
- Registry/Runtime Internals Documentation、Task Report、STATEを更新する

## Out of Scope

- `vendor/composer/installed.json` 横断
- Composer Plugin実装
- File Scan、Composer PSR-4/Classmap Scan、Token Scan
- Build Artifacts Commandとの統合
- Provider instance生成
- Production bootstrap script

## Relevant Specifications

- `spec/08-registry-and-manifest.md`
- `spec/09-runtime-and-di.md`
- `spec/15-source-layout.md`
- `spec/16-namespace-dependencies.md`
- `spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `src/Internal/**`
- `tests/Internal/**`
- `docs/internals/**`
- `orchestration/tasks/P1-033-composer-provider-discovery.md`
- `orchestration/reports/P1-033-composer-provider-discovery.md`
- `orchestration/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- DiscoveryはInternal実装とし、公開APIへInternal型を露出しない
- DiscoveryはProvider class-stringの発見だけを行い、Provider instanceを生成しない
- Handler、Operation Envelope、Domain ServiceへContainerを渡さない

## Acceptance Criteria

- [x] Composer JSONからOperation Provider class-string群を発見できる
- [x] Composer JSONからService Provider class-string群を発見できる
- [x] Provider metadataがないComposer JSONを空として扱える
- [x] 不正なComposer JSONを拒否できる
- [x] 不正なProvider entryを拒否できる
- [x] Contract不一致Providerを拒否できる
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

`orchestration/reports/P1-033-composer-provider-discovery.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
