# P22-001: Stable 1.2 Version Baseline

Status: Accepted

## Goal

公開済みLatest Stable `1.1.0`を維持したまま、Repository `main`とSkeleton Source of Truthの次期Release Candidateを`1.2.0`へ更新し、Framework／Skeleton／Instrumentation／Consumer／DocumentationのVersion境界を一貫させる。

## Source of Truth

- `AGENTS.md`
- `develop/STATE.md`
- `develop/spec/46-composer-skeleton-publication.md`
- `develop/spec/60-post-phase-10-roadmap.md`
- `develop/spec/61-experimental-release-contract.md`
- `develop/decisions/094-stable-1-1-release-contract.md`
- `CHANGELOG.md`
- `UPGRADE.md`
- Git tag／GitHub Release `1.1.0`

## Version Decision Boundary

- 公開済みLatest StableはFramework／Skeleton `1.1.0`のままとし、既存Tag、GitHub Release、Packagist claimを変更しない
- 次期Minor Release Candidateは`1.2.0`とする。1.x Experimental policyではMinor間のBackward Compatibilityを保証せず、Phase 12〜21の新機能とbreaking surfaceをpatch `1.1.1`へ見せない
- Repository `main`のComposer root version、Framework-owned OpenTelemetry instrumentation scope、Skeleton constraint、candidate Consumer fixtureを`1.2.0`系列へ同期する
- 公開GuideのStable `1.1.0` install commandと歴史的Decision／Task／Reportは書き換えない
- `1.2.0`の完全なCHANGELOG／UPGRADE、Release Candidate full gate、Tag、Push、GitHub Release、Skeleton split publication、Packagist反映は後続Taskへ分離する

## In Scope

- `1.2.0` Release Candidate boundaryのDecision／Specification／Roadmap
- Main development root versionとOpenTelemetry scope versionの`1.2.0`化
- Skeleton Source of TruthのFramework constraintを`^1.2`へ更新
- Current-source package/create-project/publication Consumer fixtureのVersion同期
- `CHANGELOG.md`のUnreleased target、`UPGRADE.md`のPreview heading、README／Guide／Internal docsのStable-vs-candidate表示
- Version inventory guard、focused test、Consumer、Website、package metadata validation
- Report／STATE／TODO同期とDocumentation Reviewer

## Out of Scope

- `1.2.0` Tag作成、Push、GitHub Release、Packagist／Skeleton publication
- `1.2.0`をLatest Stable／公開済みと表示すること
- 既存`1.1.0` Tag、Release Note、Stable install journey、historical Decision／Task／Reportの変更
- Phase 12〜21全変更の最終Release Note／Upgrade手順完成
- Feature追加、Public API変更、Database Migration追加

## Files Allowed to Change

- `Dockerfile`
- `README.md`
- `CHANGELOG.md`
- `UPGRADE.md`
- `examples/quickstart/composer.json`
- `examples/quickstart/README.md`
- `src/Internal/Telemetry/TelemetryTracer.php`
- `src/Internal/Telemetry/TelemetryMetrics.php`
- `tests/Internal/Telemetry/TelemetryTracerTest.php`
- `tests/Internal/Telemetry/TelemetryMetricsTest.php`
- `tests/Consumer/quickstart-e2e.sh`
- `tests/Consumer/auth-generator-fresh.sh`
- `tests/Consumer/scheduled-operation.sh`
- `tests/Consumer/storage-protection-rotation.sh`
- `tests/Consumer/frankenphp-worker-mode.sh`
- `tests/Consumer/skeleton-create-project.sh`
- `tests/Consumer/skeleton-publication.sh`
- `tests/Consumer/skeleton-publication-workflow.sh`
- `tests/Consumer/version-baseline.sh`
- `docs/internal/development-setup.md`
- `docs/internal/mvp-e2e.md`
- `docs/internal/production-observability.md`
- `docs/internal/skeleton-publication.md`
- `docs/internal/installed-application-status.md`
- `docs/guide/mvp-status.md`
- `docs/guide/mvp-sample.md`
- `docs/guide/observability.md`
- `docs/website/pages/index.astro`
- `docs/website/content-map.mjs`
- `docs/website/theme.css`
- `docs/website/tests/guide-code.test.mjs`
- `docs/website/tests/reader-experience.test.mjs`
- `develop/decisions/139-stable-1-2-version-baseline.md`
- `develop/spec/09-runtime-and-di.md`
- `develop/spec/46-composer-skeleton-publication.md`
- `develop/spec/60-post-phase-10-roadmap.md`
- `develop/spec/100-structured-logging-and-opentelemetry.md`
- `develop/spec/103-stable-1-2-release-plan.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/tasks/P22-001-stable-1-2-version-baseline.md`
- `develop/orchestration/reports/P22-001-stable-1-2-version-baseline.md`

変更が必要なCurrent-source Version fileを追加で発見した場合は、歴史的記録かActive contractかを分類してReportへ返し、Orchestrator承認なしに範囲を広げない。

## Acceptance Criteria

- [x] Decision／SpecificationがLatest Stable `1.1.0`とnext candidate `1.2.0`を分離する
- [x] Docker Composer root version、Telemetry Trace／Metric scopeが`1.2.0`へ一致する
- [x] Skeleton Source of TruthがFramework `^1.2`を要求し、candidate package Consumerが`1.2.0`で完走する
- [x] Stable `1.1.0` install command、Tag／Release／Packagist claim、historical recordsを変更しない
- [x] README／Guide／Internal docsがStable `1.1.0`とmain target `1.2.0`を誤認なく説明する
- [x] CHANGELOG UnreleasedとUPGRADE Previewが`1.2.0` targetへ一致し、完全Release Noteは後続Gateと明示する
- [x] Version inventoryがActive current-source `1.1.0` driftと誤ったpublished `1.2.0` claimを拒否する
- [x] Focused PHPUnit、candidate Consumer、Composer strict、Mago format／management-ID／diff guardがPASSする
- [x] Website test／check／buildがPASSする
- [x] Documentation Reviewer最終FindingがP1=0／P2=0である

## Required Commands

```bash
docker compose run --rm app composer validate --strict
docker compose run --rm app vendor/bin/phpunit tests/Internal/Telemetry
docker compose run --rm app mago format --check src tests
bash -n tests/Consumer/skeleton-create-project.sh
bash -n tests/Consumer/skeleton-publication.sh
bash -n tests/Consumer/skeleton-publication-workflow.sh
bash -n tests/Consumer/version-baseline.sh
bash tests/Consumer/version-baseline.sh
bash tests/Consumer/skeleton-create-project.sh
bash tests/Consumer/skeleton-publication-workflow.sh
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P22-001-stable-1-2-version-baseline.md`へ、Version Inventory、Stable／Candidate Matrix、Changed Files、Decisions and Assumptions、Commands and Results、Acceptance Criteria、Remaining Release Work、Suggested Next Actionを記録する。
