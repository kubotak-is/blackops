# P21-007 Ray Removal, Package Export, and Phase 21 Closeout

Status: Review Pending

## Summary

Removed the manifest-named Legacy Ray AOP implementation, compatibility
selector, Ray fixtures/evidence, Composer dependency, and Ray artifact branch.
The Application-aware build now selects the Framework profile as the sole
profile. The immutable Framework Profile Unit, Signature/DI/Lifecycle contract,
no-fallback behavior, complete-release rollback, and global generated-prefix
guard remain active.

## Changed Files

- Ray source adapters/interceptors under `src/Internal/Aop/` named by the
  accepted P21-006 removal manifest
- `src/Internal/Aop/FrameworkProxyContract/FrameworkProxyProfile.php`
- `src/Internal/Aop/ProxyProfileArtifact/ProxyProfileArtifactPublisher.php`
- `src/Internal/Application/ApplicationConsoleKernel.php`
- `src/Internal/Console/ApplicationBuildCompileCommand.php`
- `src/Internal/Console/FrameworkProxyProfileOption.php` (removed)
- `src/Internal/DependencyInjection/FrameworkProxyDefinitionCompiler.php`
- `src/Internal/DependencyInjection/RuntimeContainerDumper.php`
- `src/Internal/Runtime/FrameworkProxyProfileLoader.php`
- `src/Internal/Runtime/ProxyProfileArtifactLoader.php`
- manifest-named Ray fixtures/tests and Framework-only test updates
- `tests/Consumer/framework-proxy-removal-clean-install.sh`
- `composer.json` and `composer.lock`
- `docs/guide/project-cli.md`, `docs/guide/configuration.md`,
  `docs/guide/mvp-status.md`, `docs/internal/framework-proxy-compatibility.md`
- `develop/spec/101-framework-owned-transaction-proxy.md`,
  `develop/spec/102-phase-21-delivery-plan.md`,
  `develop/spec/60-post-phase-10-roadmap.md`, `develop/TODO.md`
- `develop/STATE.md` and this Task Report

## Decisions and Assumptions

- P21-007 removes both explicitly recorded Legacy Ray exceptions (`never`
  compilation and named-variadic forwarding) together with Ray.
- The standalone legacy `blackops:build:compile` command remains unchanged;
  it does not invoke the Application-aware AOP path.
- `ext-tokenizer` remains in the lock only where unrelated packages require it;
  the Ray package and its own requirement are removed.
- No main-worktree Composer install was run. Clean install is isolated to the
  Consumer journey.

## Commands and Results

- Focused PHPUnit Framework-only removal set: PASS, 65 tests, 284 assertions.
- Expanded focused PHPUnit after immutable-unit security and Framework identity
  guards: PASS, 68 tests, 293 assertions.
- `docker compose run --rm app php vendor/bin/phpunit --no-progress`: PASS,
  2,315 tests, 9,432 assertions in the Orchestrator final rerun. One dependency deprecation, two PHPUnit
  deprecations, and thirteen PHPUnit notices remain non-blocking.
- `docker compose run --rm app composer update --no-install --no-scripts --minimal-changes`:
  PASS, 0 updates and 1 removal (`ray/aop`).
- `bash -n tests/Consumer/framework-proxy-removal-clean-install.sh`: PASS.
- `bash tests/Consumer/framework-proxy-removal-clean-install.sh`: PASS under
  approved Docker access. Git and Composer package export passed; isolated
  Composer installed 42 packages from the path repository with `symlink=false`,
  `vendor/ray/aop` and `composer show ray/aop` were absent, Framework autoload
  and profile checks passed, and the worktree remained unchanged by the
  journey. The first isolated attempt exposed an over-escaped PHP FQCN probe;
  the probe was corrected and this exact journey was rerun successfully.
- `docker compose run --rm app mago format --check src tests`: PASS.
- `docker compose run --rm app composer validate --strict`: PASS.
- `docker compose run --rm app composer why --locked ext-tokenizer`: PASS;
  `nikic/php-parser`, `staabm/side-effects-detector`, and `theseer/tokenizer`
  remain legitimate lock consumers, and Ray is absent. The installed main
  worktree vendor remains intentionally untouched and is not lock evidence.
- Namespace/Ray scans, management-ID guard, `git diff --check`, and Consumer
  shell syntax checks: PASS.
- Scoped Mago analyze: no blocking errors; existing mixed JSON/strict warnings
  only.
- Documentation Reviewer final re-review: P1 0, P2 0, P3 0; Acceptance
  permitted after Framework Unit main-only guidance, current Framework-only
  Specification, and Report inventory were synchronized.

## Acceptance Criteria

- [x] Full signature/DI/artifact/lifecycle/compatibility/removal gates pass.
- [x] No Ray namespace, Composer dependency, `WeavedInterface`, Ray fixture,
  or legacy artifact remains outside historical Decision/Report references.
- [x] Composer strict audit, isolated Consumer clean install, package export,
  focused and full PHPUnit pass.
- [x] Framework profile is the sole selected profile; Runtime has no fallback
  or Source Scan.
- [ ] TODO/STATE/Decision index/Report mark Phase 21 closeout Accepted.

## Remaining Issues

The reviewed working-tree gates pass. The remaining closeout step is to commit
the removal, rerun package export and Ray scans against Git HEAD, and record
that committed-archive evidence before final Acceptance. No worker commit,
push, or deploy was performed.

## Suggested Next Action

Commit the reviewed removal, prove the committed Git archive and Ray-absence
contract, then mark P21-007 and Phase 21 Accepted.
