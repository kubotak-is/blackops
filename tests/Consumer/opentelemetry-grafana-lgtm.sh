#!/usr/bin/env bash

set -Eeuo pipefail

INTERACTIVE=0
case "${1:-}" in
    '') ;;
    --interactive)
        INTERACTIVE=1
        test "$#" -eq 1 || { printf 'Unknown arguments for Grafana LGTM Consumer\n' >&2; exit 2; }
        ;;
    *)
        printf 'Unknown arguments for Grafana LGTM Consumer\n' >&2
        exit 2
        ;;
esac

ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)
TEMP=$(mktemp -d)
RUN_ID="${RANDOM}-$$"
PROJECT="blackops-grafana-lgtm-${RUN_ID}"
NETWORK="${PROJECT}-network"
LGTM="${PROJECT}-backend"
EMITTER="${PROJECT}-emitter"
VALIDATOR="${PROJECT}-validator"
QUERY="${PROJECT}-query"
LGTM_IMAGE='grafana/otel-lgtm:0.29.2@sha256:af7242c1a9608faf6d26e6f235392fd0c32b67258228f9a3cfc96e724974930c'
GRAFANA_USER='admin'
if test "${INTERACTIVE}" -eq 1; then
    GRAFANA_PASSWORD='admin'
else
    GRAFANA_PASSWORD="local-${RANDOM}-${RANDOM}-${RANDOM}"
fi
EMIT_OUTPUT="${TEMP}/emit.json"

cleanup() {
    docker rm -f "${LGTM}" "${EMITTER}" "${VALIDATOR}" "${QUERY}" >/dev/null 2>&1 || true
    docker network rm "${NETWORK}" >/dev/null 2>&1 || true
    rm -rf "${TEMP}"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

fail() {
    printf 'Grafana LGTM validation failed: %s\n' "$1" >&2
    exit 1
}

git -C "${ROOT}" status --short >"${TEMP}/status.before"

docker network create "${NETWORK}" >/dev/null
docker run -d --name "${LGTM}" --network "${NETWORK}" --network-alias collector \
    --env "GF_SECURITY_ADMIN_USER=${GRAFANA_USER}" \
    --env "GF_SECURITY_ADMIN_PASSWORD=${GRAFANA_PASSWORD}" \
    -p 127.0.0.1::3000 -p 127.0.0.1::4318 \
    "${LGTM_IMAGE}" >/dev/null

for _ in $(seq 1 90); do
    status=$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "${LGTM}" 2>/dev/null || true)
    if test "${status}" = healthy; then
        break
    fi
    sleep 1
done
status=$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "${LGTM}" 2>/dev/null || true)
test "${status}" = healthy || fail 'LGTM container did not become healthy'

grafana_binding=$(docker port "${LGTM}" 3000/tcp | head -n 1)
otlp_binding=$(docker port "${LGTM}" 4318/tcp | head -n 1)
case "${grafana_binding}" in
    127.0.0.1:*) ;;
    *) fail 'Grafana was not published on a loopback-only random port' ;;
esac
case "${otlp_binding}" in
    127.0.0.1:*) ;;
    *) fail 'OTLP HTTP was not published on a loopback-only random port' ;;
esac
GRAFANA_PORT=${grafana_binding##*:}
OTLP_PORT=${otlp_binding##*:}

published_ports=$(docker port "${LGTM}" | sort)
test "$(printf '%s\n' "${published_ports}" | wc -l)" -eq 2 \
    || fail 'an unexpected backend port was published'
printf '%s\n' "${published_ports}" | grep -Fq '3000/tcp -> 127.0.0.1:' \
    || fail 'Grafana port mapping was not loopback-only'
printf '%s\n' "${published_ports}" | grep -Fq '4318/tcp -> 127.0.0.1:' \
    || fail 'OTLP port mapping was not loopback-only'

timeout --signal=TERM 45 docker run --rm --name "${EMITTER}" --network "${NETWORK}" \
    --volume "${ROOT}:/framework:ro" \
    --env BLACKOPS_OTEL_METRIC_TEMPORALITY=cumulative \
    blackops/framework:dev php /framework/tests/Consumer/fixtures/opentelemetry/emit.php \
    >"${EMIT_OUTPUT}"

if grep -Fq 'sensitive-tenant-secret' "${EMIT_OUTPUT}" || grep -Fq 'sensitive-actor-secret' "${EMIT_OUTPUT}"; then
    fail 'sensitive sentinel leaked into emitter output'
fi

trace_id=$(timeout --signal=TERM 15 docker run --rm --name "${VALIDATOR}" \
    --volume "${ROOT}:/framework:ro" --volume "${TEMP}:/tmp/lgtm:ro" \
    blackops/framework:dev php -r '
$lines = file("/tmp/lgtm/emit.json", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$summary = json_decode($lines[count($lines) - 1] ?? "", true, 512, JSON_THROW_ON_ERROR);
$trace = $summary["inline"]["traceId"] ?? null;
if (!is_string($trace) || preg_match("/^[0-9a-f]{32}$/", $trace) !== 1
    || !isset($summary["metrics"]["blackops.operation.duration"])) {
    exit(1);
}
echo $trace, PHP_EOL;
' | tail -n 1)
test "${#trace_id}" -eq 32 || fail 'emitter did not produce an exact trace id'

probe_status=0
timeout --signal=TERM 60 docker run --rm --network host \
    --name "${QUERY}" --volume "${ROOT}:/framework:ro" \
    --env "GRAFANA_URL=http://127.0.0.1:${GRAFANA_PORT}" \
    --env "GRAFANA_USER=${GRAFANA_USER}" \
    --env "GRAFANA_PASSWORD=${GRAFANA_PASSWORD}" --env "TRACE_ID=${trace_id}" \
    blackops/framework:dev php /framework/tests/Consumer/fixtures/opentelemetry/lgtm-query.php \
    || probe_status=$?
if test "${probe_status}" -ne 0; then
    case "${probe_status}" in
        2) fail 'Grafana probe input validation failed (safe stage code 2)' ;;
        3) fail 'Grafana datasource request failed (safe stage code 3)' ;;
        4) fail 'Grafana Tempo/Prometheus datasource provisioning failed (safe stage code 4)' ;;
        5) fail 'Grafana health probe failed (safe stage code 5)' ;;
        61) fail 'Grafana Tempo HTTP/non-JSON probe failed (safe stage code 61)' ;;
        62) fail 'Grafana Tempo JSON contained no trace spans (safe stage code 62)' ;;
        63) fail 'Grafana Tempo response contained a sensitive sentinel (safe stage code 63)' ;;
        71) fail 'Grafana Prometheus HTTP/non-JSON probe failed (safe stage code 71)' ;;
        72) fail 'Grafana Prometheus discovery/query returned no usable samples (safe stage code 72)' ;;
        73) fail 'Grafana Prometheus returned an unexpected metric name (safe stage code 73)' ;;
        74) fail 'Grafana Prometheus response contained a forbidden identity (safe stage code 74)' ;;
        124) fail 'Grafana probe timed out (bounded safe timeout)' ;;
        *) fail 'Grafana probe failed (bounded safe exit code)' ;;
    esac
fi

git -C "${ROOT}" status --short >"${TEMP}/status.after"
cmp -s "${TEMP}/status.before" "${TEMP}/status.after" \
    || fail 'source checkout changed during the LGTM journey'

if test "${INTERACTIVE}" -eq 1; then
    printf 'Grafana LGTM interactive journey is ready. Grafana=http://127.0.0.1:%s OTLP=http://127.0.0.1:%s Trace=%s\n' \
        "${GRAFANA_PORT}" "${OTLP_PORT}" "${trace_id}"
    while sleep 30; do
        :
    done
fi

echo 'Grafana LGTM local Trace/Metric dashboard journey passed.'
