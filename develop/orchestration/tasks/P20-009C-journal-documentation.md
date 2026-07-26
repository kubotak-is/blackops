# P20-009C: Journal Documentation

Status: Accepted

## Goal

Landingで訴求しているJournalを独立した利用者向けPageとして追加し、JSON構造、Canonical／Observed境界、設定、Replay、Security、OpenTelemetry将来構想を実装と一致する形で説明する。

## Source of Truth

- `AGENTS.md`
- `develop/decisions/127-journal-documentation.md`
- `develop/spec/94-journal-documentation.md`
- `develop/decisions/004-journal-schema-and-security.md`
- `develop/decisions/028-journal-record-schema.md`
- `develop/decisions/031-sensitive-projection.md`
- `develop/decisions/034-mvp-lifecycle-events.md`
- `develop/decisions/093-post-phase-10-roadmap.md`
- `develop/spec/83-blume-documentation-experience.md`
- `develop/spec/92-documentation-review-agent.md`
- `src/Journal/JournalRecord.php`
- `src/Journal/ObservedJournalRecord.php`
- `src/Journal/JournalEvent.php`
- `src/Logging/JsonlJournalRecordEncoder.php`

## In Scope

- 独立`Journal` Guideと`/concepts/journal`
- Sidebar、Content Map、Landing CTA、Guide相互Link
- Canonical／Observed、Lifecycle Event、Observed JSONL、`data`、Sensitive境界
- JSONL Configuration、Observer Replay、Retention／Securityへの導線
- OpenTelemetryの将来構想と未実装表示
- Source／Navigation／Reader／JSON／Artifact Regression
- Decision／Specification／TODO／STATE／Report同期

## Out of Scope

- Journal Schema、Event、Encoder、Store、ObserverのProduction Code変更
- OpenTelemetry Adapter、Exporter、Configurationの実装
- Canonical Journalの新しいPublic Serialization Contract
- Website共通Theme、Landing Layout、Blume Dependencyの変更
- Stable `1.1.0` Artifact変更
- Commit／Push／PR／External Publication

## Files Allowed to Change

- `docs/guide/journal.md`
- `docs/guide/README.md`
- `docs/guide/core-concepts.md`
- `docs/guide/operation-lifecycle.md`
- `docs/guide/observer-replay.md`
- `docs/website/content-map.mjs`
- `docs/website/site-navigation.mjs`
- `docs/website/pages/index.astro`
- `docs/website/scripts/check-site.mjs`
- `docs/website/tests/guide-code.test.mjs`
- `docs/website/tests/reader-experience.test.mjs`
- `docs/website/tests/site-navigation.test.mjs`
- `develop/decisions/127-journal-documentation.md`
- `develop/spec/94-journal-documentation.md`
- `develop/spec/README.md`
- `develop/TODO.md`
- `develop/STATE.md`
- This Task Packet
- `develop/orchestration/reports/P20-009C-journal-documentation.md`

## Required Commands

```bash
mise exec -- pnpm --dir docs/website run test
mise exec -- pnpm --dir docs/website run check
mise exec -- pnpm --dir docs/website run build
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

Websiteの`check`と`build`は同時実行しない。

## Acceptance Criteria

- [x] `Journal` Pageが`/concepts/journal`で生成される
- [x] Sidebarの`Operation` Sectionで`Lifecycle`直後に現在地表示される
- [x] Landing Journal CTAが`/concepts/journal`へ直接遷移する
- [x] Canonical JournalとObserved Journalを目的、保存、Sensitive境界で区別する
- [x] 現在のLifecycle Event 10個を実装と一致して説明する
- [x] Observed JSONLのField構造をEncoderと一致するParse可能な例で示す
- [x] `data`がEvent固有であることを説明する
- [x] JSONLの絶対Path、書込み可能Parent、`best_effort`／`required`を説明する
- [x] Observer ReplayとOperation Replayを区別する
- [x] Retention、Access Control、保存時暗号化、Key管理の運用境界を説明する
- [x] OpenTelemetry Adapter、Exporter、Configurationが未実装と明記する
- [x] OpenTelemetry Mappingは将来の候補方向でありPublic Contractではない
- [x] Guide間Link、Navigation、JSON Parse、Website Gateが成功する
- [x] WorkerはCommitしない

## Completion Report

`develop/orchestration/reports/P20-009C-journal-documentation.md`へSummary、Changed Files、Decisions and Assumptions、Commands and Results、Acceptance Criteria、Remaining Issues、Suggested Next Actionを記録する。
