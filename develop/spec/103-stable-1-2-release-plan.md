# Specification 103: Stable 1.2 Release Plan

Status: Decided (P22-003 local gate executed; strict quality and Remote CI pending)

## Release lanes

The published Latest Stable Framework／Skeleton remains `1.1.0`. Repository `main` is an unpublished `1.2.0` Release Candidate. The two lanes must remain visibly and operationally separate.

| Surface | Latest Stable | Repository main candidate |
| --- | --- | --- |
| Framework／Skeleton version | `1.1.0` | `1.2.0` |
| Skeleton Framework constraint | `^1.1` | `^1.2` |
| OpenTelemetry scope version | Stable contract | `1.2.0` |
| Install / create-project | `composer create-project blackops/skeleton my-app 1.1.0` | Local Source / Consumer only |
| Publication | Existing immutable Tag／Release／Packagist | No Tag／Push／Release／Packagist |

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

## Traceability

- [D139 Stable 1.2 Version Baseline](../decisions/139-stable-1-2-version-baseline.md)
- [Experimental Release Contract](61-experimental-release-contract.md)
- [Composer Skeleton Publication](46-composer-skeleton-publication.md)
