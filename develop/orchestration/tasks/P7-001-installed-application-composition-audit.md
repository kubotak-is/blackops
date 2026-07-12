# P7-001: Installed Application Composition Audit

Status: Completed

## Goal

現在のMVP SampleをFramework外のConsumer Applicationとして監査し、Installed Application Exampleへ移行する前に解消すべきPublic API、Package、Directory、Process EntrypointのGapを確定する。

## In Scope

- `examples/mvp/` のApplicationとしての完全性
- `MvpSampleEndToEndTest` が利用するFramework API境界
- Root `composer.json` と独立Consumer Package境界
- HTTP、Console、Worker、Migration、Build、RetentionのApplication Composition要件
- Phase 7の確定要件と未決事項の分類

## Out of Scope

- Production Code／Public API／Testの変更
- `examples/quickstart/` の作成
- 完全なApplication Directory Layoutの決定
- Public Bootstrap APIの具体的なClass／Method Signature決定
- Composer Project Packageの公開

## Relevant Specifications and Decisions

- `develop/decisions/063-developer-experience-roadmap.md`
- `develop/spec/07-project-structure.md`
- `develop/spec/14-package-architecture.md`
- `develop/spec/15-source-layout.md`
- `develop/spec/17-core-api.md`
- `develop/spec/40-mvp-delivery-plan.md`
- `develop/spec/41-developer-experience-roadmap.md`

## Files Allowed to Change

- `develop/TODO.md`
- `develop/spec/README.md`
- `develop/spec/42-installed-application-boundary.md`
- `develop/orchestration/tasks/P7-001-installed-application-composition-audit.md`
- `develop/orchestration/reports/P7-001-installed-application-composition-audit.md`
- `develop/STATE.md`

## Constraints

- 監査結果は実在するFile、Import、Composer Metadata、既存仕様を証拠とする
- Internal APIを便宜的にPublic扱いしない
- 将来のApplication LayoutやAPI Signatureを監査Taskで推測して確定しない
- Production CodeとTestは変更しない

## Acceptance Criteria

- [x] 現在のSampleがInstalled Applicationとして不足する要素が列挙される
- [x] Consumer E2Eが直接利用するInternal API境界が特定される
- [x] Phase 7で追加すべきPublic Composition領域が分類される
- [x] D063から確定済みのInstalled Application要件が仕様化される
- [x] Product／Public API判断が必要な事項が後続Decision候補として分離される
- [x] Production CodeとTestを変更していない

## Required Commands

```bash
find examples/mvp -type f | sort
rg -o 'BlackOps\\Internal\\[A-Za-z0-9_\\]+' tests/Integration/MvpSampleEndToEndTest.php | sort -u
rg -n '#\[PublicApi\]' src
git diff --check
```

## Expected Report

`develop/orchestration/reports/P7-001-installed-application-composition-audit.md` に次を記録する。

- Summary
- Current Sample Evidence
- Public Composition Gaps
- Confirmed Requirements
- Decisions Still Required
- Changed Files
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
