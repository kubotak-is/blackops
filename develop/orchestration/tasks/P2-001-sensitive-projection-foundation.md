# P2-001: Sensitive Projection Foundation

Status: Accepted

## Goal

Observer/Loggingへ渡す前の最低安全基準として、Sensitive metadataとInternal Sensitive Projection Filterを追加する。

## In Scope

- Public `#[Sensitive]` Attributeを追加する
- Public `SensitiveMode` enumを追加する
- Internal Sensitive Projection Filterを追加する
- Object public propertyの `#[Sensitive]` Metadataに基づきOmit/Mask/Hashできることを検証する
- Array key fallback patternに基づき予約KeyをOmitできることを検証する
- Logger context等へ使えるarray projectionを検証する
- Sensitive Projection Internals Documentation、Task Report、STATEを更新する

## Out of Scope

- Journal Observer Port実装
- ObservedJournalRecord実装
- PSR-3 Logger decorator実装
- Execution Scope実装
- Adapter固有Redactor
- 暗号化

## Relevant Specifications

- `develop/spec/10-logging-and-traceability.md`
- `develop/spec/25-sensitive-projection.md`
- `develop/spec/26-journal-ports.md`
- `develop/spec/15-source-layout.md`
- `develop/spec/16-namespace-dependencies.md`
- `develop/spec/40-mvp-delivery-plan.md`

## Files Allowed to Change

- `src/Core/**`
- `src/Internal/**`
- `tests/Core/**`
- `tests/Internal/**`
- `docs/internal/**`
- `develop/orchestration/tasks/P2-001-sensitive-projection-foundation.md`
- `develop/orchestration/reports/P2-001-sensitive-projection-foundation.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は、実装を止めてReportへ記載する。

## Constraints

- PHP Comment／DocBlockへSpec、Decision、Task、TODOの管理番号を書かない
- CommentはCodeだけで理解できる責務、Invariant、理由を説明する
- Public APIへInternal型を露出しない
- Hashは秘密鍵付きHMACとし、平文Hashを実装しない
- Canonical Journal StoreのRaw Payload保存方針は変更しない

## Acceptance Criteria

- [x] `#[Sensitive]` AttributeがPublic APIとして追加される
- [x] `SensitiveMode` enumがPublic APIとして追加される
- [x] `#[Sensitive]` の既定ModeはOmitである
- [x] Object public propertyをOmit/Mask/Hashできる
- [x] Array key fallback patternで予約KeyをOmitできる
- [x] Hashは秘密鍵付きHMACである
- [x] Sensitive Projection Internals Documentationが更新される
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

`develop/orchestration/reports/P2-001-sensitive-projection-foundation.md` に次を記録する。

- Summary
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
