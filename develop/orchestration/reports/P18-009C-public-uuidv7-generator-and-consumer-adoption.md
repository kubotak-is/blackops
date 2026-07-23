# P18-009C Public UUIDv7 Generator and Consumer Adoption

## Summary

Added the public `BlackOps\\Identifier\\Uuidv7Generator` contract and Framework default UUIDv7 service. Every compiled container path now binds a default or explicit application override through a validation adapter that accepts only canonical lowercase RFC 4122 UUIDv7 values. Auth Generator and Community Board Identity／Board Infrastructure adapters now constructor-inject the public contract; Domain ports remain application-owned and vendor-independent.

## Changed Files

- `src/Identifier/Uuidv7Generator.php`
- `src/Internal/Identifier/DefaultUuidv7Generator.php`
- `src/Internal/Identifier/ValidatedUuidv7Generator.php`
- `src/Internal/DependencyInjection/RuntimeContainerCompiler.php`
- `src/Internal/Console/ApplicationBuildCompileCommand.php`
- `src/Internal/Console/CompileBuildArtifactsCommand.php`
- `src/Internal/Console/CompileRuntimeContainerCommand.php`
- `resources/stubs/auth-random-identity-identifier.php.stub`
- `examples/community-board/app/Infrastructure/Identity/RandomIdentityIdentifier.php`
- `examples/community-board/app/Infrastructure/Identifier/Uuidv7BoardIdGenerator.php`
- `examples/community-board/app/Infrastructure/Identifier/SymfonyBoardIdGenerator.php` (replaced)
- `examples/community-board/app/ApplicationServiceProvider.php`
- `tests/Identifier/Uuidv7GeneratorTest.php`
- `tests/Architecture/QuickstartApplicationArchitectureTest.php`
- `develop/spec/17-core-api.md`, `20-identifier-value-objects.md`, `71-full-stack-reference-application.md`, `78-application-runtime-and-bootstrap.md`, `79-phase-18-runtime-follow-up-delivery-plan.md`
- `develop/STATE.md`

## Final Public API, Binding, and Validation Boundary

`Uuidv7Generator` is the only new PHP Public API and exposes `generate(): string`. The default service uses Symfony UID internally and returns canonical lowercase UUIDv7 text. Service Provider `autowire()` and object `set()` overrides are moved behind a private source service, then exposed through `ValidatedUuidv7Generator`; invalid output fails with a fixed message before application data receives it. Default and override binding is installed by Application Build Compile, Compile Build Artifacts, and Compile Runtime Container paths.

## UUIDv7 Evidence

- Default shape and lowercase/version/variant validation: PASS.
- Default uniqueness across consecutive generation: PASS.
- Deterministic `autowire()` override: PASS.
- Deterministic object `set()` override: PASS.
- Invalid override fixed failure: PASS.
- Existing Internal `IdentifierFactory` and Clock-based typed ID tests remain green.

## Auth Generator and Community Board Evidence

- Auth Generator unit suite: PASS, 33 tests, 135 assertions.
- Generated auth identifier stub injects `Uuidv7Generator`; no random UUID algorithm remains in the stub.
- Community Board Identity adapter injects `Uuidv7Generator`; Board adapter `Uuidv7BoardIdGenerator` does the same.
- Architecture tests assert both adapters contain no Symfony UID import and use the public contract.
- Domain Identity／Board files remain free of BlackOps, Symfony, and Doctrine imports.

## Consumer Results

- `bash tests/Consumer/community-board-identity.sh`: PASS. Isolated Worker and Classic runtime; migrations, PHP 49/548, frontend check/Vitest 43/build, registration/current/logout/rotation/expiry/failure/sensitive guards.
- `bash tests/Consumer/community-board-post-comment.sh`: PASS. Isolated Board post/comment journey with migrations, PHP 49/548, build/frontend, and HTTP persistence flow.
- `bash tests/Consumer/auth-generator-fresh.sh`: PASS — Fresh/Force generation, build, frontend, HTTP auth journey, sensitive-surface checks, and final working-tree guard; output ended with `Auth generator fresh consumer journey passed.`
- `bash tests/Consumer/community-board-clean-install.sh`: PASS — output ended with `Community Board clean install journey passed.` Clean cleanup was followed by successful Composer install and frozen pnpm install restoration.
- Existing Community Board project: PostgreSQL remained healthy, migrations reported 0 pending, build:compile passed, HTTP/Worker restarted, and `GET http://127.0.0.1:8081/welcome` returned expected JSON. Existing database volume was not deleted or recreated; setup restored removed `.env`/`var/log` before recompilation.

## Commands and Results

- Full PHPUnit: PASS — 1,725 tests, 6,892 assertions.
- Focused UUID/API/architecture and container regressions: PASS (Application API + UUID binding 10 tests/44 assertions; architecture + UUID 17 tests/316 assertions; Auth/DI 33 tests/135 assertions).
- Mago format check: PASS.
- Mago lint/analyze: PASS (existing SAPI worker empty-loop note only).
- Deptrac: PASS — 0 violations, 2,860 allowed, 15 uncovered.
- Root Composer strict: PASS.
- Community Board Composer strict: PASS.
- Management-ID guard: PASS.
- `git diff --check`: PASS.

## Acceptance Criteria

- [x] Public Uuidv7Generator contract only, with canonical default binding.
- [x] Default and explicit `autowire`／object overrides validated safely.
- [x] Auth Generator and Community Board Identity／Board adapters use constructor injection.
- [x] Domain layer remains vendor/framework independent.
- [x] Existing Internal IdentifierFactory tests remain green.
- [x] Identity and Board Community consumers passed.
- [x] Clean-install terminal evidence and Auth fresh final status guard passed.

## Remaining Issues

No remaining issues within the Task Packet scope.

## Suggested Next Action

Commit the accepted P18-009C change set, then proceed to P18-009D Runtime Distribution, Dependency Audit, and Closeout.

## Orchestrator Verification

- Reviewed the sole new Public API, canonical validation, default and explicit override boundaries, all three compiled-container paths, and Application-owned Domain ports: accepted.
- Focused Public API／UUID／Architecture PHPUnit: PASS — 23 tests, 353 assertions.
- Auth Fresh／Force, Community Board Identity, Board post/comment, and Clean Install consumers: PASS.
- Existing Community Board Volume: migrations 0 pending, build compile PASS, PostgreSQL volume preserved, HTTP／Worker restarted, `/welcome` expected JSON PASS, and final HTTP health PASS.
- Worker Full PHPUnit, Mago, Deptrac, Composer strict, Management-ID guard, and `git diff --check`: PASS.
