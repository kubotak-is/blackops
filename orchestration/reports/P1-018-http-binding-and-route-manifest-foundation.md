# P1-018 Report: HTTP Binding and Route Manifest Foundation

## Summary

Implementation is complete and accepted.

Added HTTP binding attributes, attribute-aware OperationValue binding, minimal dynamic path matching, and an in-memory HTTP operation manifest. Existing HTTP request handling now passes path parameters from route matching into the binder.

## Changed Files

- `src/Http/Attribute/FromPath.php`
- `src/Http/Attribute/FromQuery.php`
- `src/Http/Attribute/FromHeader.php`
- `src/Http/Attribute/FromBody.php`
- `src/Http/Binding/BoundHttpValue.php`
- `src/Http/Binding/HttpParameterBinder.php`
- `src/Http/Binding/JsonRequestBody.php`
- `src/Http/Binding/OperationValueBinder.php`
- `src/Http/OperationRequestHandler.php`
- `src/Http/Routing/HttpOperationManifest.php`
- `src/Http/Routing/HttpPathPattern.php`
- `src/Http/Routing/HttpRouteCompiler.php`
- `src/Http/Routing/HttpRouteMatch.php`
- `src/Http/Routing/HttpRouteRegistry.php`
- `tests/Http/OperationRequestHandlerTest.php`
- `docs/internals/http-api-slice.md`
- `orchestration/tasks/P1-018-http-binding-and-route-manifest-foundation.md`
- `orchestration/STATE.md`

## Decisions and Assumptions

- Binding attributes are public API attributes and accept an optional source name override.
- Attribute resolution order is path, query, header, body, then implicit same-name JSON body binding.
- JSON body binding only accepts a JSON object. Scalar or list JSON bodies are rejected.
- Bound scalar and null values are allowed. Nested arrays or objects are rejected for this first binding slice.
- Dynamic path support is intentionally minimal: a full path segment in `{name}` form becomes one named parameter and is decoded before binding.
- Route manifests are in-memory structures only. File generation and manifest loading remain out of scope.
- `HttpRouteCompiler::compile()` materializes iterable definitions before compiling so generator inputs are not consumed twice.
- Dynamic path regex compilation is isolated in `HttpPathPattern` so `HttpRouteRegistry` remains focused on route registration and lookup.

## Commands and Results

```text
docker compose run --rm app vendor/bin/phpunit --filter OperationRequestHandlerTest
Result: OK (9 tests, 26 assertions).
```

```text
docker compose run --rm app mago format src tests
Result: Success. Formatter applied before the binder complexity refactor.
```

```text
docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted.
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
docker compose run --rm app composer validate --strict
Result: ./composer.json is valid.
```

```text
docker compose run --rm app vendor/bin/phpunit
Result: OK (194 tests, 477 assertions).
```

```text
docker compose run --rm app vendor/bin/deptrac
Result: Violations 0 / Skipped 0 / Uncovered 0 / Allowed 265 / Warnings 0 / Errors 0.
```

```text
rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No matches.
```

## Acceptance Criteria

- [x] `FromPath` can bind path parameters into an OperationValue.
- [x] `FromQuery` can bind query parameters into an OperationValue.
- [x] `FromHeader` can bind header values into an OperationValue.
- [x] `FromBody` can bind JSON body fields into an OperationValue.
- [x] Parameters without binding attributes bind from same-name JSON body fields.
- [x] Minimal `{name}` route matching passes path parameters to the binder.
- [x] Route compiler can produce a route manifest array.
- [x] Required quality commands, including formatter check, are complete.
- [x] PHP comments and docblocks do not contain management numbers.

## Remaining Issues

- None.

## Suggested Next Action

Proceed to manifest file output/loader or Runtime DI Container Compile.
