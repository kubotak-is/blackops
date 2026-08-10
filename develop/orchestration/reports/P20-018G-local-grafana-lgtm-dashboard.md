# P20-018G Local Grafana LGTM Dashboard Report

Status: Accepted

## Summary

Implemented a Consumer-only Grafana LGTM journey for local Trace and Metric
inspection. The pinned `grafana/otel-lgtm:0.29.2` OCI index runs in a randomized
network with only random loopback bindings for Grafana `3000` and OTLP HTTP
`4318`. The existing BlackOps Emitter is reused through the `collector` network
alias. No Framework Production dependency, Default Compose service, Readiness
check, remote credential, or persistent backend was added.

## Changed Files

- `develop/decisions/138-local-grafana-lgtm-development-backend.md`
- `develop/spec/README.md`
- `develop/spec/60-post-phase-10-roadmap.md`
- `develop/spec/100-structured-logging-and-opentelemetry.md`
- `develop/TODO.md`
- `develop/orchestration/tasks/P20-018G-local-grafana-lgtm-dashboard.md`
- `develop/STATE.md`
- `tests/Consumer/opentelemetry-grafana-lgtm.sh`
- `tests/Consumer/fixtures/opentelemetry/emit.php`
- `tests/Consumer/fixtures/opentelemetry/lgtm-query.php`
- `docs/guide/observability.md`
- `docs/guide/troubleshooting.md`
- `docs/guide/mvp-status.md`
- `docs/guide/deployment.md`
- `docs/guide/security.md`

The documentation and specification boundaries are synchronized. Both isolated
Consumers and the full quality gates passed under Orchestrator execution. The
Documentation Reviewer re-review is pending after resolving its first-pass
P1/P2/P3 findings.

## Decisions and Assumptions

- Grafana's authenticated API is the safe probe boundary. The probe discovers
  provisioned Tempo and Prometheus datasource UIDs and uses Grafana's datasource
  proxy, so backend ports are never published.
- A random local admin password is passed only to the isolated probe process and
  is not printed or persisted.
- Only the LGTM Consumer passes `BLACKOPS_OTEL_METRIC_TEMPORALITY=cumulative` to
  the Emitter. The existing Collector lane remains on the Emitter default;
  Framework metric names, types, units, and schema remain unchanged.
- No-argument mode is bounded CI evidence and exits after cleanup. The
  `--interactive` mode uses a documented disposable local login, prints loopback URLs,
  the safe Trace ID, and the strictly allowlisted selected stored metric name, then waits
  for Ctrl-C/TERM so the user can browse Grafana. These are navigation values only, not
  credentials, backend responses, trace/metric payloads, or labels.
- Tempo trace retrieval uses the exact validated Trace ID in the request path and
  accepts only a 2xx JSON response containing non-empty trace spans and the
  known `blackops.operation.execute` span. Tempo may encode IDs as base64 in
  OTLP JSON, so the response need not repeat the request's hex spelling.
- Prometheus name discovery is bounded to 20 safe names, selects only the
  dotted/normalized `blackops.operation.duration` family, and then queries that
  exact selected name for a non-empty result.

## Image / Supply-chain Evidence

- Image: `grafana/otel-lgtm:0.29.2@sha256:af7242c1a9608faf6d26e6f235392fd0c32b67258228f9a3cfc96e724974930c`.
- The digest resolves the verified linux/amd64 and linux/arm64 OCI index from the
  official Grafana image registry. The Consumer does not use `latest`.

## Grafana / Tempo / Prometheus Probe Matrix

| Probe | Evidence | Result |
| --- | --- | --- |
| Grafana health | `/api/health`, authenticated API | PASS |
| Datasource provisioning | Grafana API contains `tempo` and `prometheus` datasource types | PASS |
| Exact Trace | Exact request path, non-empty Tempo spans, known `blackops.operation.execute` span, no sensitive sentinel | PASS |
| Stable Metric | Bounded name discovery, exact selected stable family query, non-empty Prometheus result, identity guard | PASS |

## Security / Port / Cleanup Evidence

- Randomized container, network, and temporary directory names are scoped to the
  invocation; the existing `blackops-otel-lgtm` resource is never addressed.
- Only random loopback mappings for `3000` and `4318` are allowed. Tempo and
  Prometheus backend ports remain internal.
- Source is mounted read-only. Cleanup is registered for normal, failure, and
  interrupt exits and removes only resources created by this script.
- Probe output excludes credentials, backend response dumps, Trace/Metric
  payloads, labels, and sensitive sentinel values. Success output contains only
  the loopback Grafana and OTLP random ports, the safe Trace ID, and the strictly
  allowlisted selected stored metric name for the Contributor's next step in
  interactive mode; the automated lane prints no URLs.

## Commands and Results

| Command | Result |
| --- | --- |
| `bash -n tests/Consumer/opentelemetry-grafana-lgtm.sh` | PASS |
| `bash tests/Consumer/opentelemetry-grafana-lgtm.sh` | PASS; bounded Grafana health, Tempo trace, Prometheus metric, safe ports, cleanup, and source status comparison |
| `bash tests/Consumer/opentelemetry-grafana-lgtm.sh --interactive` | PASS; TTY ready output, credential non-exposure, Ctrl-C exit 130, and Container/Network cleanup |
| `bash tests/Consumer/opentelemetry-observability.sh` | PASS; existing Collector and post-stop health isolation |
| `docker compose run --rm app composer validate --strict` | PASS |
| `docker compose run --rm app mago format --check src tests` | PASS |
| `docker compose run --rm app php -l tests/Consumer/fixtures/opentelemetry/emit.php` | PASS |
| `docker compose run --rm app php -l tests/Consumer/fixtures/opentelemetry/lgtm-query.php` | PASS |
| `docker compose run --rm app mago analyze tests/Consumer/fixtures/opentelemetry/lgtm-query.php` | PASS with 9 existing dynamic JSON `mixed-assignment` warnings |
| `docker compose run --rm app mago analyze tests/Consumer/fixtures/opentelemetry/emit.php` | BLOCKED by Consumer-only OpenTelemetry SDK/Exporter classes absent from main Composer autoload; no Production dependency added |
| `mise exec -- pnpm --dir docs/website run test` | PASS (77 tests) |
| `mise exec -- pnpm --dir docs/website run check` | PASS |
| `mise exec -- pnpm --dir docs/website run build` | PASS (42 pages; known Vite chunk/root-route warnings only) |
| Public Guide management-ID guard | PASS |
| Management-ID guard / `git diff --check` | PASS |
| Documentation Reviewer final re-review | PASS; P1=0, P2=0, P3=0 |

## Acceptance Criteria

- [x] New Decision fixes the Development-only Application-owned boundary.
- [x] Pinned LGTM Consumer starts with randomized resources and loopback-only
  Grafana/OTLP publication.
- [x] Grafana health and Tempo/Prometheus provisioning are machine verified.
- [x] Exact Trace ID and `blackops.operation.duration` are machine verified.
- [x] Sensitive/high-cardinality guard, read-only source mount, and cleanup pass.
- [x] Public guides, Specification, Roadmap, TODO, Task, and STATE are synced.
- [x] Existing Collector Consumer and full required quality commands pass.

## Remaining Issues

The lgtm-query analyzer no longer reports nullable array access for the metric
response; its remaining nine warnings are dynamic JSON `mixed` assignments. The
Emitter analyzer cannot resolve its explicitly mounted Consumer-only
OpenTelemetry SDK/Exporter classes because those packages are not main Composer
dependencies; runtime Consumer evidence remains the applicable verification.

Documentation Browser review was Not Verified because the Reviewer environment
could not reach the Orchestrator-owned localhost server. Website test, check,
build, static artifact, navigation, and accessibility markup guards passed; no
P1/P2/P3 Documentation Finding remains. Push and deploy remain out of scope.

## Suggested Next Action

Commit the accepted P20-018G change. Do not push or deploy without a separate
instruction.
