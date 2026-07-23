# P18-009D2: GitHub Actions Toolchain Correction Report

## Summary

Pinned ripgrep 15.2.0 in the Repository-managed mise toolchain. Both failing Community Board GitHub Actions jobs already execute `jdx/mise-action@v4` with `install: true`, so they now receive `rg` without depending on the hosted runner image. The previously failing Foundation and Clean Install consumers passed through `mise exec`, and the full CI Mago step passed. No Production Code, Test Code, workflow structure, Public API, or Phase 19 contract changed.

## Failure Cause and Evidence

- GitHub Actions Run `29906792935`, Job `Mago, PHPUnit, and Deptrac`, stopped at `mago format --check` because one Quickstart import order required formatting. Commit `b7d8d81` already contains the corrected order.
- Job `Community Board full-stack product journey` failed at `tests/Consumer/community-board-foundation.sh: line 97` with `rg: command not found`.
- Job `Community Board clean install and seed` failed at `tests/Consumer/community-board-clean-install.sh: line 55` with `rg: command not found`, then returned exit 2 because the sensitive-marker guard could not inspect the database dump.
- The hosted Ubuntu runner image did not provide `rg`, while the Consumer Scripts and workflow guards require it.

## Changed Files

- `mise.toml`
- `develop/STATE.md`
- `develop/orchestration/tasks/P18-009D2-github-actions-toolchain-correction.md`
- `develop/orchestration/reports/P18-009D2-github-actions-toolchain-correction.md`

## Decisions and Assumptions

- Pin `ripgrep = "15.2.0"` beside the existing Node and pnpm versions instead of adding an unpinned `apt-get` step.
- Reuse the existing `jdx/mise-action@v4` steps. The action documents that `install: true` runs `mise install`, and both affected jobs already use that setting before their Consumer Script.
- Keep `.github/workflows/ci.yml` unchanged because the existing action configuration supplies every tool declared by the Repository `mise.toml`.
- Keep Consumer Scripts unchanged; `rg` is an intentional cross-consumer security and generated-artifact guard.
- No worker delegation was required because the Task changed Repository Toolchain／Orchestration files only and made no Production Code or Test Code change.

## Commands and Results

- `mise install` — initial sandboxed attempt could not write the mise cache or access the GitHub release API; rerun with the required filesystem／network permission passed and installed ripgrep 15.2.0 with checksum verification.
- `mise exec -- rg --version` — PASS, ripgrep 15.2.0 with PCRE2 10.45.
- `mise current` — PASS; Node 24.18.0, pnpm 11.12.0, ripgrep 15.2.0.
- `mise exec -- bash tests/Consumer/community-board-foundation.sh` — PASS; Svelte 0 errors／0 warnings, Vitest 7 files／43 tests, production build, real HTTP availability／fallback, generated／sensitive guards, and final Foundation journey.
- `mise exec -- bash tests/Consumer/community-board-clean-install.sh` — PASS; clean Composer／pnpm install, deterministic migration／seed, Svelte 0 errors／0 warnings, Vitest 7 files／43 tests, production build, real login／feed／detail journey, database／artifact sensitive-marker guards, and final Clean Install journey.
- `docker compose run --rm app mago format --check src tests examples` — PASS, all files formatted.
- `docker compose run --rm app mago lint` — PASS; the existing empty FrankenPHP loop note and RuntimeContainerCompiler `else` help remain informational.
- `docker compose run --rm app mago analyze` — PASS, no issues.
- `docker compose run --rm app mago format --check src tests` — PASS, AGENTS.md required format gate.
- Management-ID Guard — PASS.
- `git diff --check` — PASS.
- Clean Install cleanup — PASS; Community Board `.env`, `vendor`, `node_modules`, generated, build, and runtime artifacts were removed.

## Acceptance Criteria

- [x] `mise.toml` declares pinned ripgrep 15.2.0.
- [x] mise installs and executes the pinned version.
- [x] Both affected CI jobs already run `jdx/mise-action@v4` with `install: true`.
- [x] Full CI Mago format, lint, and analyze pass.
- [x] Community Board Foundation passes through the mise toolchain.
- [x] Community Board Clean Install passes through the mise toolchain.
- [x] AGENTS.md Mago／management-ID／diff guards pass.
- [x] No Production Code, Test Code, or CI workflow structure changed.
- [x] Worker Commitなし.

## Remaining Issues

GitHub has not executed the corrected toolchain because the local branch is still ahead of `origin/main` and this Task has not been committed or pushed. The current failed Run remains historical evidence until the accepted local commits are pushed and a new run completes. No implementation blocker remains.

## Suggested Next Action

Commit P18-009D2 with the accepted local Phase 18 follow-up commits, push `main`, and monitor the new GitHub Actions run through completion before starting the first Phase 19 Task Packet.
