# P21-007: Ray Removal, Package Export, and Phase 21 Closeout

Status: Planned

## Goal

After P21-006 acceptance, remove Ray.Aop and `ext-tokenizer` only through the complete Specification 101 removal gate, then prove clean package export and close Phase 21.

## Source of Truth

- `AGENTS.md`
- `develop/STATE.md`
- `develop/spec/101-framework-owned-transaction-proxy.md`
- `develop/spec/102-phase-21-delivery-plan.md`
- `develop/decisions/137-framework-owned-transaction-proxy.md`
- Accepted P21-002–P21-006 Reports
- Composer/package export contract and all historical Ray references

## Dependencies

P21-006 accepted with synchronized Report/STATE and all removal gates green.

## In Scope

- Remove Ray source adapters/interceptors, Ray fixtures, Composer dependency and lock entries
- Remove `ext-tokenizer` requirement when no other package requires it
- Remove compatibility profile only after the final framework profile is selected
- Namespace/artifact/source scan, clean install, package export, focused/full regression
- Update internal specification/roadmap/TODO/STATE/report references for closeout

## Out of Scope

- New proxy behavior or generator changes
- Runtime Source Scan, fallback compatibility, external Issue/PR, public deployment
- Removing historical D096/D108/P17 evidence; historical decisions remain traceable

## Files Allowed

- `src/Internal/Aop/**` Ray adapters only, as identified by the accepted removal manifest
- `tests/Internal/Aop/**` Ray fixtures/tests only, as identified by the manifest
- `src/Internal/Console/ApplicationBuildCompileCommand.php`
- `src/Internal/Console/FrameworkProxyProfileOption.php`
- `src/Internal/Runtime/FrameworkProxyProfileLoader.php`
- `src/Internal/Build/FrameworkProxyProfile.php`
- `tests/Internal/Console/ApplicationBuildCompileCommandTest.php`
- `tests/Internal/Console/FrameworkProxyProfileOptionTest.php`
- `tests/Internal/Runtime/FrameworkProxyProfileLoaderTest.php`
- `tests/Internal/Build/FrameworkProxyProfileTest.php`
- `src/Internal/DependencyInjection/RuntimeContainerDumper.php`
- `tests/Internal/DependencyInjection/RuntimeContainerDumperTest.php`
- `tests/Internal/Application/ApplicationConsoleKernelTest.php`
- `tests/Consumer/framework-package-export.sh`
- `tests/Consumer/framework-proxy-removal-clean-install.sh`
- `docs/guide/project-cli.md`
- `docs/internal/framework-proxy-compatibility.md`
- `develop/orchestration/reports/P21-006-ray-removal-manifest.md` (must be accepted before P21-007 starts)
- `composer.json`
- `composer.lock`
- `develop/spec/README.md`
- `develop/spec/60-post-phase-10-roadmap.md`
- `develop/TODO.md`
- `develop/STATE.md`
- `develop/orchestration/reports/P21-007-framework-proxy-ray-removal-closeout.md`

No file may be deleted or edited unless the exact P21-006 removal manifest names the action and the manifest is reviewed at P21-007 start. P21-007 MUST amend this Files Allowed section at start if the accepted manifest identifies an additional exact compatibility-profile target. Historical Decision/Report text must remain.

## Constraints

- Do not remove Ray before every Specification 101 gate passes.
- Package export must prove the working-tree and committed archive contract separately.
- Never run `composer install` in the main worktree. Clean-install verification MUST be performed only by the isolated `tests/Consumer/framework-proxy-removal-clean-install.sh` script (or an equivalent explicitly reviewed temporary consumer directory).
- No secret, generated source, or full vendor dump in reports.
- Worker does not commit, push, or deploy before Orchestrator review.

## Acceptance Criteria

- [ ] Full signature/DI/artifact/lifecycle/compatibility/removal gates pass.
- [ ] No Ray namespace, Composer dependency, `WeavedInterface`, Ray fixture, or legacy artifact remains outside historical Decision/Report references.
- [ ] Composer strict audit, isolated Consumer clean install, package export, focused and full PHPUnit pass.
- [ ] Framework profile is the sole selected profile; Runtime has no fallback or Source Scan.
- [ ] TODO/STATE/Decision index/Report mark Phase 21 closeout Review Pending; Orchestrator performs final Acceptance.

## Required Commands

```bash
docker compose run --rm app php vendor/bin/phpunit
composer validate --strict
test -f develop/orchestration/reports/P21-006-ray-removal-manifest.md
bash tests/Consumer/framework-package-export.sh
bash tests/Consumer/framework-proxy-removal-clean-install.sh
! rg -n 'Ray\\\\Aop|Ray\.Aop|ray/aop|WeavedInterface|FrameworkProxyProfile.*ray' src tests composer.json composer.lock --glob '*.php' --glob '*.json' --glob '*.lock'
docker compose run --rm app mago format --check src tests
! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
git diff --check
```

## Expected Report

`develop/orchestration/reports/P21-007-framework-proxy-ray-removal-closeout.md`
