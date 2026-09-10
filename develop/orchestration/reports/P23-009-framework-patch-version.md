# P23-009: Framework 1.2.1 Version Preparation

Status: Accepted — local version preparation; release publication not performed

## Summary

Prepared unpublished Framework 1.2.1 in the isolated 1.2 maintenance checkout
`/tmp/blackops-framework-1.2.1`, branch `agent/p23-009-framework-1-2-1`, based on
`5ad87557c08e20c3bcc6d77be83804001ba3eb80`. The two Telemetry scope versions,
Composer development environment and synthetic package fixtures agree on 1.2.1.
No new capability, API, dependency, resource or migration is included.

The complete 19-file delta is preserved in
[the reviewable patch](../artifacts/P23-009-framework-1.2.1.patch), SHA256
`e1f0eb353bcd27b8828829d1cfa1ac9e76619288db13a01209f507528626ba2f`.
It applies to the maintenance base, not the primary branch containing 1.3 work.
No commit, push, tag, release, Skeleton publication or deployment was performed.

## Changed Files

- Dockerfile; two src/Internal/Telemetry version constants and their two tests.
- Nine tests/Consumer shell fixtures: version-baseline, quickstart-e2e,
  auth-generator-fresh, scheduled-operation, storage-protection-rotation,
  frankenphp-worker-mode, framework-update-generators, framework-update-runtime,
  skeleton-create-project.
- CHANGELOG Unreleased, three docs/internal notes (mvp-e2e,
  production-observability, installed-application-status), and the bounded
  maintenance candidate clause in spec103.
- Primary workspace: this Task/Report, preserved patch, TODO and rotated STATE.
  P23-010/P23-011 independently own the primary documentation changes.

## Decisions and Assumptions

Live remote discovery confirmed published Framework 1.2.0, the maintenance base
and no 1.2.1 tag. Framework src, resources, migrations, Composer manifests,
Dockerfile and export rules at that base match the published source. Root chose
the isolated maintenance route after the optional scope question received no
selection; subsequent Audit questions did not cancel the patch request.

Composer package versions come from VCS tags; no manifest version field was added.
The synthetic create-project check uses Skeleton 1.2.0 plus Framework 1.2.1 under
its unchanged ^1.2 constraint. It does not change spec46's publication policy.
Current authority, public installs and historical releases remain 1.2.0/1.1.0 as
applicable. Third-party version occurrences were not rewritten.

## Commands and Results

Commands ran from the primary repository root. Evidence is under
`/tmp/blackops-p23-009/`; each named JSON records literal command, UTC time,
input hashes, exit and duration, with matching log. Final source binding is
`final-source.json`; all 19 hashes and preserved patch were rechecked at closeout.

Common container prefix, followed by the table's command:

```text
docker run --rm --user 1000:1000 --env COMPOSER_ROOT_VERSION=1.2.1@dev --volume /tmp/blackops-framework-1.2.1:/app --workdir /app blackops/framework:dev
```

Runtime: PHP 8.5.7, PHPUnit 13.2.2, Mago 1.42.0, Node 24.18.0. Existing image
reused with explicit candidate environment; a new Docker image was not built.
Dependency reuse first verified identical locked package arrays (41 production,
62 development); Composer install regenerated the isolated checkout's autoload.

| Command / owner | Result | Seconds | Receipt |
| --- | --- | ---: | --- |
| composer install --no-interaction --prefer-dist --no-progress / Root | PASS; no package change | 1.486 | install.json |
| composer validate --strict / Root | PASS | 0.810 | validate.json |
| vendor/bin/phpunit tests/Internal/Telemetry / Root | PASS; 13 tests, 96 assertions | 1.139 | telemetry.json |
| mago format --check src tests / Root | PASS | 1.350 | format.json |
| PHP version and COMPOSER_ROOT_VERSION / Root | 8.5.7 / 1.2.1@dev | 0.828 | environment.json |
| bash /tmp/blackops-framework-1.2.1/tests/Consumer/skeleton-create-project.sh / Root | PASS normal and no-scripts journeys; cleanup verified | 24.317 | create-project.json |
| node /tmp/blackops-framework-1.2.1/docs/website/scripts/release-claim-checker.mjs source / Root | PASS | 0.096 | release-source.json |
| node --test --test-isolation=none /tmp/blackops-framework-1.2.1/docs/website/tests/release-claim-checker.test.mjs / Root | PASS 9/9 | 0.100 | release-fixtures.json |

Telemetry reports existing one PHPUnit deprecation and seven notices. The test
result is passing, not a zero-warning claim. Luna's shell syntax, version-baseline
and diff checks passed; literal commands/results are in `final-receipt-v2.txt`.
The version-baseline check took 0.94s. Root's required management-ID rg scan of
maintenance src/tests PHP found no matches (0.024s).

Initial Docker socket and Git worktree writes hit sandbox permissions. Approved
identical retries succeeded; no source assertion was bypassed. The initial
install permission receipt is retained as `install-permission.json`.

## Review and Acceptance Criteria

All local Task criteria pass. Root verified only the two version constants change
Framework PHP, while Composer manifests, resources and migrations remain equal
to the base. Read-only Documentation Review closed both wording findings and
returned P1/P2/P3=0. Unchanged product inputs retain their earlier passing tests
after the final CHANGELOG/fixture-description corrections.

The primary accepted site work and unrelated D2S1 Report were preserved. This
Task did not rebuild or replace the primary preview; its later updates belong
to P23-010/P23-011.

## Release Documentation Impact

Published Experimental Stable 1.2.0 authority remains Framework direct
`00e8c5875047a3c47acbebfe57f75b0e581d18b9`, peeled
`3332fd1dd0738fc7e79750facd93d49a59054ecf`; Skeleton direct
`fedcfda5f39caf320ad67196e8ced459176cedb1`, peeled
`fa5e8247fc8cf789cf73685e5be59cc498ffb4ce`. Roadmap 1.3.0 stays unreleased.
No Capability ID is promoted. Only unpublished framework-core version metadata
changes. Public guide/site/README/UPGRADE and authority bytes are unchanged against
the maintenance base; Search/LLM artifacts therefore receive no new release claim.
Source and positive/negative fixtures pass. A candidate website build is not
needed for unchanged public inputs and is not claimed as a release artifact.

Full PHPUnit, full static analysis, all Consumers, same-SHA CI/export, release
publication and production delivery are Not Run: local version preparation does
not change algorithms or dependencies and does not qualify a published release.
Selected measured verification totals approximately 31.1s, excluding discovery,
agent/review waits and the permission retry. Full PHP suite executions: zero.
The final prose-only corrections required review, not repeat product tests.

## Remaining Issues / 残り工程

Local preparation: none. Actual publication would still require a fixed committed
candidate, the release qualification/same-SHA CI/export gates, authorized remote
publication and external version verification. The separate 1.3 work is unchanged.

## Suggested Next Action

Keep the reviewed maintenance candidate and preserved patch for the next explicitly
requested release operation; finish P23-010/P23-011's local documentation delivery.
