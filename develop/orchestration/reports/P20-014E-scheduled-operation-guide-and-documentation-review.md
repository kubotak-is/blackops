# P20-014E Scheduled Operation Guide and Documentation Review

## Summary

Scheduled Application OperationのRepository `main`限定Guideを、AuthoringからBuild／Migration／one-shot評価、Worker、Occurrence／Journal診断、外部Supervisorまで一続きで読める状態へ同期した。Review指摘に対してpublic `$schedule` property、固定FireOnce misfire、Framework Schema付きRead-only SQL、per-occurrence／top-level runtime error分離、初回検証手順を補正した。最新Artifactの実Browser ReviewはP1／P2／P3すべて0でAcceptされ、P20-014EをAcceptedとする。

## Changed Files

P20-014EのScheduled Operation導線・本文・Reference・Website同期で対象になった許可Fileは次のとおりです。

- Guide本文: `docs/guide/attributes.md`, `authorization.md`, `core-api.md`, `deployment.md`, `execution-context.md`, `execution.md`, `glossary.md`, `journal.md`, `mvp-status.md`, `operations.md`, `outbox.md`, `project-cli.md`, `scheduled-operation.md`, `troubleshooting.md`
- Website IA／Regression: `docs/website/content-map.mjs`, `docs/website/site-navigation.mjs`, `docs/website/tests/reader-experience.test.mjs`, `docs/website/tests/site-navigation.test.mjs`
- Orchestration closeout: `develop/orchestration/tasks/P20-014E-scheduled-operation-guide-and-documentation-review.md`, `develop/STATE.md`, `develop/TODO.md`, `develop/orchestration/reports/P20-014E-scheduled-operation-guide-and-documentation-review.md`

上記のうちReview指摘のaccuracy／journey correctionとWebsite regressionはこのCorrection Passで補正した。その他は既存P20-014E worker差分を保持しており、許可範囲外のProduction Code、Migration、Consumer Sourceは変更していない。

## Reader Journey Matrix

| Step | Guide evidence |
| --- | --- |
| Stable／main boundary | `scheduled-operation.md` opening warning and `mvp-status.md` capability matrix |
| Authoring／Value shape | `ScheduledBy` canonical sample; no required Value constructor; `ExecutionContext::schedule()` |
| Strategy | Attribute-free Inline／`#[Deferred]` Deferred table |
| Authorization | Application-owned `ScheduledActorProvider`, Service Provider class, and `config/app.php` `services` registration |
| Preparation／run | `database:migrate` → `build:compile` → `operation:schedule:run --json` |
| Results／recovery | Human／JSON counts, Exit 0／1／2, misfire／overlap／claimed recovery, fixed Operation ID |
| Diagnostics／operations | safe occurrence SQL, `operation:inspect`, Journal correlation, one-shot external Supervisor |
| Process boundary | Application `operation:schedule:run`／Worker versus Maintenance `scheduler:run`／`scheduler:daemon` |

## Stable／main Matrix

Stable `1.1.0` does not include Scheduled Application Operation. Repository `main` documents it as Experimental, including `ScheduledBy`, schedule persistence, and `operation:schedule:run`; Application Schedule Daemon and manifest generators remain unavailable.

## Command／Exit Matrix

`operation:schedule:run [--json]` evaluates once and returns `evaluated`, `accepted`, `skipped_misfire`, `skipped_overlap`, and `failed`. Exit `0` means no runtime failures, `1` means runtime evaluation／invocation failure, and `2` means safe input／configuration failure. JSON contains counts only; an Operation ID is obtained from the safe occurrence query for later inspection.

## Process Boundary Matrix

| Capability | Process owner | Command |
| --- | --- | --- |
| Application Schedule | External Cron／systemd／Kubernetes Supervisor | `php blackops operation:schedule:run --json` (one-shot) |
| Deferred completion | Application Worker Supervisor | `php blackops worker:run` |
| Framework Maintenance | Maintenance Supervisor | `php blackops scheduler:run`／`scheduler:daemon` |

The Maintenance Scheduler never starts Application Operations, and the Application Schedule command is not a daemon or supervisor.

## Accuracy Evidence

- Source contract: `develop/spec/98-scheduled-application-operation.md`
- Runtime command／counts／safe error: `src/Internal/Console/ScheduledOperationRunCommand.php` and focused command tests
- Provider registration and Build validation: `tests/Internal/Console/ApplicationBuildCompileCommandTest.php`, `tests/Scheduling/ScheduledActorProviderTest.php`
- Schedule Context wire fields: `src/Internal/Codec/ExecutionContextNormalizer.php`, `src/Transport/PostgreSql/PostgreSqlJournalRecordCodec.php`
- Journal schedule parameter table documents `operation.schedule`, `name`, `scheduled_at`, and `timezone`; non-Scheduled roots remain `null`.

## Commands and Results

| Command | Result |
| --- | --- |
| `mise exec -- pnpm --dir docs/website run test` | PASS — 73 tests |
| `mise exec -- pnpm --dir docs/website run check` | PASS — content, diagrams, Blume check; 0 errors／warnings／hints |
| `mise exec -- pnpm --dir docs/website run build` | PASS — 40 pages; artifact and site checks passed for 39 public pages |
| Focused PHPUnit (ScheduledBy／ScheduleContext／Provider／CLI) | PASS — 18 tests, 40 assertions |
| `bash tests/Consumer/scheduled-operation.sh` | PASS — Scheduled operation CLI, recovery, and concurrency journey |
| `docker compose run --rm app mago format --check src tests` | PASS |
| Management-ID guard | PASS — no forbidden IDs in `src`／`tests` PHP comments |
| `git diff --check` | PASS |

### Correction rerun

| Command | Result |
| --- | --- |
| `mise exec -- pnpm --dir docs/website run test` | PASS — 74 tests |
| `mise exec -- pnpm --dir docs/website run check` | PASS — 0 errors／warnings／hints |
| `mise exec -- pnpm --dir docs/website run build` | PASS — 40 pages; artifact／site check passed |
| `git diff --check` | PASS |

## Browser Evidence

Read-only Documentation Reviewerが最新`http://localhost:4322` ArtifactをChromiumで実測した。

- 対象: Scheduled Operation、BlackOps CLI、Deployment、Troubleshooting、Journal、Attributes、Core API、Releasesの8 Routes
- 条件: Desktop 1440px Light／Dark、Mobile 390pxの計24条件
- 全24条件: HTTP 200、H1一致、`aria-current="page"`各1件、Document幅`1440/1440`または`390/390`、Page-wide overflowなし
- Theme toggle: LightからDarkへ切り替わり、本文色と背景色が変化
- Mobile Navigation: Drawer `x=0..256`、active Scheduled Operation `x=33..235`で可視
- Mobile Code: 内側Code `clientWidth=340`／`scrollWidth=617`／`overflow:auto`で局所Scrollへ隔離
- Table: `blume-table-scroll`内に収まり、Scheduled Operation Pageは`340/340`でWrap
- Link: Scheduled Operation本文のInternal／Anchor Link 18件がHTTP 200かつAnchor実在
- Evidence: `/tmp/p20-014e-browser/evidence/desktop-light-operations-scheduled-operation.png`、`desktop-dark-operations-scheduled-operation.png`、`mobile-light-operations-scheduled-operation.png`、`measurements.json`

## Documentation Reviewer Findings

Final verdict: **Accept — P1: 0／P2: 0／P3: 0**。

Correction前の5件はすべて最新Source／Artifactで解消した。

- `OperationMetadata::$schedule`へPublic API表記を修正
- Misfireを設定可能Windowではなく固定FireOnce契約へ修正
- Occurrence Failure CountとTop-level `runtime_error`を分離
- Daily Cronの任意時刻初回`accepted: 0`と検証用`* * * * *`手順を追加
- Occurrence SQLへProject Root、Skeleton Container、別環境のRead-only接続Contextを追加

## Acceptance Criteria

- [x] Scheduled Operation Guide covers the required reader journey and main／Stable boundary.
- [x] Authoring, strategy, Context, Provider, CLI, counts／Exit, recovery, Occurrence／Journal, and process separation are documented.
- [x] Attributes／Core API／Journal／Releases／Troubleshooting references are synchronized; old unimplemented claim removed.
- [x] Website tests, check, build, focused PHP, Consumer, format, guard, and diff checks pass; Correction rerun is 74 tests.
- [x] Browser evidence covers 24 responsive conditions, Mobile navigation／local scroll, and 18 Guide links.
- [x] Documentation Reviewer final verdict is Accept with P1／P2／P3 all zero.
- [x] Report／STATE／TODO are synchronized; no Commit／Push／Deploy.

## Remaining Issues

P20-014EにRemaining Issueはない。Phase 20全体にはJournal／Outcome参照制御、Tenant分離、暗号化Capabilityと、構造化Log Schema／OpenTelemetry Adapterが残る。Commit、Push、Deployは実施していない。

## Suggested Next Action

Phase 20の次Taskとして、Journal／Outcome参照制御を先にDecision／Task Packet化する。OpenTelemetry Adapterは構造化Log SchemaとJournalの安全なProjection境界を確定してから着手する。

## Orchestrator Acceptance

2026-07-29T10:32:34+09:00、OrchestratorはSource／Test／Stable境界、Correction後のWebsite Gates、Read-only Documentation Reviewerの24条件Browser Evidenceを確認した。P20-014EをAcceptedとし、P20-014A〜EのScheduled Application Operation Sliceを完了する。Production Codeの追加変更、Commit、Push、Deployは行っていない。
