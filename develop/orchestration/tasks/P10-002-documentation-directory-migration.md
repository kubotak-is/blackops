# P10-002: Documentation Directory Migration

Status: Ready

## Goal

Framework実装者向けDocumentationを`docs/internals/`から`docs/internal/`へAtomicに移行し、Repository内の有効な参照とAudience境界を同期する。

## In Scope

- `docs/internals/`全Fileの`docs/internal/`へのRename
- AGENTS、Root README、Develop Index／Specification／Task／Report内Pathの同期
- GuideからInternal Documentationへの相対Link同期
- Acceptance Evidence中心の`installed-application-status.md`をInternalへ移動
- Guide／Internal IndexのAudience説明同期
- 旧Path残存とMarkdown Linkの機械検証
- Report／STATE更新

## Out of Scope

- Astro Starlight Project作成
- Guide本文の全面改稿
- Website Navigation／URL実装
- Production Code／PHP Test変更
- 歴史的DecisionがRename前後を説明するために必要な旧Path表記の削除

## Relevant Specifications and Decisions

- `develop/decisions/063-developer-experience-roadmap.md`
- `develop/decisions/081-documentation-website-delivery-contract.md`
- `develop/spec/41-developer-experience-roadmap.md`
- `develop/spec/57-documentation-website-delivery-contract.md`
- `develop/spec/58-phase-10-delivery-plan.md`

## Files Allowed to Change

- `AGENTS.md`
- `README.md`
- `docs/guide/**`
- `docs/internals/**`
- `docs/internal/**`
- `develop/**/*.md`

許可されていないFileの変更が必要な場合は変更を広げず、Reportへ記載する。

## Constraints

- Production CodeとTestを変更しない
- File内容とGit Historyを追えるRenameとして実施する
- `docs/internal/`を公開Website Sourceとして記述しない
- `docs/guide/installed-application-status.md`の移動後もRoot／Guideから必要なCurrent Statusへ到達できるようにする
- Decided文書の意味をPath置換で変えない。Rename履歴を説明する`docs/internals/`表記は明示的Allowlistにする
- Credential、Token、Secretを追加しない

## Acceptance Criteria

- [ ] `docs/internal/`に全Internal Documentationが存在する
- [ ] `docs/internals/`が存在しない
- [ ] AGENTSとRoot READMEの現行Pathが`docs/internal/`を指す
- [ ] 有効なMarkdown LinkとTaskの変更可能Pathが新Directoryへ同期する
- [ ] Acceptance Evidence中心のInstalled Application Statusが公開GuideからInternalへ移る
- [ ] Guide／Internal IndexがAudience境界と一致する
- [ ] 許可したRename履歴以外に旧Path参照が残らない

## Required Commands

```bash
test -d docs/internal
! test -e docs/internals
rg -n "docs/internal|Framework実装者" AGENTS.md README.md docs/internal/README.md develop/DOCS.md
! rg -n 'docs/internals|\.\./internals' AGENTS.md README.md docs/guide docs/internal develop --glob '*.md' --glob '!develop/decisions/063-developer-experience-roadmap.md' --glob '!develop/decisions/081-documentation-website-delivery-contract.md' --glob '!develop/spec/41-developer-experience-roadmap.md' --glob '!develop/spec/57-documentation-website-delivery-contract.md' --glob '!develop/spec/58-phase-10-delivery-plan.md' --glob '!develop/orchestration/tasks/P10-002-documentation-directory-migration.md'
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P10-002-documentation-directory-migration.md` に次を記録する。

- Summary
- Rename and Reference Evidence
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Issues
- Suggested Next Action
