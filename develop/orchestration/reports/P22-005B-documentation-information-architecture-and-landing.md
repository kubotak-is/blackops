# P22-005B Report: Documentation Information Architecture and Landing

Status: Accepted
Updated At: 2026-08-17T03:32:57+09:00

## Summary

P22-005B reorganized the public Documentation navigation and Landing within the allowed scope. The Content Map now declares one canonical Section for all 41 guide sources, and the 40 non-index public slugs are placed exactly once in the canonical seven-section order: Start Here, Build, Async and Lifecycle, Data and Security, Operate, Reference, Releases. Navigation validation is fail-closed for missing, unknown, duplicate, wrong-section, and reordered entries.

The custom Landing now keeps the existing BlackOps and The PHP Framework product language while providing direct Install, Quickstart and Skeleton, and First Operation paths; the Stable `1.2.0` install command; an actual Operation PHP sample with exactly one `#[OperationType('report.generate')]` in Route → OperationType → Deferred order; an Operation, Inline and Deferred, Lifecycle and Journal mental model; and purpose navigation for the six downstream sections. The design remains solid-surface and documentation-oriented, with one restrained eyebrow, ordered-list semantics without visible generic step numbers, local code scrolling, Light and Dark focus states, explicit responsive collapse, and reduced-motion CSS. Landing-specific gradients, grid or glow decoration, three equal feature cards, fake screenshots, status dots, version badges, new dependencies, and assets were not added.

The bounded Sol xHigh correction is complete: the raw Landing now has exactly the canonical seven level-two headings in order, generated `index.md` and the Landing segment of `llms-full.txt` are guarded against recombination, focus uses the accent token with pure Light/Dark contrast fixtures and emitted CSS checks, the Landing root is non-landmark so PageLayout supplies the sole visible `main`, and Releases emits one direct `root: 'releases/current-status'` entry with one page-current navigation target.

## Changed Files

Only Task Packet allowed files were changed for P22-005B. Existing unrelated P22-005A, P20-009H, and P23-001 worktree changes were preserved.

- `docs/guide/README.md`
- `docs/website/README.md`
- `docs/website/content-map.mjs`
- `docs/website/site-navigation.mjs`
- `docs/website/pages/index.astro`
- `docs/website/theme.css`
- `docs/website/scripts/check-site.mjs`
- `docs/website/tests/site-navigation.test.mjs`
- `docs/website/tests/reader-experience.test.mjs`
- `tests/Consumer/version-baseline.sh`
- `develop/orchestration/tasks/P22-005B-documentation-information-architecture-and-landing.md`
- `develop/orchestration/tasks/P22-005-documentation-governance-and-information-architecture.md`
- `develop/orchestration/reports/P22-005B-documentation-information-architecture-and-landing.md`
- `develop/TODO.md`
- `develop/STATE.md`

No guide page slug, source filename, canonical URL, redirect, Release Authority tuple, historical allowlist, Framework, Skeleton, PHP, Package, Tag, Release, dependency, asset, generated source, external state, Commit, Push, PR, CI dispatch, Deploy, or Release was changed.

## Decisions and Assumptions

- `docs/website/content-map.mjs` uses a `section` field as the single source for Content Map membership. `validateNavigation` checks that every mapped entry has a canonical Section, that the Landing remains in Start Here without Sidebar duplication, that each Sidebar entry matches the Content Map Section, and that Releases is a direct singleton root.
- The existing public `Releases` H1 and slug are preserved while the native Blume navigation receives one direct `root: 'releases/current-status'` entry; no same-name collapsible parent is emitted.
- The focus fixture parses the current Light/Dark `--bo-accent`, `--bo-paper`, and `--bo-surface` values from `theme.css`, proves each focus pair is at least 3:1, and proves the existing Light orange action token fails the threshold. The same `--bo-focus` token is applied to Landing links and active Sidebar focus.
- The Landing operation sample uses one rendered `#[OperationType('report.generate')]` annotation between Route and Deferred. Source and built HTML assert exact count/order, while a missing-attribute fixture fails closed.
- `check-site.mjs` guards source headings plus generated `dist/index.md`, the Landing `llms-full.txt` segment, visible-main/skip-target counts, emitted focus CSS, and direct Releases navigation. A transient first build failure was a missing guard import only; the corrected complete build passed.
- The Landing's actual Operation code is the product visual. No bitmap, external image, external font, hand-rolled SVG, or fake dashboard was introduced.
- The design preflight was applied at the source and artifact guard level: one eyebrow remains, the ordered list supplies journey semantics without visible `01`/`02`/`03` labels, and `contain: inline-size` plus local `overflow-x: auto` code hosts prevent the page from depending on `overflow-x: hidden`.
- `check-site.mjs` scopes Landing CSS decoration checks to the emitted Landing stylesheet segment because Blume's shared CSS contains unrelated gradient utility classes. The first build exposed this over-broad check; the bounded guard correction was made within the allowed file and the final build passed.
- Release Documentation impact remains unchanged: `currentStable=1.2.0`, existing capability mapping, exact six-entry historical `1.1.0` allowlist, 41-source route inventory, 40 Sidebar pages, and four redirects are preserved.

## Commands and Results

| Command | Result |
| --- | --- |
| `mise exec -- pnpm --dir docs/website run release:check:source` | PASS — current source claims and historical allowlist guard |
| `mise exec -- pnpm --dir docs/website run test` | PASS — 95/95 Website tests, including IA, OperationType, contrast, root, and Releases positive/negative fixtures |
| `mise exec -- pnpm --dir docs/website run check` | PASS — content determinism, diagrams, strict Blume validation, and type check |
| `mise exec -- pnpm --dir docs/website run build` | PASS — final complete rerun emitted 42 pages; Artifact guard, site navigation, Landing links, canonical headings, OperationType, focus CSS, direct Releases nav, redirects, Search, and accessibility checks passed |
| `mise exec -- pnpm --dir docs/website run release:check:artifact` | PASS — generated HTML, metadata, Search, raw Markdown, and LLM artifacts |
| `bash -n tests/Consumer/version-baseline.sh` | PASS |
| `bash tests/Consumer/version-baseline.sh` | PASS — `published=1.2.0 historical=1.1.0`, IA and Landing recurrence contracts included |
| `docker compose run --rm app mago format --check src tests` | PASS — all files already formatted |
| `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+\|D[0-9]{3}\|P[0-9]+-[0-9]+\|TODO\\.md:[0-9]+' src tests --glob '*.php'` | PASS — no forbidden management references |
| `git diff --check` | PASS |

The first post-correction build attempt reached Artifact PASS but stopped in `check-site.mjs` with `ReferenceError: repositoryRoot is not defined` after the new source guard was added. The import was corrected within the allowed guard file, the standalone site check passed, and the complete build was rerun successfully. Blume retained its existing non-fatal chunk-size and route-conflict warnings; no Task failure remained.

## Acceptance Criteria

- [x] Sidebar has the canonical seven Sections in exact order.
- [x] All 40 non-index public pages match their Content Map Section and appear exactly once.
- [x] Source filenames, public slugs, and the four Redirects are unchanged.
- [x] Landing reaches Install, Quickstart and Skeleton, and First Operation through direct actions.
- [x] Landing exposes Stable `1.2.0` install, the current BlackOps mental model, and Build, Async and Lifecycle, Data and Security, Operate, Reference, and Releases lanes.
- [x] Landing does not use Landing-specific gradients, grid or glow decoration, three equal feature cards, or fake screenshots.
- [x] Source and Artifact guards cover Light and Dark focus states, responsive collapse, local code overflow, reduced motion boundaries, canonical Landing headings, OperationType metadata, one visible main, and direct Releases navigation.
- [x] Source and Artifact release guards, Website test (95/95), check, final complete build, and site check pass.
- [x] Mago, PHP management-ID, version baseline, and diff checks pass.
- [x] Independent Sol xHigh Documentation Review returns P1=0 and P2=0.
- [x] No Commit, Push, PR, CI dispatch, Deploy, Release, or external mutation was performed.

## Independent Documentation Review

Sol xHigh Documentation Review returned P1=1／P2=3／P3=1 and did not permit Acceptance.

- P1: the Landing's actual Operation sample omits mandatory `#[OperationType('report.generate')]` and would fail Stable `1.2.0` metadata compilation.
- P2: raw Markdown／`llms-full.txt` recombine canonical Reference and Releases lanes as `Reference and Releases`.
- P2: the Light theme focus outline has only 2.574:1 contrast against the Landing background.
- P2: the generated Landing nests its own `main` inside `PageLayout`'s `main`, producing two visible main landmarks.
- P3: the singleton Releases Section renders a `Releases` parent with a same-name `Releases` child instead of one direct entry.

The five findings are resolved within the authorized scope. The complete required command set was rerun after all Source changes; its evidence supersedes the pre-correction Green results. Independent Sol xHigh re-review and Orchestrator Acceptance remain pending.

### Re-review Verdict

The exact post-correction uncommitted Working Tree received P1=0／P2=0／P3=0 and P22-005B Acceptance permission. The reviewer independently confirmed one Stable-valid OperationType in exact order, canonical raw／LLM headings, CSS-bound Light／Dark contrast, one PageLayout main and skip target, one direct Releases target with unique `aria-current`, preserved 41-source／40-page／four-redirect inventory, and no new regression. Orchestrator also reran the complete Required Commands and directly confirmed `main=1`、`OperationType=1`、Releases target=1、canonical raw／LLM headings, and focus contrast Light 5.027／5.473 and Dark 12.545／11.256.

## Remaining Issues

- P22-005B has no remaining in-scope implementation, verification, or review work.
- Browser and production canonical verification are out of scope for this child and remain P22-005D work.
- Full public-page Tutorial, How-to, Concept, and Reference migration remains P22-005C work.
- No Worker Commit exists. The unrelated pre-existing worktree changes remain under Orchestrator scope and were not staged or modified by this worker.

## Suggested Next Action

Begin P22-005C full-page reader-contract migration as a separate Task Packet without committing this combined dirty Working Tree yet. Keep P22-005D browser and production verification, exact reviewed commit flow, same-SHA CI and Documentation delivery, and Website production canonical checks pending.

## Release Documentation Impact

- Authority tuple and Capability IDs: unchanged `currentStable=1.2.0`; no Release Authority or historical allowlist modification.
- Public Source and route inventory: 41 Content Map sources, 40 Sidebar pages, and Landing `/`; source filename, slug, canonical route, and four Redirect sets are unchanged.
- Version occurrence classification: current Stable `1.2.0` install wording is retained; no candidate, main-only, or historical allowlist change was introduced.
- Source, Search, HTML, raw Markdown, and LLM artifact evidence: source and artifact release guards passed after the final 42-page build; positive and negative navigation/Landing fixtures passed in the Website suite.
- Same-SHA CI, Documentation delivery, and Production deploy: local verification only; no Commit, Push, CI dispatch, Deploy, Release, or external mutation occurred.
- Remaining parent work: P22-005C full-page migration and P22-005D browser/production verification, followed by exact reviewed commit and same-SHA delivery.
