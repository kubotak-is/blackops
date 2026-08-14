# D139 Stable 1.2 Version Baseline

Status: Decided

## Decision

公開済みLatest StableのFramework／Skeleton `1.1.0`はImmutableな履歴として維持する。既存Tag、GitHub Release、Packagist metadata、Stable install command、歴史的Decision／Task／Reportは書き換えない。

Repository `main`の次期Release Candidateは`1.2.0`とする。BlackOps 1.xはExperimentalでMinor間のBackward Compatibilityを保証しないため、Phase 12〜21のBreaking Surfaceをpatch `1.1.1`として表現しない。Main development root、Framework-owned OpenTelemetry instrumentation scope、Skeleton Source of Truth、Current-source Consumer fixtureを`1.2.0`系列へ同期する。

`1.2.0`は未公開であり、Latest StableまたはProduction Readyとして表示しない。Tag、Push、GitHub Release、Packagist反映、Skeleton split publication、完全なRelease Note／Upgrade手順は後続Release Gateの責務とする。

## Version classification

| Classification | Contract | Action |
| --- | --- | --- |
| stable-history | Published `1.1.0` install journey、Release、Tag、Packagist claim、historical record | Preserve exactly |
| current-candidate | Repository `main` root／Telemetry／Skeleton Source／candidate Consumer／active Preview docs | Synchronize to `1.2.0` / `^1.2` |
| third-party | Keep a Changelog URL, dependency versions, HTTP protocol `1.1`, and unrelated package fixtures | Do not rewrite |

## Guard

Version inventory must fail when an active current-source surface still advertises `1.1.0`, or when any `1.2.0` surface claims publication or Latest Stable. Every remaining `1.1.0` reference is recorded as stable-history or third-party evidence in the P22-001 Report.

## Traceability

- [Specification 103: Stable 1.2 Release Plan](../spec/103-stable-1-2-release-plan.md)
- [Experimental Release Contract](../spec/61-experimental-release-contract.md)
- [Stable 1.1 Release Contract](094-stable-1-1-release-contract.md)
