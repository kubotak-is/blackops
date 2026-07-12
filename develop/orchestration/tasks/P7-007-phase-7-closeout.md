# P7-007: Phase 7 Closeout

Status: Ready

## Goal

Phase 7のInstalled Application Example and Skeleton Layoutを、現行Quickstart Tree、Public API Architecture Guard、独立Consumer E2E、Documentation、全品質Commandへ対応付け、Phase Acceptance Criteriaを証拠付きでcloseoutする。

## In Scope

- Phase 7 Acceptance Criteria 9項目のEvidence Table
- Installed Tree仕様と `examples/quickstart/` 実Fileの一致確認
- Application／Bootstrap／EntrypointのPublic API Boundary確認
- Feature-first削除単位とSelf-handled Operation導線確認
- Explicit Build／Migration／Worker／Maintenance Process Boundary確認
- Public HTTP／Console CompositionとProject CLI確認
- Root Dev AutoloadなしConsumer copy-install E2E証拠
- Guide／Internals／Quickstart README導線の同期
- Phase 8へ渡すPackage Source Boundaryと未実装範囲の明記
- `develop/TODO.md`、Phase Plan、Report、Checkpoint更新
- Full Quality SuiteとConsumer E2E再実行

## Out of Scope

- Production Code／Public API／Test Scenarioの新規機能変更
- Quickstart Runtime／Consumer E2Eの仕様変更
- Skeleton Split Repository／Packagist公開
- `composer create-project` Remote Install
- Project Generator Command
- Documentation Website
- Git Tag／Release／Push

## Relevant Specifications and Decisions

- `develop/decisions/063-developer-experience-roadmap.md`
- `develop/decisions/064-installed-application-layout-and-bootstrap.md`
- `develop/decisions/065-composer-skeleton-publication.md`
- `develop/decisions/068-public-console-kernel-composition.md`
- `develop/decisions/069-skeleton-http-entrypoint-adapters.md`
- `develop/decisions/070-quickstart-journal-observer.md`
- `develop/decisions/071-operation-authoring-and-discovery.md`
- `develop/spec/41-developer-experience-roadmap.md`
- `develop/spec/42-installed-application-boundary.md`
- `develop/spec/43-installed-application-layout-and-bootstrap.md`
- `develop/spec/44-public-application-bootstrap-api.md`
- `develop/spec/45-phase-7-delivery-plan.md`
- `develop/spec/49-feature-first-quickstart-application.md`
- `develop/spec/50-operation-authoring-and-build-discovery.md`
- `develop/spec/51-local-runtime-and-consumer-e2e.md`

## Files Allowed to Change

- `develop/TODO.md`
- `develop/DOCS.md`
- `develop/spec/45-phase-7-delivery-plan.md`
- `docs/guide/README.md`
- `docs/guide/installed-application-status.md`
- `docs/internals/README.md`
- `docs/internals/mvp-e2e.md`
- `examples/quickstart/README.md`
- `develop/orchestration/tasks/P7-007-phase-7-closeout.md`
- `develop/orchestration/reports/P7-007-phase-7-closeout.md`
- `develop/STATE.md`

許可されていないFileの変更が必要な場合は変更を広げず、ReportのBlockerとして返す。

## Constraints

- Production CodeとTestをCloseout便宜で変更しない
- Phase Acceptance Criteriaは実File、Test、Command、Accepted Task Reportへ紐付ける
- Phase 8以降の未実装機能を利用可能と記載しない
- `examples/quickstart/` はSkeleton配布元だが、Remote Package公開済みとは記載しない
- Consumer E2EはTemp Path Repositoryによるcopy installであり、Packagist install証拠とは扱わない
- MVP Complete、Phase 7 Complete、Production Ready、Stable Releaseを混同しない
- TODOはPhase 7の実証済み項目だけ完了にし、Phase 8以降を未完了のまま保つ

## Acceptance Criteria

- [ ] Phase Acceptance Criteria 9項目がEvidence付きでSatisfiedになる
- [ ] Installed Tree仕様とQuickstart実Fileの一致が確認される
- [ ] Public API BoundaryとInternal Import不在が確認される
- [ ] Feature-first削除単位とSelf-handled Authoring導線が文書化される
- [ ] Default／Explicit Process Boundaryが現行Compose／READMEと一致する
- [ ] Independent Consumer copy-install E2Eが成功する
- [ ] Phase 8 Package Source Boundaryと未公開範囲が明記される
- [ ] Guide／Internals／DOCSからPhase 7 Statusへ到達できる
- [ ] Full Quality Suite、Composer、Compose、Guardが成功する
- [ ] TODO、Phase Plan、Report、STATEがPhase 7 Completeへ更新される

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app composer validate --strict examples/quickstart/composer.json
docker compose -f examples/quickstart/compose.yaml config
docker compose -f examples/quickstart/compose.yaml config --services
docker compose run --rm app mago format --check src tests examples
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
bash tests/Consumer/quickstart-e2e.sh
! rg -n 'BlackOps\\Internal' examples/quickstart --glob '*.php'
! rg -n '"type"[[:space:]]*:[[:space:]]*"path"|"url"[[:space:]]*:[[:space:]]*"/framework"|"repositories"[[:space:]]*:' examples/quickstart/composer.json
! test -e examples/quickstart/composer.lock
! test -d examples/quickstart/vendor
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests examples --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P7-007-phase-7-closeout.md` に次を記録する。

- Summary
- Phase 7 Acceptance Evidence
- Installed Tree／Public Boundary Evidence
- Runtime／Consumer Evidence
- Phase 8 Package Source Handoff
- Changed Files
- Decisions and Assumptions
- Commands and Results
- Acceptance Criteria
- Remaining Post-Phase-7 Work
- Suggested Next Action
