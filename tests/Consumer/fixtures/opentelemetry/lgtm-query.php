<?php

declare(strict_types=1);

/**
 * Probe the local Grafana API without printing backend responses.
 *
 * The probe deliberately uses Grafana's datasource proxy so Tempo and
 * Prometheus ports never need to be published to the host.
 */

$grafanaUrl = rtrim((string) getenv('GRAFANA_URL'), '/');
$username = (string) getenv('GRAFANA_USER');
$password = (string) getenv('GRAFANA_PASSWORD');
$traceId = strtolower((string) getenv('TRACE_ID'));

if ($grafanaUrl === '' || $username === '' || $password === '' || !preg_match('/^[0-9a-f]{32}$/', $traceId)) {
    exit(2);
}

$request = static function (string $url) use ($username, $password): ?array {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' =>
                'Authorization: Basic '
                    . base64_encode($username . ':' . $password)
                    . "\r\nAccept: application/json\r\n",
            'timeout' => 5,
            'ignore_errors' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    $headers = function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : false;
    $statusLine = is_array($headers) ? $headers[0] ?? null : null;
    if (!is_string($body) || !is_string($statusLine) || !preg_match('/\s(2\d\d)\s/', $statusLine)) {
        return null;
    }

    try {
        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return null;
    }

    return is_array($decoded) ? $decoded : null;
};

$contains = static function (mixed $value, string $needle) use (&$contains): bool {
    if (is_string($value)) {
        return str_contains(strtolower($value), strtolower($needle));
    }

    if (!is_array($value)) {
        return false;
    }

    foreach ($value as $child) {
        if ($contains($child, $needle)) {
            return true;
        }
    }

    return false;
};

$hasForbiddenMetricLabel = static function (mixed $value) use (&$hasForbiddenMetricLabel): bool {
    if (!is_array($value)) {
        return false;
    }

    foreach ($value as $key => $child) {
        if (
            is_string($key)
            && preg_match('/(^|[._])(id|attempt|tenant|actor|trace|span|key|occurrence)([._]|$)/i', $key) === 1
        ) {
            return true;
        }
        if (is_string($child) && preg_match('/sensitive-(tenant|actor)-secret|^[0-9a-f]{32}$/i', $child) === 1) {
            return true;
        }
        if ($hasForbiddenMetricLabel($child)) {
            return true;
        }
    }

    return false;
};

$hasTraceSpans = static function (mixed $value) use (&$hasTraceSpans): bool {
    if (!is_array($value)) {
        return false;
    }

    foreach ($value as $key => $child) {
        if ($key === 'spans' && is_array($child) && $child !== []) {
            return true;
        }
        if ($hasTraceSpans($child)) {
            return true;
        }
    }

    return false;
};

$datasources = $request($grafanaUrl . '/api/datasources');
if ($datasources === null) {
    exit(3);
}

$uids = [];
foreach ($datasources as $datasource) {
    if (!is_array($datasource) || !is_string($datasource['type'] ?? null) || !is_string($datasource['uid'] ?? null)) {
        continue;
    }

    $type = strtolower($datasource['type']);
    if ($type === 'tempo' || $type === 'prometheus') {
        $uids[$type] = $datasource['uid'];
    }
}

if (!isset($uids['tempo'], $uids['prometheus'])) {
    exit(4);
}

$health = $request($grafanaUrl . '/api/health');
if (($health['database'] ?? null) !== 'ok') {
    exit(5);
}

$deadline = microtime(true) + 40.0;
$traceFound = false;
$traceFailureCode = 62;
$metricFound = false;
$metricFailureCode = 72;
$metricNames = [];
$selectedMetricName = null;
while (microtime(true) < $deadline) {
    if (!$traceFound) {
        $trace = $request(sprintf(
            '%s/api/datasources/proxy/uid/%s/api/traces/%s',
            $grafanaUrl,
            rawurlencode($uids['tempo']),
            rawurlencode($traceId),
        ));
        if ($trace === null) {
            $traceFailureCode = 61;
        } elseif ($contains($trace, 'sensitive-tenant-secret') || $contains($trace, 'sensitive-actor-secret')) {
            $traceFailureCode = 63;
        } elseif ($hasTraceSpans($trace) && $contains($trace, 'blackops.operation.execute')) {
            // The request path is the exact emitted Trace ID. Tempo may encode
            // IDs inside OTLP JSON as base64, so non-empty returned spans are
            // the response proof rather than an incidental hex string search.
            $traceFound = true;
        } else {
            $traceFailureCode = 62;
        }
    }

    if (!$metricFound) {
        if ($selectedMetricName === null) {
            $metricNames = [];
            $discovery = $request(sprintf(
                '%s/api/datasources/proxy/uid/%s/api/v1/label/__name__/values',
                $grafanaUrl,
                rawurlencode($uids['prometheus']),
            ));
            $discoveredNames = is_array($discovery['data'] ?? null) ? $discovery['data'] : [];
            $candidateNames = [];
            foreach ($discoveredNames as $name) {
                if (
                    !is_string($name)
                    || preg_match('/^[\x21-\x7e]+$/', $name) !== 1
                    || !str_contains($name, 'blackops')
                ) {
                    continue;
                }
                $candidateNames[] = $name;
            }
            sort($candidateNames);
            $metricNames = array_slice($candidateNames, 0, 20);
            if ($discovery === null || ($discovery['status'] ?? null) !== 'success') {
                $metricFailureCode = 71;
            } else {
                foreach ($metricNames as $name) {
                    if (
                        preg_match(
                            '/^(?:blackops\.operation\.duration|blackops_operation_duration)(?:_seconds)?(?:_bucket|_sum|_count)?$/',
                            $name,
                        ) === 1
                    ) {
                        $selectedMetricName = $name;
                        break;
                    }
                }
                if ($selectedMetricName === null) {
                    $metricFailureCode = 72;
                }
            }
        }
        if ($selectedMetricName !== null) {
            $metric = $request(sprintf(
                '%s/api/datasources/proxy/uid/%s/api/v1/query?%s',
                $grafanaUrl,
                rawurlencode($uids['prometheus']),
                http_build_query(['query' => '{__name__="' . $selectedMetricName . '"}']),
            ));
            if ($metric === null) {
                $metricFailureCode = 71;
            }
            $metricData = is_array($metric['data'] ?? null) ? $metric['data'] : [];
            $resultType = $metricData['resultType'] ?? null;
            $results = is_array($metricData['result'] ?? null) ? $metricData['result'] : [];
            $storedNameFound = false;
            foreach ($results as $result) {
                $name = is_array($result['metric'] ?? null) ? $result['metric']['__name__'] ?? null : null;
                if ($name === $selectedMetricName) {
                    $storedNameFound = true;
                }
            }
            $metricForbidden =
                $hasForbiddenMetricLabel($results)
                || $contains($results, 'sensitive-tenant-secret')
                || $contains($results, 'sensitive-actor-secret');
            if ($metric !== null && ($resultType !== 'matrix' && $resultType !== 'vector' || $results === [])) {
                $metricFailureCode = 72;
            } elseif ($metric !== null && !$storedNameFound) {
                $metricFailureCode = 73;
            } elseif ($metric !== null && $metricForbidden) {
                $metricFailureCode = 74;
            }
            $metricFound =
                is_array($metric)
                && ($metric['status'] ?? null) === 'success'
                && is_array($metric['data'] ?? null)
                && ($resultType === 'matrix' || $resultType === 'vector')
                && $results !== []
                && $storedNameFound
                && !$metricForbidden;
        }
    }

    if ($traceFound && $metricFound) {
        echo 'Grafana health, Tempo trace, and Prometheus metric probes passed; metric=', $selectedMetricName, PHP_EOL;
        exit(0);
    }

    usleep(500000);
}

if (!$metricFound) {
    echo 'Prometheus metric discovery names: ', $metricNames === [] ? 'none' : implode(',', $metricNames), PHP_EOL;
}

exit($traceFound ? $metricFailureCode : $traceFailureCode);
