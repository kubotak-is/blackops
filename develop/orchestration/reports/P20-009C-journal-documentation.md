# P20-009C Journal Documentation Report

## Summary

Landingで紹介していたJournalを独立したReader-facing Guideとして追加し、`/concepts/journal`、Operation SectionのLifecycle直後のSidebar、Landing CTAを同期した。Canonical JournalとObserved Projectionを区別し、現在の10 Lifecycle Event、`JsonlJournalRecordEncoder`準拠のObserved JSONL、Event固有`data`、JSONL設定、Observer Replay、Retention／Security、OpenTelemetry未実装境界を説明した。

## Changed Files

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
- `develop/orchestration/tasks/P20-009C-journal-documentation.md`
- `develop/orchestration/reports/P20-009C-journal-documentation.md`

## Decisions and Assumptions

- JSONL例はCanonical PostgreSQLの公開シリアライズではなく、Observed Projectionの例とした。
- JSONLのField順と構造は`JsonlJournalRecordEncoder`へ照合し、Actor IDはObserved projectorの`[masked]`、`attempt.started`の`data`は`EmptyJournalData`の`{}`とした。
- `#[Sensitive]`の既定はOmitとし、Mask／HMAC Hashは選択可能なProjection方針として説明した。
- OpenTelemetry Adapter／Exporter／Configurationはmainで未実装とし、Span／Span Event／Metric／Trace Contextは将来候補の方向だけを示した。
- Landing Journal FeatureはCTAを一つに保ち、旧site guardもJournal Routeへ同期した。JSONL設定例は`config/journal.php`のApplication-owned編集手順を追加し、`best_effort`はObserver FailureをOperation失敗にしない現行契約、Canonical暗号化／KMSはFramework APIではなくApplication／Infrastructure／運用責務として明記した。
- Reviewer P1 correctionでDeferred JSONLを`received → accepted → attempt.started`（sequence 1／2／3）へ修正し、Record ID／Operation ID／Correlation IDの関係、nullable actors、Inline／Deferred Outcome境界のRegressionを追加した。
- Final wording correctionで`operation.accepted`の`EmptyJournalData` assertion messageを実際のEventへ同期した。Website test 61／61と`git diff --check`を再実行してPASSした。
- 既存Phase20のWorking Tree差分は保持し、Task Packetの許可File以外は変更していない。Commitは作成していない。

## Commands and Results

- `mise exec -- pnpm --dir docs/website run test` — PASS（61 tests）
- `mise exec -- pnpm --dir docs/website run check` — PASS（Blume check 38 pages、0 errors／warnings／hints）
- `mise exec -- pnpm --dir docs/website run build` — PASS（39 pages、artifact／site check PASS）
- `docker compose run --rm app mago format --check src tests` — PASS（All files are already formatted）
- `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'` — PASS（該当なし）
- `git diff --check` — PASS

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

## Remaining Issues

なし。Website Buildの既存Vite chunk-size warningとAstro route conflict warningは成功結果を妨げない既存警告として残る。

## Orchestrator Review

Read-only Documentation Reviewerは初回Reviewで、Deferred JSONLの`operation.accepted`欠落とIdentifier関係、Inline／Deferred Outcome Store境界、`actors`全体のnullable契約をP1 Findingとして返した。Correction後の再Reviewで3件すべてResolved、Remainingなしを確認した。

OrchestratorはJournal Implementation、Guide、Navigation、Landing、Regressionを独立Reviewし、Website test 61件、Blume check 38 pages、build 39 pages／artifact／site check、Mago format、Management-ID guard、git diff --checkを再実行してPASSした。最初のBlume checkは起動中dev serverのRuntime保護Guardで停止したため、serverを停止して直列再実行しPASSした。

Playwright Chromiumによる実Browser Reviewでは、Desktop 1440px Light／DarkとMobile 390pxで`/concepts/journal` HTTP 200、H1 `Journal`、Sidebar current、横Overflowなし、JSONL／Configuration Code、必要Headingを確認した。Landingは3 Featureがすべて高さ452.84px、各Feature 1 CTAで、Journal CTAは`/concepts/journal`へ遷移した。

## Suggested Next Action

P20-009CをAcceptedとし、Phase20のP20-010 Task-oriented Guide増強へ進む。
