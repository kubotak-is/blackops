# P20-012 Documentation Editorial Pass Report

## Summary

全38 `docs/guide/*.md`をSpecification 97のPage Type MatrixでReviewし、一般語の日本語化、Stable／`main`／公開範囲の説明、RetentionとObserver Replayの実行手順を実装済みContractへ同期した。Glossary／Public API Concept、Command、Option、Route、Header、JSON、Error Code、Landing exact copy、Public Slug、Sidebar、Header、Banner、Search、Redirectは変更していない。表示Proseだけを対象にするFence／Inline Code aware Editorial Guardと、Code Comment／Mermaid Labelを検査するFixtureをWebsite Regressionへ追加した。

## Changed Files

- `docs/guide/README.md`
- `docs/guide/attributes.md`
- `docs/guide/authentication.md`
- `docs/guide/community-board.md`
- `docs/guide/configuration.md`
- `docs/guide/console-command.md`
- `docs/guide/database-migrations.md`
- `docs/guide/deployment.md`
- `docs/guide/execution.md`
- `docs/guide/first-operation.md`
- `docs/guide/frontend.md`
- `docs/guide/installation.md`
- `docs/guide/journal.md`
- `docs/guide/mvp-sample.md`
- `docs/guide/mvp-status.md`
- `docs/guide/observer-replay.md`
- `docs/guide/project-generators.md`
- `docs/guide/project-cli.md`
- `docs/guide/outbox.md`
- `docs/guide/retention.md`
- `docs/guide/security.md`
- `docs/guide/runtime-bootstrap.md`
- `docs/guide/testing.md`
- `docs/guide/troubleshooting.md`
- `docs/guide/why-blackops.md`
- `docs/website/README.md`
- `docs/website/content-map.mjs`
- `docs/website/scripts/check-content.mjs`
- `docs/website/scripts/editorial-guard.mjs`
- `docs/website/tests/guide-code.test.mjs`
- `docs/website/tests/reader-experience.test.mjs`
- `develop/orchestration/tasks/P20-012-documentation-editorial-pass.md`
- `develop/orchestration/reports/P20-012-documentation-editorial-pass.md`
- `develop/TODO.md`
- `develop/STATE.md`

Existing Phase 20 Working Tree changes outside this list were preserved. Generated Content and `docs/website/dist/` were not edited directly.

## Decisions and Assumptions

- Documentation Websiteは`https://blackops-php.pages.dev`のCloudflare Pages公開済みとして説明し、BlackOps BoardはRepository `main`のLocal／CI Reference Applicationで公開Hostなしとして分離した。
- RetentionはProject Root Hostの`php blackops`とContainerの`docker compose run --rm app php blackops`を同じ完全Optionで示した。Plan／dry-runは副作用なし、confirmだけが変更し、Runtimeの期待出力と終了Code 0を記載した。
- Observer Replayは新規dry-run（Selector＋Observer＋`--dry-run`）、新規confirm（Selector＋Observer＋Checkpoint＋Actor＋Reason＋`--confirm`）、resume confirm（`--resume`＋Actor＋Reason＋`--confirm`）の3 Modeを分離した。dry-runはCheckpoint／Actor／Payloadを出力せず、配送／Audit／Checkpoint／Canonical JournalへWriteしない。
- Editorial Guardは表示Markdownの見出し、表、Link Text、Callout Label、通常Proseを検査し、Code CommentとMermaidの`accTitle`／`accDescr`／Quoted LabelだけをFence内から検査する。Execution Token、Exact Shell／Text Output、JSON／JSONL、Inline Code、Link Target、HTML Comment、Mermaid Syntaxは保護する。Fence長の異なる```／~~~をFixtureで確認した。
- 一般語だけを文脈なしに機械置換せず、Official Product／BlackOps Concept／Public API／Exact Command Tokenを保持した。Troubleshootingの表示ラベルは日本語へ変更した。

## 38-Source Coverage Matrix

Page TypeはSpecification 97の割当。`Changed`は本文またはEditorial Guard対象の変更、`Unchanged`はReview済みでContract維持のため変更不要、ContractはSource／Regression照合結果を示す。

| Source | Page Type | Review | Contract |
| --- | --- | --- | --- |
| `README.md` | Orientation | Changed | Stable／main lane and landing links preserved |
| `why-blackops.md` | Concept | Changed | Headless／Operation／Journal concepts preserved |
| `core-concepts.md` | Concept | Unchanged | Operation／Value／Outcome／Journal boundary preserved |
| `operation-lifecycle.md` | Concept | Unchanged | Lifecycle states and Mermaid accessibility preserved |
| `journal.md` | Concept | Changed | Canonical／Observed boundary and JSONL contract preserved |
| `execution-context.md` | Concept | Unchanged | Identifier and context tables preserved |
| `security.md` | Concept | Changed | Framework／Application responsibility and 404／410 preserved |
| `attributes.md` | Reference | Changed | 24 Public Attribute list and marker exclusion preserved |
| `configuration.md` | Reference | Changed | Environment／Database／Runtime config examples preserved; internal release wording removed |
| `core-api.md` | Reference | Unchanged | 175 PublicApi types and Internal exclusion preserved |
| `directory-structure.md` | Reference | Unchanged | Skeleton ownership boundaries preserved |
| `glossary.md` | Reference | Unchanged | Required BlackOps terms and definitions preserved |
| `mvp-status.md` | Reference | Changed | Stable 1.1.0／main capability matrix and publication lanes corrected |
| `project-cli.md` | Reference | Changed | CLI command and option matrix preserved; headings and discovery prose clarified |
| `application-bootstrap.md` | How-to/Tutorial | Unchanged | HTTP／Console process boundary preserved |
| `authentication.md` | How-to/Tutorial | Changed | Session Core and credential boundary preserved; internal report wording removed |
| `authorization.md` | How-to/Tutorial | Unchanged | Authorize／Actor／Deferred reauthorization preserved |
| `community-board.md` | How-to/Tutorial | Changed | Local full-stack journey and credential-free boundary preserved |
| `console-command.md` | How-to/Tutorial | Changed | AsCommand／Help／Exit contract preserved; reader heading localized |
| `database-and-transactions.md` | How-to/Tutorial | Unchanged | Connection／Transaction／Outbox guarantees preserved |
| `database-migrations.md` | How-to/Tutorial | Changed | Application migration commands preserved; directory prose clarified |
| `database-seeding.md` | How-to/Tutorial | Unchanged | Seeder order and Build／Seed boundary preserved |
| `deployment.md` | How-to/Tutorial | Changed | HTTP／Worker operations and container command boundary preserved |
| `execution.md` | How-to/Tutorial | Changed | Inline／Deferred execution and Mermaid preserved; dense boundary prose split |
| `first-operation.md` | How-to/Tutorial | Changed | Generator／HTTP 202／Status／Outcome journey preserved |
| `frontend.md` | How-to/Tutorial | Changed | Generated Client and framework choice boundary preserved |
| `installation.md` | How-to/Tutorial | Changed | Stable 1.1.0 install command and main preview boundary preserved |
| `mvp-sample.md` | How-to/Tutorial | Changed | Quickstart source, auth, transaction, frontend and Docker lane preserved |
| `observer-replay.md` | How-to/Tutorial | Changed | Selector／Observer／dry-run／confirm／resume contract corrected |
| `operations.md` | How-to/Tutorial | Unchanged | Typed self-handled Operation and implicit Inline preserved |
| `outbox.md` | How-to/Tutorial | Changed | Dispatch／Relay／Retry／Dead Letter journey preserved; process link label localized |
| `outcome-retrieval.md` | How-to/Tutorial | Unchanged | Status／Outcome retrieval boundary preserved |
| `project-generators.md` | How-to/Tutorial | Changed | Generator ownership and generated-file boundary preserved |
| `retention.md` | How-to/Tutorial | Changed | Plan／dry-run／confirm host-container procedure and output corrected |
| `runtime-bootstrap.md` | How-to/Tutorial | Changed | Worker Mode and classic fallback preserved; default wording localized |
| `testing.md` | How-to/Tutorial | Changed | Five test layers and evidence commands preserved; evidence labels localized |
| `validation.md` | How-to/Tutorial | Unchanged | Protocol／Binding／Value／Business validation preserved |
| `troubleshooting.md` | Troubleshooting | Changed | 16 symptom sections and four guidance parts preserved with Japanese labels |

## Commands and Results

| Command | Result |
| --- | --- |
| `mise exec -- pnpm --dir docs/website run test` | PASS（73 tests、Correction cycle） |
| `mise exec -- pnpm --dir docs/website run check` | PASS（Content／links／diagrams／Blume check、38 pages） |
| `mise exec -- pnpm --dir docs/website run build` | PASS（39 pages、artifact／site guard；Orchestrator final run） |
| `docker compose run --rm app mago format --check src tests` | PASS（All files are already formatted） |
| Final `docker compose run --rm app mago format --check src tests` retry | PASS（Orchestrator final run） |
| `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\\.md:[0-9]+' src tests --glob '*.php'` | PASS（Management-ID guard） |
| `git diff --check` | PASS |
| Playwright全38 Route × 1440px Light／Dark＋390px Light | PASS（114 Page checks、active navigation、Pagination、Edit Link、Callout、overflowなし） |
| Playwright重点Content／Clipboard | PASS（Publication、Retention、Why、Observer Replay、Community Board、Search、copy success／failure） |
| Documentation Reviewer | Accept（P1／P2／P3 Findingなし） |

## Correction Cycle

Orchestrator再監査のP3/P2指摘へ、Task Report／Consumer Evidence／Phase番号／保守Evidence／Worker Retry Evidenceの表示語Guard、state-aware single／multiline HTML Comment除外、content-map descriptionの同一Guard、Community BoardとCloudflare Pagesの公開境界、Retentionの`idempotency_record_days`既定値／Plan Exit Code 0、README／installation／database-migrationsの一般語、Community Boardの確認ラベル、Security／Execution／CLIの長段落分割、利用者向け見出しを反映した。最終P2 CorrectionではHTML Comment処理をFence判定より先に行い、Comment内のPHP／Shell／Mermaid Fenceを除外し、Comment終了後の可視禁止語を拒否するFixtureを追加した。Public Contract、H1／Sidebar、Command／Option／Route／Header／JSON／Error Codeは維持した。

## Acceptance Criteria

- [x] 全38 Public Documentation SourceをPage Type MatrixでReviewし、変更不要PageをCoverageへ記録した。
- [x] Stable `1.1.0`／Repository `main`／Cloudflare Pages公開とBlackOps Board Local／CI onlyを同期した。
- [x] RetentionへHost／ContainerのPlan、dry-run、confirm、Policy／Actor、Runtime出力、終了Code 0を追加した。
- [x] Observer Replayのdry-run／confirm／resumeをRuntime SourceとTestへ照合し、dry-runの出力／書き込み境界を明記した。
- [x] 表示Prose、Code Comment、Mermaid Labelを検査し、Fence／Inline／JSON／Exact Output／Syntax保護をFixtureで固定した。
- [x] Glossary／Public API／Command／Option／Route／Header／JSON／Error Code／Landing／IA／Slug／H1を変更していない。
- [x] Website Test（73）／Content Check／Artifact／Site Check、Required PHP quality commands、Report／STATE／TODO／Task同期が完了した。
- [x] Commit、Push、PR、External Deployは実行していない。

## Remaining Issues

- P20-012のAcceptance Blockerはない。Orchestratorの最終Build、Browser Verification、Documentation Reviewまで完了した。
- Build時のVite chunk-size warningとAstroの`/[...slug]` root route conflict warningは既存の非致命Warningで、Artifact／Site CheckはPASS。

## Suggested Next Action

P20-012はAccepted。Commit／Push／PR／External Deployは別指示まで保留する。
