# P20-002 Worker Report: Documentation Content Correction

## Summary

P20-002の指定に合わせ、Blume LandingのHero／Operation／Journal／Headless本文を完全一致へ補正し、未指定のMarketing CopyとSection Numberを除去した。三要素を同一`landing-feature` DOMとGridへ統一し、Desktop三列／Mobile一列、Keyboard Focus、Reduced Motionを維持した。Sidebar対象PageのH1を公開Labelへ揃え、Authentication／Authorization／Frontendを利用者がCommand、設定、生成結果、Request／再認可／Drift確認まで辿れるHow-toへ拡充した。

## Changed Files

- `docs/website/pages/index.astro`
- `docs/website/theme.css`
- `docs/website/README.md`
- `docs/website/blume.config.ts`
- `docs/website/site-navigation.mjs`
- `docs/website/content-map.mjs`
- `docs/website/scripts/check-site.mjs`
- `docs/website/tests/reader-experience.test.mjs`
- `docs/website/tests/site-navigation.test.mjs`
- `docs/guide/README.md`
- `docs/guide/why-blackops.md`
- `docs/guide/installation.md`
- `docs/guide/mvp-sample.md`
- `docs/guide/directory-structure.md`
- `docs/guide/runtime-bootstrap.md`
- `docs/guide/validation.md`
- `docs/guide/outcome-retrieval.md`
- `docs/guide/execution.md`
- `docs/guide/operation-lifecycle.md`
- `docs/guide/console-command.md`
- `docs/guide/database-and-transactions.md`
- `docs/guide/database-migrations.md`
- `docs/guide/database-seeding.md`
- `docs/guide/mvp-status.md`
- `docs/guide/core-api.md`
- `docs/guide/attributes.md`
- `docs/guide/configuration.md`
- `docs/guide/project-cli.md`
- `docs/guide/application-bootstrap.md`
- `docs/guide/security.md`
- `docs/guide/troubleshooting.md`
- `docs/guide/glossary.md`
- `docs/guide/authentication.md`
- `docs/guide/authorization.md`
- `docs/guide/frontend.md`
- `develop/spec/83-blume-documentation-experience.md`
- `develop/decisions/116-blume-documentation-site.md`
- `develop/TODO.md`
- `develop/STATE.md`
- this report

## Exact Landing Copy and Layout

- Hero, Operation, Journal, and Headless copy are the exact Task contract, including `Javascript`, `NuxtJS`, punctuation, and Japanese wording.
- H1 is `BlackOps - The PHP Framework`; CTA label is `Whats BlackOps`.
- Forbidden `ONE MODEL / TWO PATHS`, `Operation ↔ Execution`, English marketing claims, and Section/Alphabet indexes are absent from the custom Landing.
- Three feature articles share the same `.landing-feature` class inside `.landing-features-grid`; CSS provides three equal columns and a one-column mobile breakpoint.

## Sidebar Title and Reader Journey Audit

Sidebar and source H1s now use `Whats BlackOps`, `Install`, `Quickstart and Skeleton`, `Directory`, `Local Runtime`, `Value and Validation`, `Outcome`, `Deferred`, `Lifecycle`, `ConsoleCommand`, `Transaction`, `Migration`, `Seeder`, `Authentication`, `Authorization`, `Frontend`, `Testing`, `BlackOps Board Reference Application`, `Deployment`, `Security`, `Troubleshooting`, `Releases`, and the Reference labels. Tutorial points to `testing/community-board`.

The strict reader test checks all 30 mapped sidebar labels against the corresponding `docs/guide/` H1. The Whats page now defines the PHP 8.5 Headless Operation Framework before discussing the problem it addresses, and its lifecycle section uses descriptive Journal wording rather than the rejected marketing slogan.

## Authentication

The guide now covers the exact DBAL/Migrations install, `make:auth`, generated Domain／Infrastructure／Feature／Provider／config／migration paths, `AUTH_REGISTRATION_ENABLED=true`, 8-hour TTL, 5-minute touch defaults, `database:migrate`, `composer dump-autoload`, `build:compile`, optional frontend generation, Bearer/Cookie middleware choice, Register／Login／authorized Request／Logout, and Application-owned credential／Cookie／CSRF boundaries.

## Authorization

The guide adds an `#[Authorize(ApplicationPolicy::class)]`／`AuthorizationRequest`／`AuthorizationDecision` example, Repository／Permission Service binding through the Service Provider, Anonymous／Unauthorized／Forbidden distinctions, Deferred actor-context re-authorization before side effects, and `OperationStatusAuthorizer` for status reads.

## Frontend

The guide states that BlackOps is Headless and supplies no UI or framework adapter. It documents `build:compile`, `frontend:generate`, `frontend:check`, the real `dirname(__DIR__) . '/resources/js/blackops'` default and configurable output path, generated-tree immutability, Application-owned Wrapper imports, request-scoped Factory binding, direct Operation Object call options, `.url()`／`.toRequest()`／`.fetch()`／`.status()`／`.wait()` with AbortSignal and finite timeout, and regenerate／check steps after Operation changes. Bound clients pass only bound wait options; the example narrows accepted Results before reading `operationId`.

## Commands and Results

- `mise exec -- pnpm --dir docs/website run test` — PASS, 46 tests (including strict Sidebar label／H1 synchronization).
- `mise exec -- pnpm --dir docs/website run check` — PASS, Content／Mermaid checks and Blume check: 0 errors, 0 warnings, 0 hints.
- `mise exec -- pnpm --dir docs/website run build` — PASS, 38 static pages; artifact guard and site/search checks passed for 37 routes. Vite chunk-size and custom `/` route conflict are non-fatal existing build warnings.
- `docker compose run --rm app mago format --check src tests` — PASS (`INFO All files are already formatted.`; rerun outside the restricted Docker socket sandbox).
- `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:' src tests --glob '*.php'` — PASS (no matches).
- `git diff --check` — PASS.
- Built Landing HTML is checked for exact Hero／Operation／Journal／Headless text and forbidden copy, not only source fragments.
- Generated `.blume/`, `.astro/`, `.generated/`, and `dist/` directories were removed after verification; they remain reproducible build outputs.
- Required dev-server launch was not repeated for P20-002 per Orchestrator instruction; P20-001 had already verified the local dev HTTP surface.

## Acceptance Criteria

- [x] Exact Hero and three feature copy.
- [x] Forbidden Landing copy absent.
- [x] Shared three-feature Grid: desktop three columns, mobile one column.
- [x] `Whats BlackOps` synchronized across Navigation, H1, and CTA.
- [x] Sidebar H1 and reader structure audited.
- [x] Authentication install-to-request path documented.
- [x] Authorization Policy, binding, and re-authorization documented.
- [x] Frontend generation, output path, import, runtime, and drift flow documented.
- [x] Existing detailed guides and security constraints retained.
- [x] Public URL, Search, and Artifact boundary preserved.
- [x] Website full gate passed.
- [x] Report／STATE synchronized; no Worker commit.

## Remaining Issues

No in-scope implementation issue remains. External publication／deploy and framework runtime changes remain out of scope.

## Suggested Next Action

P20-002はOrchestrator Acceptance済み。必要ならBlume Dev Serverの実画面を確認し、確定後にCommit／Push workflowへ進む。

## Orchestrator Acceptance

2026-07-25T11:01:12+09:00にOrchestratorが実装SourceとGuide記載を独立照合し、Auth Migration／Route、Authorization Public API／Status認可、Frontend Result narrowing／bound wait option、Browser Title、全30 Sidebar Label／H1をReviewした。Website 46 tests、Blume check 37 pages 0 errors、Static 38 pages、Artifact／Search 37 routes、Mago format、Management ID guard、`git diff --check`を再実行して成功した。

既存Port 4321が使用中のためBlume Dev Serverを`http://localhost:4322/`で起動し、HTTP 200を確認した。P20-002をAcceptedとする。Commit／Push、External Publication／Deployは実行していない。
