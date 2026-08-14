# D138: Local Grafana LGTM Development Backend

Status: Decided

## Context

P20-018E established an API-only OpenTelemetry Provider boundary and a
Consumer-only local Collector journey. Developers also need a repeatable local
viewer for the emitted Trace and Metric without introducing a Framework-owned
Dashboard, a Production backend, or a new health dependency.

## Decision

1. `grafana/otel-lgtm` is an Application-owned backend for Development, Demo,
   and Test journeys only. It is not a Framework Production SDK, Exporter,
   Collector, Dashboard, or Composer dependency.
2. The Consumer pins
   `grafana/otel-lgtm:0.29.2@sha256:af7242c1a9608faf6d26e6f235392fd0c32b67258228f9a3cfc96e724974930c`.
   The digest was verified to resolve its linux/amd64 and linux/arm64 OCI index
   members.
3. The isolated Consumer creates randomized Docker resources and publishes
   only randomly assigned loopback ports for Grafana `3000` and OTLP HTTP
   `4318`. Tempo, Prometheus, Loki, and other backend ports remain internal to
   the Docker network. Grafana's datasource proxy is the verification boundary.
4. The journey verifies Grafana health, provisioned Tempo and Prometheus
   datasources, OTLP ingestion from the existing BlackOps Emitter, exact Trace
   ID lookup in Tempo, and `blackops.operation.duration` lookup in Prometheus.
   It does not print credentials, backend response dumps, or sensitive signal
   payloads.
5. Only the LGTM Consumer passes an explicit cumulative-temporality environment
   setting to its Emitter for Prometheus OTLP ingestion. An Emitter without that
   setting keeps its existing default. This is a Consumer delivery setting only;
   it does not alter the Framework metric schema, names, or units.
6. LGTM availability, Grafana state, Dashboard state, and datasource state are
   not Liveness or Readiness inputs. Export failure remains Best-effort and does
   not change a Primary Operation, Journal, Outcome, or HTTP Response.
7. No Remote backend, Cloud credential, TLS setup, Alert/SLO, Dashboard JSON,
   Persistent Volume, Production Compose Default, or Public Deployment is
   introduced by this decision.
8. The Consumer's no-argument mode is bounded CI evidence and cleans up before
   exit without advertising dead URLs. Its `--interactive` mode keeps the
   randomized resources alive after probes until Ctrl-C/TERM, prints only the
   loopback URLs, safe Trace ID, and strictly allowlisted selected stored metric
   name, and uses the known `admin/admin` login for this disposable local lane.
   Those values are safe navigation values, not credentials, backend responses,
   trace/metric payloads, or labels.

## Consequences

The local viewer is intentionally disposable: stopping the randomized
container removes its in-memory data. Contributors use Grafana at
`http://127.0.0.1:<printed-grafana-port>` for browsing and send OTLP HTTP to the
printed `http://127.0.0.1:<printed-otlp-port>` only when using the Host lane.
Port `4318` is an ingestion endpoint, not a Grafana UI. The Consumer's container lane uses the
`collector` network alias and does not require a Host backend port.

## Traceability

- [Structured Logging and OpenTelemetry](../spec/100-structured-logging-and-opentelemetry.md)
- [P20-018G Local Grafana LGTM Dashboard Evidence](../orchestration/tasks/P20-018G-local-grafana-lgtm-dashboard.md)
