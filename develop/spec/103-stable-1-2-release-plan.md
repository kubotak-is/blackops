# Specification 103: Stable 1.2 Release Plan

Status: Decided (Framework `1.2.0` published at fixed source `3332fd1`; P22-004C reviewed Commit pending)

## Release lanes

Framework `1.2.0` is published at the accepted fixed source while Skeleton `1.2.0` recovery is in progress; the complete Composer project lane is not accepted until both packages and remote smoke pass. Existing `1.1.0` remains immutable and installable throughout recovery.

| Surface | Existing immutable lane | 1.2 publication／recovery lane |
| --- | --- | --- |
| Framework／Skeleton version | `1.1.0`／`1.1.0` | Framework `1.2.0` published／Skeleton `1.2.0` pending recovery |
| Skeleton Framework constraint | `^1.1` | `^1.2` |
| OpenTelemetry scope version | Stable contract | Framework `1.2.0` |
| Install / create-project | `composer create-project blackops/skeleton my-app 1.1.0` | Framework Composer install available／Skeleton create-project pending recovery |
| Publication | Existing immutable Tag／Release／Packagist | Framework Tag／Packagist present; Skeleton Tag／Packagist and GitHub Release pending |

## Active source contract

Docker Composer root version, Framework-owned Trace／Metric scope, `examples/quickstart` Composer metadata, candidate Consumer path repository mappings, and active main Preview documentation use `1.2.0` or `^1.2`. Skeleton publication validation derives `^1.2` from a candidate `1.2.0` input.

Stable install commands and historical release evidence continue to use `1.1.0`. Unrelated third-party versions and protocol literals are not release metadata and are not changed.

## Documentation contract

README, Guides, Internal documentation, CHANGELOG Unreleased, and UPGRADE Preview explicitly identify the `1.2.0` candidate without calling it Latest Stable or published. The Releases guide links the canonical root CHANGELOG/UPGRADE and actual-tag Consumer. The Stable onboarding remains executable from the existing `1.1.0` Tag.

## Release gate boundary

P22-001 establishes the version baseline and P22-002 completes the Release Notes／Migration documentation and actual-tag Consumer evidence. Complete `1.2.0` quality/full gate, annotated Tag, Skeleton split publication, Packagist, GitHub Release, and deployment are subsequent work.

Delivery is split into explicit checkpoints. P22-002 audits `1.1.0...main`, completes CHANGELOG／UPGRADE and the actual Stable-to-candidate Framework Update journey. P22-003 fixes a Release Candidate SHA and executes the full local／CI gate. P22-004 may perform Tag／Push／Skeleton／Packagist／GitHub Release／Remote Smoke only after separate authorization; preceding Tasks do not mutate external publication state.

P22-003 first commits the Stable-to-candidate Runtime Consumer and its CI wiring, then fixes that committed SHA and restarts the complete gate. The Runtime Consumer executes one shared Database migration／setup and DDL guard before the Provider-present Worker-mode HTTP／Worker positive lane and Provider-missing Classic HTTP safe-500／Worker CLI non-zero safe-negative lane. A local CI-equivalent run does not replace GitHub Actions evidence for the fixed SHA; if publishing the commit is required to obtain that evidence, P22-003 remains unaccepted until separately authorized Branch Push and successful CI. Worker-mode boot failure exits before the FrankenPHP request loop, so the missing-provider HTTP lane intentionally uses `http-classic`／`classic-mode`, whose per-request runtime emits the generic 500 JSON.

The Runtime Consumer's database evidence is sequential: actual Stable `1.1.0` install/migration runs once and read-only catalog checks must find exactly two Stable Framework rows in current-schema `blackops.schema_migrations` plus the six baseline tables and baseline constraints. Stable `database:status` may misreport `applied: 0`／`pending: 2` for this role/schema shape; the Consumer never reruns Stable migration. After a Framework-only update, Candidate `database:status` must recognize `applied: 2`／`pending: 9`, then finish at eleven applied migrations before either runtime lane. The Consumer applies only the Manual Merge Matrix's three candidate runtime bootstrap files (`bootstrap/app.php`, `public/index.php`, `public/worker.php`) and rechecks byte equality before build and after both runtime lanes; it does not copy Caddyfile, Compose, or other Application-owned Source. Metadata rows are never edited. Its disposable `.env` is removed before Compose shutdown, and the CI job requires full tag history plus mounted container UID/GID configuration.

Fixed candidate `08ad61f8236b3a240c9c9547fbde3b9d765fc6d5` completed all 23 local Consumer scripts, Frontend, Website, package export, Skeleton publication dry run, create-project lanes, and repository guards. P22-003 remains unaccepted because broad Mago lint still reports the existing 186 issues／14 errors, Deptrac still stops at 0/857 on its PHP 8.5 vendor parser, and the candidate is not in remote `main`, so same-SHA GitHub Actions evidence is unavailable. A source correction supersedes this candidate and restarts the full gate; a local baseline observation or CI-equivalent run is not a waiver.

D140／P22-003A resolves the two local tooling blockers without Production PHP changes: Mago 1.42.0 generator output becomes a tracked strict baseline verified for synchronization in CI, while Deptrac moves by exact minimal update from 4.6.2 to the official PHP 8.5-compatible 4.7.1. The reviewed tooling commit supersedes `08ad61f`; its SHA becomes the replacement candidate only after commit, and the complete gate restarts from that SHA.

P22-003B closes the resulting architecture and package boundaries: explicit public/library layers, bounded Internal Telemetry／Storage Protection／Deferred Integrity collectors, the Core `ConfigurationFailure` marker, and synchronized Git／Composer exclusion of `mago-lint-baseline.toml`. No generic `Transport -> Internal` permission or Deptrac waiver is allowed.

P22-003Aのhistorical review evidence records that Mago normal／verify lint succeeds, while Deptrac 4.7.1 reaches all 857 files but reports 152 violations／59 uncovered under the unchanged Ruleset, and Framework package export lacks the root baseline archive-exclusion contract. P22-003BのD141／D142 correctionではDeptrac 0 violations／0 uncovered、bounded facade/internal SCC guard、root/exclusion contractを確認した。Documentation Review P1=0／P2=0／P3=0後に`577cc224e0628ccbb9d91027ca214a4625a5228a`へCommitし、直後のGit／Composer全regular-file inventory完全一致とPublic API必須fileを含むexact package exportもPASSした。P22-003BはAcceptedであり、このSHAをreplacement candidateとしてP22-003 Full Gateを再実行し、complete local gateがPASSした。

Replacement candidate `577cc224e0628ccbb9d91027ca214a4625a5228a` completed the entire local P22-003 gate: strict-baseline Mago lint, Full PHPUnit `2317／9444`, Deptrac `858/858` with zero violation／uncovered, all 23 Consumers, Frontend, Website, package export, create-project, deterministic Skeleton split, and repository guards. The first Scheduled Operation concurrency run exited 255 after creating both evaluator containers; cleanup completed and an immediate unchanged-source rerun passed, so it is retained as diagnostic evidence rather than a Source correction. Direct `main` Push was rejected without mutation by the pull-request Repository Rule; exact candidate `577cc224` was pushed to a candidate branch and Draft PR #3. Same-SHA CI and Documentation delivery then failed because the Runtime disposable Git lacked tagger identity, Quality used a shallow checkout without Stable tag `1.1.0`, and the guide syntax test duplicated an existing PHP opening tag. A bounded correction changes Candidate Source and therefore requires a new reviewed Commit plus complete Local／Remote gate restart. After successful same-SHA Remote CI, final Documentation Review must pass before Orchestrator acceptance; publication remains prohibited until that sequence completes.

P22-003C closed those three CI environment contracts without Production PHP／Public API／Guide本文 changes. Documentation Reviewer returned P1=0／P2=0／P3=0, and the correction was committed as replacement candidate `96383e1bbe1a0914d1eddc9e1dea160042804f7c`. The complete Local Gate passed at this exact SHA without reusing `577cc224` evidence: strict quality, all 23 Consumers, Frontend, Website, package／create-project／deterministic Skeleton split, and repository guards are green. Draft PR #3 must next be updated to this exact SHA and pass same-SHA Remote CI before final Documentation Review and Orchestrator acceptance.

Draft PR #3 was updated to exact `96383e1`. Documentation delivery and every CI job except Quality passed. Quality stopped in `version-baseline.sh` because `tests/Consumer/framework-update-runtime.sh` is committed with Git mode `100644` even though WSL2 exposed local mode `0755` under `core.filemode=false`. The executable guard remains required. P22-003D was explicitly approved and stages only Git mode `100644` to `100755`; the script blob/content is unchanged and focused Orchestrator review passes. Documentation Review must approve the correction before Commit. That Commit becomes a new candidate and restarts the complete Local／same-SHA Remote gate.

P22-003D Documentation Review returned P1=0／P2=0／P3=0, and the mode-only correction was committed as replacement candidate `3332fd1dd0738fc7e79750facd93d49a59054ecf`. The Runtime Consumer is recorded as `100755`; its blob remains exact `8b82505b2da9b14014a20836a42137d33e6042fd` from `96383e1`. The complete Local Gate passed at exact `3332fd1` without reusing `96383e1` evidence: strict quality, all 23 Consumers, Frontend, Website, package／create-project／deterministic Skeleton split, and repository guards are green. Independent checkpoint Documentation Review also returned P1=0／P2=0／P3=0 and permits only a User-authorized exact `3332fd1` candidate branch update plus new same-SHA CI monitoring; merge, Acceptance, and publication remain prohibited.

User authorized exact `3332fd1` CI qualification and Green-gated `1.2.0` publication. Draft PR #3 was updated by explicit SHA refspec; same-SHA CI run `31771509163` and Documentation delivery run `31771509167` succeeded. Corrected final Documentation Review must confirm the synchronized evidence before PR ready／merge. Merge commit must preserve `3332fd1` as an ancestor; after fetch confirms the candidate in remote `main` history, P22-003 may be accepted and P22-004 initialized. Publication remains prohibited before that sequence completes.

Corrected final Documentation Review returned P1=0／P2=0／P3=0. PR #3 was marked ready and merged with merge commit `547149109419b62ab769af9d3aad1ed80dbba905`, whose second parent is exact candidate `3332fd1`; post-fetch ancestry and tree equality passed. P22-003 is Accepted. P22-004 must keep release source `3332fd1` and deterministic Skeleton split `fa5e8247fc8cf789cf73685e5be59cc498ffb4ce` fixed while executing the User-authorized publication sequence.

P22-004 integrated its reviewed tracking checkpoint through PR #4 and published Framework annotated tag `1.2.0`, whose live peeled commit is exact `3332fd1`; Packagist Framework `1.2.0` is visible. Tag-triggered Skeleton run `31809007808` passed Framework quality and failed before credential configuration／distribution push because the Workflow did not install `mise`, required by the Quickstart Consumer. P22-004A／B corrections subsequently passed all required CI and merged through PR #5 as `f61dc037533f3dea54ba33df9e203c7727d06443`. Manual Recovery run `31827240918` then passed immutable tag checkout, pinned toolchain, dependencies, and Framework quality but failed before credential／publication steps because `retention:plan | grep -q` closed Docker Compose stdout early under `pipefail`. P22-004C drains complete database／retention outputs before assertions and, for Manual Recovery only, overlays the reviewed dispatch-SHA Quickstart harness against immutable tag Source after fail-closed equality across `src`／root `composer.json`／`examples/quickstart`／`resources`／`migrations`; it restores the tagged harness before later gates. Worker and Orchestrator full Quickstart evidence passes without broken pipe, and corrected Documentation Review returned P1=0／P2=0／P3=0. Reviewed Commit, new all-Green CI, main merge／fetch, and a new one-shot Manual Dispatch remain required. Skeleton tag／Packagist version and GitHub Release remain absent.

## Traceability

- [D139 Stable 1.2 Version Baseline](../decisions/139-stable-1-2-version-baseline.md)
- [D140 Release Quality Tooling Baseline](../decisions/140-release-quality-tooling-baseline.md)
- [Experimental Release Contract](61-experimental-release-contract.md)
- [Composer Skeleton Publication](46-composer-skeleton-publication.md)
