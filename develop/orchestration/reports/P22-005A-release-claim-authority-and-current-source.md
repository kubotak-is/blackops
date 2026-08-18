# P22-005A Report: Release Claim Authority and Current Source

Status: Accepted
Updated At: 2026-08-17T01:05:18+09:00

## Summary

P22-005A synchronized current-facing public Documentation to the live Experimental Stable `1.2.0` release and introduced one machine-readable Release Authority shared by Source and generated Artifact checks. The exact six-entry `1.1.0` historical allowlist is enforced by path, heading, normalized sentence, category, and reason. Search, raw Markdown, HTML/metadata, `llms.txt`, and `llms-full.txt` are checked through the same dependency-free Node classifier.

The bounded re-review correction closes three confirmed fail-open paths and preserves the minified Search fix: page mappings are structured and lane-bound through the full Source checker, Roadmap/current-version and Japanese unpublished claims are derived from Authority, HTML metadata/OG/Twitter/JSON-LD attributes are extracted, and artifact history is exact after structure normalization with no substring/partial bypass. The final exact-history correction removes artifact prefix rewind/terminal truncation, structurally separates Search JSON headings/table rows, preserves public `Repository main Preview` heading anchors, and checks full artifact prefix/suffix/current-context fixtures.

## Changed Files

All candidate changes remain within the corrected P22-005A Files Allowed scope. D143 and Spec104 are explicitly included candidate management files; the existing unrelated P23-001 proposal remains excluded and other Orchestrator worktree changes were preserved.

- Release contract and management: `develop/spec/release-authority.json`, `develop/spec/README.md`, D143 `develop/decisions/143-documentation-release-truth-and-information-architecture.md`, Spec104 `develop/spec/104-documentation-release-lifecycle-and-information-architecture.md`, `develop/TODO.md`, `develop/STATE.md`, `develop/orchestration/tasks/P22-005-documentation-governance-and-information-architecture.md`, `develop/orchestration/tasks/P22-005A-release-claim-authority-and-current-source.md`, `develop/orchestration/reports/P22-005A-release-claim-authority-and-current-source.md`, `develop/orchestration/tasks/TEMPLATE.md`, `develop/orchestration/reports/TEMPLATE.md`, `AGENTS.md`.
- Public current-claim corrections: the permitted `docs/guide/*.md` files changed in this task, including landing, authentication, Community Board, CLI/generator, database, deployment, journal/observability, runtime, scheduled operation, security/tenant protection, testing, status, and MVP sample pages.
- Website source and guards: `docs/website/content-map.mjs`, `docs/website/package.json`, `docs/website/scripts/check-content.mjs`, `docs/website/scripts/check-artifact.mjs`, `docs/website/scripts/release-claim-checker.mjs`.
- Website tests and recurrence guards: `docs/website/tests/guide-code.test.mjs`, `docs/website/tests/reader-experience.test.mjs`, `docs/website/tests/release-claim-checker.test.mjs`, `tests/Consumer/version-baseline.sh`.
- Workflow wiring: `.github/workflows/ci.yml`, `.github/workflows/docs.yml`.

No Production PHP, generated `dist/` source, Tag, Release, Packagist, Commit, Push, Deploy, or public external state was changed. The pre-existing untracked `P23-001` proposal is separate scope and is excluded from the P22-005A candidate.

## Re-review Counterexamples and Corrections

- Deleting `pageCapabilities` or inserting an unknown/stable-main-only surface now fails Authority validation. Each mapped path must be a `docs/guide` Markdown path, each Capability ID must exist, each Capability must support the mapped lane, and the full Source checker requires the mapped page to state the Authority current version while rejecting `Stableにはない`／`Stable未提供`／main-only／Repository-main Preview claims. Positive and negative temporary Source/content-map/Authority fixtures exercise this path.
- Roadmap version/state is validated as a later unreleased semver. Source and Artifact checks reject `Latest Experimental Stable 1.3.0として公開済みです。`; the Japanese `未公開の1.2.0` candidate form is also rejected. Current candidate detection is derived from the Authority version, with a full Authority-bump fixture for `1.3.0`.
- Artifact history matching now requires normalized exact equality after HTML structure extraction. Prefix/suffix fixtures fail. HTML extraction includes visible text, table rows, all relevant quoted attributes (including tags carrying both `name`/`property` and `content`), and JSON-LD payloads; metadata-only stale fixtures fail.
- Capability `since` values reflect verified tag history: Framework Core and Skeleton Install `1.0.0`, Documentation Website `1.1.0`, remaining listed capabilities `1.2.0`. Validator permits semver history at or before current and rejects future `since` values.

## Decisions and Assumptions

- `develop/spec/release-authority.json` is the current release authority: Experimental Stable `1.2.0`, Framework direct/peeled refs `00e8c5875047a3c47acbebfe57f75b0e581d18b9` / `3332fd1dd0738fc7e79750facd93d49a59054ecf`, and Skeleton direct/peeled refs `fedcfda5f39caf320ad67196e8ced459176cedb1` / `fa5e8247fc8cf789cf73685e5be59cc498ffb4ce`.
- Framework Package, Skeleton install, Authentication/Authorization, Frontend Bridge, Seeder, Scheduled Operation, Protected Storage, Community Board Repository Example, and Documentation-only Surface are distinct Capability IDs.
- Only the six exact historical sentences retained in the authority are allowed to mention Stable `1.1.0`; the old `1.1.0` CLI/current-release claims were corrected rather than allowlisted. The `1.3.0` roadmap remains explicitly unreleased.
- Source checking scans every Guide Markdown file and `content-map.mjs`. Artifact checking scans generated HTML/metadata, raw Markdown, Search JSON records, and LLM artifacts without concatenating independent JSON records.
- CI and Documentation workflows run the Source guard before Website build and the Artifact guard after build.

## Version Occurrence Before / After Classification

- Task baseline classification: 17 current-facing stale `1.1.0` claims and 6 historical candidates required explicit separation; they were not bulk-replaced without classification.
- Final source audit: exactly 6 literal `1.1.0` occurrences remain in 3 source files, all matching the six authority entries. No current-facing candidate/unpublished/main-only claim remains in Guide or Content Map source.
- The checker rejects downgrade, candidate, main-only capability, moved/unexpected/unused history, artifact-only stale injection, and Authority-only version-bump fixtures.
- The corrected Search fixture proves that a valid current sentence in a later JSON record does not make an earlier record appear to be one stale sentence; a real stale record still fails.

## Source / Search / LLM Artifact Evidence

- `release:check:source` passed against all public Guide source and `content-map.mjs`.
- `release:check:artifact` passed against the generated artifact tree, including HTML, metadata, `blume-search.json`, raw Markdown, `llms.txt`, and `llms-full.txt`.
- The Website build completed and its integrated `check-artifact.mjs` and `site:check` both passed. The build emitted 42 static pages; site navigation/accessibility/search checks passed for 41 mapped pages.
- `release-claim-checker.test.mjs` includes positive and negative minified Search record fixtures, full mapped-page lane fixtures, Roadmap/source-artifact fixtures, metadata-only fixtures, exact prefix/suffix/current-context fixtures across Search JSON and LLM-like HTML/text, and an Authority-bump candidate fixture.

## Commands and Results

| Command | Result |
| --- | --- |
| `mise exec -- pnpm --dir docs/website run release:check:source` | PASS — source guard |
| `mise exec -- pnpm --dir docs/website run release:check:artifact` | PASS — artifact guard after record-boundary correction |
| `mise exec -- pnpm --dir docs/website run test` | PASS — 92/92 after exact-history correction |
| `mise exec -- pnpm --dir docs/website run check` | PASS — content, diagrams, Blume validation/type check |
| `mise exec -- pnpm --dir docs/website run build` | PASS — 42-page build, artifact guard, site check |
| `bash -n tests/Consumer/version-baseline.sh` | PASS |
| `bash tests/Consumer/version-baseline.sh` | PASS — `published=1.2.0 historical=1.1.0` |
| `docker compose run --rm app mago format --check src tests` | PASS — all files already formatted |
| `! rg -n 'Spec(ification)?[[:space:]]*[0-9]+\|D[0-9]{3}\|P[0-9]+-[0-9]+\|TODO\\.md:[0-9]+' src tests --glob '*.php'` | PASS — no forbidden management references |
| `git diff --check` | PASS |

The Website build emitted only existing non-fatal chunk-size and route-conflict warnings; no guard or build failure remained.

## Acceptance Criteria

- [x] Release Authority, immutable refs, Capability Surface, and exact historical allowlist are present.
- [x] Current-facing stale Stable `1.1.0`/unpublished-candidate claims are removed from Source.
- [x] Source and Artifact guards share one dependency-free classifier and reject all required negative fixtures, including mapped-page, Roadmap, metadata, exact-history, and future-authority cases.
- [x] Search, raw Markdown, HTML/metadata, and LLM artifacts are covered; minified Search records are record-bounded.
- [x] CI and Documentation workflows wire Source-before-build and Artifact-after-build guards.
- [x] Required Website, baseline, syntax, Mago, management-ID, and diff checks pass.
- [x] Orchestrator independent review and final Documentation Review returned P1=0／P2=0／P3=0 and accepted P22-005A.

## Remaining Issues

- This worker has not committed or mutated any external state. Exact commit/PR flow, same-SHA CI/Documentation delivery, and later parent P22-005 child tasks remain under Orchestrator control.
- D143 and Spec104 are explicit allowed P22-005A candidate management files. The separate untracked `develop/orchestration/tasks/P23-001-blackops-1-3-cli-and-frankenphp-feasibility.md` is excluded from the P22-005A candidate.
- Website production canonical verification is intentionally out of scope for this worker and remains a parent P22-005D concern.

## Suggested Next Action

Proceed to P22-005B for the approved Information Architecture, Landing, Sidebar, and Content Map restructure. Keep P22-005C–D, an exact reviewed commit/PR, same-SHA CI/Documentation delivery, and Website production verification as remaining parent work.

## Release Documentation Impact

- Release tuple: Experimental Stable `1.2.0`; Framework and Skeleton immutable direct/peeled refs are recorded in `develop/spec/release-authority.json`.
- Capability IDs and public Guide/route inventory are authority-backed; historical `1.1.0` sentences are exact allowlist entries rather than current claims.
- Source, Search, HTML/metadata, raw Markdown, and LLM artifact checks plus positive/negative fixtures are required before merge.
- No production deploy or public release mutation occurred; same-SHA CI/Documentation delivery remains the next reviewed operation.
