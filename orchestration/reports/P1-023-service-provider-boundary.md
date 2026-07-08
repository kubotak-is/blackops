# P1-023 Report: Service Provider Boundary

## Summary

Implemented and accepted the public service provider boundary.

Applications and packages can now implement `ServiceProvider` and register services through a framework-owned `ServiceRegistry`. The Symfony-specific registry adapter remains internal.

## Changed Files

- `src/Core/DependencyInjection/ServiceProvider.php`
- `src/Core/DependencyInjection/ServiceRegistry.php`
- `src/Internal/DependencyInjection/SymfonyServiceRegistry.php`
- `src/Internal/DependencyInjection/RuntimeContainerCompiler.php`
- `tests/Core/DependencyInjection/ServiceProviderTest.php`
- `tests/Internal/DependencyInjection/ServiceProviderBoundaryTest.php`
- `docs/internals/runtime-container.md`
- `orchestration/tasks/P1-023-service-provider-boundary.md`
- `orchestration/reports/P1-023-service-provider-boundary.md`
- `orchestration/STATE.md`

## Decisions and Assumptions

- Public contracts expose BlackOps-owned types only; Symfony `ContainerBuilder` remains internal.
- The first registry API supports class autowiring and object services only.
- Runtime container compiler applies providers before compile.
- Config loading, automatic provider discovery, aliases, tags, factories, scalar bindings, and parameters remain out of scope.

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter 'ServiceProviderTest|ServiceProviderBoundaryTest'
Result: OK (3 tests, 6 assertions).
```

```text
docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.
```

```text
docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.
```

```text
docker compose run --rm app mago lint
Result: INFO No issues found.
```

```text
docker compose run --rm app mago analyze
Result: INFO No issues found.
```

```text
docker compose run --rm app vendor/bin/phpunit
Result: OK (207 tests, 503 assertions).
```

```text
docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 288 / Warnings 0 / Errors 0.
```

```text
rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.
```

## Acceptance Criteria

- [x] Public `ServiceProvider` Contract can be implemented.
- [x] Public `ServiceRegistry` can register class autowiring services.
- [x] Public `ServiceRegistry` can register object services.
- [x] Runtime Container Compiler can apply service providers.
- [x] Handlers registered through a service provider can be resolved from a compiled container.
- [x] Public contracts do not expose Internal or Symfony types.
- [x] Required quality commands, including formatter check, are complete.
- [x] PHP comments and docblocks do not contain management numbers.

## Remaining Issues

- None.

## Suggested Next Action

Proceed to Config Loader, automatic provider discovery, or richer service registration DSL depending on the next runtime integration need.
