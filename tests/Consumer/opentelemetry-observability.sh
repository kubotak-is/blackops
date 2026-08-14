#!/usr/bin/env bash

set -euo pipefail

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
TEMP=$(mktemp -d)
PROJECT="blackops-otel-${RANDOM}-$$"
NETWORK="${PROJECT}-network"
COLLECTOR="${PROJECT}-collector"
EMITTER="${PROJECT}-emitter"
HEALTH_BEFORE="${PROJECT}-health-before"
HEALTH_AFTER="${PROJECT}-health-after"
POST_STOP="${PROJECT}-post-stop"
VALIDATOR="${PROJECT}-validator"
HEALTH_PARSE_BEFORE="${PROJECT}-health-parse-before"
HEALTH_PARSE_AFTER="${PROJECT}-health-parse-after"
COLLECTOR_IMAGE='otel/opentelemetry-collector:0.158.0@sha256:5b97e6e3550ec6e48a71dba6f6304d349a293af8df4ee1f51da67be94fce2ecd'
COLLECTOR_LOG="${TEMP}/collector.log"
EMIT_OUTPUT="${TEMP}/emit.json"

cleanup() {
    docker rm -f "${COLLECTOR}" "${EMITTER}" "${HEALTH_BEFORE}" "${HEALTH_AFTER}" "${POST_STOP}" "${VALIDATOR}" "${HEALTH_PARSE_BEFORE}" "${HEALTH_PARSE_AFTER}" >/dev/null 2>&1 || true
    docker network rm "${NETWORK}" >/dev/null 2>&1 || true
    rm -rf "${TEMP}"
}
trap cleanup EXIT

fail() {
    printf 'OpenTelemetry local collector validation failed: %s\n' "$1" >&2
    if test -f "${COLLECTOR_LOG}"; then
        sed -n '1,240p' "${COLLECTOR_LOG}" >&2 || true
    fi
    exit 1
}

docker network create "${NETWORK}" >/dev/null
timeout --signal=TERM 20 docker run -d --name "${COLLECTOR}" --network "${NETWORK}" --network-alias collector \
    --volume "${ROOT}/tests/Consumer/fixtures/opentelemetry/collector-config.yaml:/etc/otelcol/config.yaml:ro" \
    "${COLLECTOR_IMAGE}" --config=/etc/otelcol/config.yaml >/dev/null

for _ in $(seq 1 30); do
    if docker logs "${COLLECTOR}" 2>&1 | grep -q 'Everything is ready'; then
        break
    fi
    sleep 1
done
docker logs "${COLLECTOR}" 2>&1 | grep -q 'Everything is ready' \
    || fail 'collector did not become ready'

timeout --signal=TERM 45 docker run --rm --name "${EMITTER}" --network "${NETWORK}" \
    --volume "${ROOT}:/framework:ro" \
    blackops/framework:dev php /framework/tests/Consumer/fixtures/opentelemetry/emit.php \
    >"${EMIT_OUTPUT}"

summary_output="${TEMP}/summary.json"
sed -n '2p' "${EMIT_OUTPUT}" >"${summary_output}"
correlation_output="${TEMP}/correlation.json"
sed -n '1p' "${EMIT_OUTPUT}" >"${correlation_output}"
if ! timeout --signal=TERM 15 docker run --rm --name "${VALIDATOR}" --volume "${TEMP}:/tmp/otel:ro" blackops/framework:dev php -r '
$summary = json_decode(file_get_contents("/tmp/otel/summary.json"), true, 512, JSON_THROW_ON_ERROR);
$correlation = json_decode(file_get_contents("/tmp/otel/correlation.json"), true, 512, JSON_THROW_ON_ERROR);
$id = static fn (mixed $value, int $length): bool => is_string($value)
    && preg_match("/^[0-9a-f]{" . $length . "}$/", $value) === 1;
if (($summary["event"] ?? null) !== "blackops.observability.summary"
    || ($correlation["schemaVersion"] ?? null) !== 1
    || ($correlation["kind"] ?? null) !== "application"
    || !is_array($correlation["telemetry"] ?? null)
    || !$id($summary["inline"]["traceId"] ?? null, 32)
    || !$id($summary["inline"]["spanId"] ?? null, 16)
    || ($correlation["telemetry"]["traceId"] ?? null) !== $summary["inline"]["traceId"]
    || ($correlation["telemetry"]["spanId"] ?? null) !== $summary["inline"]["spanId"]
    || ($summary["server"]["traceId"] ?? null) !== $summary["inline"]["traceId"]
    || ($summary["deferredProducer"]["traceId"] ?? null) !== ($summary["deferredWorker"]["traceId"] ?? null)
    || ($summary["deferredProducer"]["traceId"] ?? null) !== ($summary["deferredRetry"]["traceId"] ?? null)
    || ($summary["deferredWorker"]["spanId"] ?? null) === ($summary["deferredRetry"]["spanId"] ?? null)
    || ($summary["outboxProducer"]["traceId"] ?? null) !== ($summary["outboxRelay"]["traceId"] ?? null)
    || ($summary["outboxProducer"]["spanId"] ?? null) === ($summary["outboxRelay"]["spanId"] ?? null)) {
    exit(1);
}
$expected = [
    "blackops.operation.duration" => ["type" => "histogram", "unit" => "s"],
    "blackops.operation.active" => ["type" => "updowncounter", "unit" => "{operation}"],
    "blackops.worker.claims" => ["type" => "counter", "unit" => "{claim}"],
    "blackops.worker.heartbeat.failures" => ["type" => "counter", "unit" => "{failure}"],
    "blackops.outbox.relay.duration" => ["type" => "histogram", "unit" => "s"],
    "blackops.outbox.relay.records" => ["type" => "counter", "unit" => "{record}"],
    "blackops.scheduler.run.duration" => ["type" => "histogram", "unit" => "s"],
    "blackops.scheduler.occurrences" => ["type" => "counter", "unit" => "{occurrence}"],
    "blackops.observer.failures" => ["type" => "counter", "unit" => "{failure}"],
    "blackops.storage.protection.failures" => ["type" => "counter", "unit" => "{failure}"],
];
if (($summary["metrics"] ?? null) !== $expected) {
    exit(1);
}
echo $summary["inline"]["traceId"], " ", $summary["inline"]["spanId"], PHP_EOL;
' >"${TEMP}/validator.out" 2>"${TEMP}/validator.err"; then
    cat "${TEMP}/validator.out" "${TEMP}/validator.err" >&2 || true
    fail 'structured correlation, span propagation, or metric matrix contract failed'
fi
correlation_result=$(cat "${TEMP}/validator.out")
read -r trace_id span_id <<<"${correlation_result}"
test "${#trace_id}" -eq 32 || fail 'trace id was not emitted'
test "${#span_id}" -eq 16 || fail 'span id was not emitted'
if grep -Fq 'sensitive-tenant-secret' "${EMIT_OUTPUT}"; then
    fail 'sensitive sentinel leaked into JSONL output'
fi

for _ in $(seq 1 20); do
    docker logs "${COLLECTOR}" >"${COLLECTOR_LOG}" 2>&1
    if grep -q "${trace_id}" "${COLLECTOR_LOG}" && grep -q 'blackops.operation.duration' "${COLLECTOR_LOG}"; then
        break
    fi
    sleep 1
done
grep -q "${trace_id}" "${COLLECTOR_LOG}" || fail 'collector did not receive the trace'
grep -q 'blackops.operation.duration' "${COLLECTOR_LOG}" || fail 'collector did not receive the metric'
if grep -Eqi 'password|authorization|api[_-]?key|secret|token|dsn|postgresql://' "${COLLECTOR_LOG}"; then
    fail 'collector log contained a sensitive-looking field'
fi
grep -Fq 'blackops.tenant.id: Str([masked])' "${COLLECTOR_LOG}" \
    || fail 'BlackOps trace redaction did not mask the tenant sentinel'
metric_log=$(awk '
    /otelcol.signal.*metrics/ { metrics=1 }
    /otelcol.signal.*traces/ { metrics=0 }
    metrics { print }
' "${COLLECTOR_LOG}")
for metric_expectation in \
    'blackops.operation.duration|s|Histogram' \
    'blackops.operation.active|{operation}|Sum' \
    'blackops.worker.claims|{claim}|Sum' \
    'blackops.worker.heartbeat.failures|{failure}|Sum' \
    'blackops.outbox.relay.duration|s|Histogram' \
    'blackops.outbox.relay.records|{record}|Sum' \
    'blackops.scheduler.run.duration|s|Histogram' \
    'blackops.scheduler.occurrences|{occurrence}|Sum' \
    'blackops.observer.failures|{failure}|Sum' \
    'blackops.storage.protection.failures|{failure}|Sum'; do
    IFS='|' read -r metric_name metric_unit metric_type <<<"${metric_expectation}"
    grep -Fq "Name: ${metric_name}" <<<"${metric_log}" \
        || fail "collector log omitted metric ${metric_name}"
    grep -Fq "Unit: ${metric_unit}" <<<"${metric_log}" \
        || fail "collector log omitted unit for ${metric_name}"
    grep -Fq "Type: ${metric_type}" <<<"${metric_log}" \
        || fail "collector log omitted type for ${metric_name}"
done
if grep -Eqi 'blackops\.(operation\.id|attempt|tenant|actor|trace|key|occurrence)' <<<"${metric_log}"; then
    fail 'collector metric datapoint attributes contained a forbidden identity field'
fi

healthy_before=$(timeout --signal=TERM 15 docker run --rm --name "${HEALTH_BEFORE}" --volume "${ROOT}:/framework:ro" \
    blackops/framework:dev php /framework/tests/Consumer/fixtures/opentelemetry/health.php)
timeout --signal=TERM 10 docker stop "${COLLECTOR}" >/dev/null
healthy_after=$(timeout --signal=TERM 15 docker run --rm --name "${HEALTH_AFTER}" --volume "${ROOT}:/framework:ro" \
    blackops/framework:dev php /framework/tests/Consumer/fixtures/opentelemetry/health.php)
timeout --signal=TERM 20 docker run --rm --name "${POST_STOP}" --network "${NETWORK}" --volume "${ROOT}:/framework:ro" \
    blackops/framework:dev php /framework/tests/Consumer/fixtures/opentelemetry/post-stop.php \
    | grep -Fx 'post-stop telemetry call completed' \
    || fail 'primary telemetry call did not remain isolated after collector outage'
healthy_status_before=$(printf '%s' "${healthy_before}" | timeout --signal=TERM 15 docker run --rm -i --name "${HEALTH_PARSE_BEFORE}" --volume "${ROOT}:/framework:ro" blackops/framework:dev php -r '$data=json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR); echo $data["status"];')
healthy_status_after=$(printf '%s' "${healthy_after}" | timeout --signal=TERM 15 docker run --rm -i --name "${HEALTH_PARSE_AFTER}" --volume "${ROOT}:/framework:ro" blackops/framework:dev php -r '$data=json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR); echo $data["status"];')
test "${healthy_status_before}" = pass \
    || fail 'readiness was not passing before collector outage'
test "${healthy_status_after}" = pass \
    || fail 'readiness depended on collector availability'

docker rm "${COLLECTOR}" >/dev/null
docker network rm "${NETWORK}" >/dev/null
test -z "$(docker ps -a --filter "name=^/${COLLECTOR}$" --format '{{.Names}}')" \
    || fail 'collector container was not cleaned up'
test -z "$(docker network ls --filter "name=^${NETWORK}$" --format '{{.Name}}')" \
    || fail 'collector network was not cleaned up'

echo 'OpenTelemetry local collector and health isolation contract passed.'
