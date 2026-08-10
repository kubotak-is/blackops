# Specification 103: Stable 1.2 Release Plan

Status: Decided (P22-001 baseline)

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

README, Guides, Internal documentation, CHANGELOG Unreleased, and UPGRADE Preview explicitly identify the `1.2.0` candidate without calling it Latest Stable or published. The Stable onboarding remains executable from the existing `1.1.0` Tag.

## Release gate boundary

P22-001 establishes the version baseline and focused inventory evidence only. Complete `1.2.0` Release Notes／Migration, quality and Consumer full gate, annotated Tag, Skeleton split publication, Packagist, GitHub Release, and deployment are subsequent work.

## Traceability

- [D139 Stable 1.2 Version Baseline](../decisions/139-stable-1-2-version-baseline.md)
- [Experimental Release Contract](61-experimental-release-contract.md)
- [Composer Skeleton Publication](46-composer-skeleton-publication.md)
