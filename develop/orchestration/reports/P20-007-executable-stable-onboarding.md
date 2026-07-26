# P20-007 Executable Stable Onboarding

## Summary

Stable `1.1.0` now has an explicit Docker Compose lane from `create-project` through anonymous `/welcome` HTTP 200 and shutdown. The main Preview Quickstart separates Stable handoff, shows the current PHP Operation/Value/Outcome before generated TypeScript, and provides a generated-client `try-client.ts` compile/run path. First Operation, Authentication, Local Runtime, Outbox, and Console Command now use explicit container context, current generator/consumer contracts, and Troubleshooting links.

## Changed Files

- `docs/guide/installation.md`
- `docs/guide/mvp-sample.md`
- `docs/guide/first-operation.md`
- `docs/guide/authentication.md`
- `docs/guide/runtime-bootstrap.md`
- `docs/guide/outbox.md`
- `docs/guide/console-command.md`
- `docs/guide/testing.md`
- `docs/website/tests/guide-code.test.mjs`
- `docs/website/tests/reader-experience.test.mjs`
- `develop/orchestration/tasks/P20-007-executable-stable-onboarding.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Stable documentation excludes Seeder, Authentication, Authorization, Frontend generation, and pnpm; `database:migrate`, `build:compile`, and HTTP remain explicit Container commands.
- Preview `try-client.ts` is compiled with the repository's existing TypeScript dependency and CommonJS runtime marker before Node execution, avoiding Node 24 extensionless ESM import failures.
- Quickstart Report completion documents the current `reportName` and `location` Outcome properties; generic Outcome Retrieval remains separate.
- Authentication examples follow `auth-generator-fresh.sh`: duplicate register is 409 `auth.email_unavailable`, invalid/missing credentials are 401 `auth.invalid_credentials`, and logout sends JSON `{ "token": ... }` and returns 200 `{}` idempotently.

## Commands and Results

- `pnpm test` (docs website): PASS, 53 tests.
- `pnpm run content:generate`: PASS.
- `pnpm run content:check`: PASS.
- `pnpm run diagrams:check`: PASS.
- `pnpm run blume:validate`: PASS, no broken links.
- `pnpm run site:check`: BLOCKED after build artifact removal because `dist/blume-search.json` is unavailable (dependent on blocked build).
- `pnpm run build`: BLOCKED by sandbox `listen EPERM: operation not permitted 0.0.0.0` from Blume/Astro font server.
- `bash tests/Consumer/quickstart-e2e.sh`: PASS (`Quickstart consumer E2E passed.`, interactive wall approximately 70 seconds). A later timed rerun was blocked immediately by Docker API permission (`real 0.11s`), not by the journey.
- `bash tests/Consumer/auth-generator-fresh.sh`: PASS (`Auth generator fresh consumer journey passed.`, interactive wall approximately 80 seconds).
- `docker compose run --rm app mago format --check src tests`: PASS.
- Management-ID guard and `git diff --check`: PASS.

The final review correction rerun kept `pnpm test` at 53 passing tests and `content:generate`, `content:check`, `diagrams:check`, `blume:validate`, and `git diff --check` passing. The generated-client command now uses `../../resources/js/blackops`, TypeScript union narrowing, and the existing compile/runtime marker path.

Orchestrator follow-up correction: TypeScript 6's `TS5112` is avoided by `--ignoreConfig --ignoreDeprecations 6.0`; the client now throws a safe fixed error on non-completed responses. A temporary Consumer with a generated tree compiled the exact command successfully. Runtime execution could not reach HTTP in this sandbox (Docker/API and loopback bind permissions), so no false runtime PASS is claimed. Authentication now lists both generated User migrations.

The previously passing Mago and Consumer results remain the authoritative runs; final reruns after the correction were blocked by the sandbox Docker API permission (`postgres:18` image access denied).

## Acceptance Criteria

- Stable create-project, `--no-scripts`, migration/build, anonymous Welcome 200, and shutdown: implemented.
- Stable/main boundary, PHP-first Quickstart, executable generated client, Outcome property, and placeholder Operation IDs: implemented.
- First Operation context and Authentication generator/build/status/logout contract: implemented.
- Runtime token channel, worker/scheduler profiles, Outbox dispatch/relay, Console Operation Command, Troubleshooting links: implemented.
- Website regression coverage and consumer journeys: implemented and passing.

## Remaining Issues

- P20-007のBlockerは残っていない。Worker環境で失敗したBlume static build／`site:check`はOrchestrator環境で成功した。

## Suggested Next Action

P20-008でGlossary、Rejection Category、Retry／Backoff、Retention Key、Path Parameter、Lifecycle State等のReference欠番を埋める。WorkerはCommitしていない。

## Orchestrator Review

OrchestratorはStable Tag、current Quickstart、Auth Generator Stub、Guide Source、生成Artifact、localhost実HTTPを独立Reviewした。初回差分でQuickstart Outcomeをgeneric例と混同した誤り、Generated Clientを使わない`try-client.ts`、Logoutの誤ったHeader契約、Authenticationの英語化／疑似Stubを差し戻した。再Reviewで相対Import、Result narrowing、重複H2、Stable Runtimeへのmain Token混入を差し戻し、最終ReviewでTypeScript 6の`TS5112`を実Commandから検出して`--ignoreConfig --ignoreDeprecations 6.0`へ補正した。

最終Evidence:

- `mise exec -- pnpm --dir docs/website run test`: PASS、53 tests
- `mise exec -- pnpm --dir docs/website run check`: PASS、Blume 37 pages、0 errors／warnings／hints
- `mise exec -- pnpm --dir docs/website run build`: PASS、38 pages、37 public routes、Artifact／Site Guard
- `bash tests/Consumer/quickstart-e2e.sh`: PASS、`Quickstart consumer E2E passed.`
- `bash tests/Consumer/auth-generator-fresh.sh`: PASS、`Auth generator fresh consumer journey passed.`
- `docker compose run --rm app mago format --check src tests`: PASS
- Management ID Guard、`git diff --check`: PASS
- `http://localhost:4322/`、Quickstart、Authentication: HTTP 200
- localhost Quickstart Artifact: `try-client.ts`、`--ignoreConfig`、Step 4 Anchorを確認
- localhost Authentication Artifact: User Migration、Authentication Middleware、409／401 Codeを確認

Framework `src/**`、Generator Stub、Example Source、Consumer Test、Stable Tag、Public Slug、External Publication／Deployは変更していない。P20-007をAcceptedとする。
