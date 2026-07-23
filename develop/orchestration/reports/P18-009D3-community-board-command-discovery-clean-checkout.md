# P18-009D3: Community Board Command Discovery Clean-checkout Correction Report

## Summary

Removed the Community Board's stale `app.command_discovery` entry after the final Application-owned Symfony Command was deleted. The configuration no longer points build-time discovery at the absent `app/Console` directory. Added a Clean Install guard so a future reintroduction of that root fails before the consumer journey proceeds.

No Framework source, Command Discovery contract, Public API, Phase 19 contract, workflow, or external runtime was changed. Worker did not commit.

## Failure Cause and Evidence

- GitHub Actions Run `30004535286` completed ripgrep installation and Mago／PHPUnit／Deptrac successfully, but Community Board Browser and Clean Install failed during `build:compile`.
- `examples/community-board/config/app.php` still declared `dirname(__DIR__) . '/app/Console'` under `command_discovery`.
- The final Application-owned `CommunityBoardSeedCommand.php` was removed when Seeder execution moved to the Framework-owned command, so the configured discovery root no longer existed.
- The Command Discovery contract intentionally fails fast for a missing root; this task removes the stale Application configuration rather than weakening that contract.

## Changed Files

- `examples/community-board/config/app.php`
- `tests/Consumer/community-board-clean-install.sh`
- `develop/STATE.md`
- `develop/orchestration/reports/P18-009D3-community-board-command-discovery-clean-checkout.md`

The Task Packet was not otherwise broadened. `tests/Consumer/community-board-browser.sh` remained unchanged because its existing `build:compile` journey directly covers the regression.

## Decisions and Assumptions

- A Consumer without Application-owned Commands omits `command_discovery` entirely, as required by the Console and build-discovery specifications.
- The Clean Install script asserts that neither `command_discovery` nor `app/Console` appears in the Community Board config before dependency/runtime work. This is a narrow configuration regression guard; the Browser journey continues to validate the full runtime path through `build:compile` and Playwright.
- The initial Browser invocation found dependencies absent because the preceding clean checkout state had already been cleaned. Dependencies were restored in an isolated consumer setup, the Browser journey was rerun successfully, and the final Clean Install run removed generated dependencies again.
- Existing Mago lint note/help messages in Framework files are unchanged and remain informational with exit status 0.

## Commands and Results

- `mise exec -- bash tests/Consumer/community-board-clean-install.sh` — PASS; clean Composer／pnpm install, config guard, `build:compile`, migration, deterministic seed repeat, Svelte check (0 errors／0 warnings), Vitest 7 files／43 tests, production build, real login/feed/detail HTTP, database／artifact security guards, and cleanup.
- `mise exec -- bash tests/Consumer/community-board-browser.sh` — PASS; migration, `build:compile`, frontend generation/check, Svelte check (0 errors／0 warnings), Vitest 7 files／43 tests, production build, real HTTP, worker retry coordination, and Playwright 1 test.
- `docker compose run --rm app mago format --check src tests examples` — PASS; all files already formatted. The gate was run after Consumer cleanup so it did not traverse the example's dependency symlink.
- `docker compose run --rm app mago lint` — PASS (exit 0); existing empty-loop note and `else` help only.
- `docker compose run --rm app mago analyze` — PASS; no issues found.
- `docker compose run --rm app vendor/bin/phpunit tests/Internal/Application/ApplicationCommandDiscoveryTest.php tests/Internal/Application/ApplicationCommandDiscoveryIntegrationTest.php` — PASS; 17 tests／70 assertions.
- `docker compose run --rm app mago format --check src tests` — PASS; all files already formatted.
- `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'` — PASS; no management IDs found.
- `bash -n tests/Consumer/community-board-clean-install.sh` — PASS.
- `git diff --check` — PASS.

## Orchestrator Review

2026-07-23T21:05:00+09:00に、OrchestratorはTask Packet、確定仕様、GitHub Actions Run `30004535286`の失敗Job、Worker差分を独立Reviewした。存在しないDiscovery Rootを許容するFramework変更ではなく、Application-owned Commandを持たないCommunity Boardから不要設定だけを削除していること、Clean Install Guardが同じ設定ドリフトを検出すること、変更が許可File内に収まることを確認した。

- Focused Command Discovery PHPUnit — PASS、17 tests／70 assertions。
- Community Board Clean Install — PASS。Fresh dependency install後の`build:compile`、migration、seed、frontend、実HTTP、security guard、cleanupを完走した。
- Management ID Guard、Shell Syntax、`git diff --check` — PASS。
- Worker Commitなし。

## Acceptance Criteria

- [x] Community Board config no longer references the absent `app/Console` discovery root.
- [x] `build:compile` succeeds in the Clean Install and Browser journeys.
- [x] `community-board-browser.sh` succeeds.
- [x] `community-board-clean-install.sh` succeeds.
- [x] Mago Format／Lint／Analyze and focused Command Discovery PHPUnit succeed.
- [x] AGENTS.md management-ID guard and `git diff --check` succeed.
- [x] Framework Command Discovery contract, Public API, and Phase 19 contract are unchanged.
- [x] Worker Commitなし。

## Remaining Issues

- No implementation or CI blocker remains. Accepted changeは`d17b24f`として`main`へPushされ、replacement CIは全Job成功した。
- External publication／deployment was not performed.

## Suggested Next Action

Proceed to Phase 19 Reliability and Delivery planning from D109 and the Roadmap.

## GitHub Actions Closeout

- Commit: `d17b24fc7865a968732f279ff031c552ee8e74fb`
- CI Run: `30005659143` — PASS、5／5 Jobs。
- Community Board Clean Install: PASS、1m31s。
- Community Board Full-stack Product Journey: PASS、4m40s。Browser、Foundation、Identity、Posts／Comments、Product、Deferred Digest、Artifact Guard、Cleanupを完走した。
- Mago／PHPUnit／Deptrac、Frontend Contract、Documentation Website: PASS。
- Documentation Delivery Run: `30005659104` — PASS。
