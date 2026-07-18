<?php

declare(strict_types=1);

$method = $argv[1] ?? '';
$target = $argv[2] ?? '';
$cookie = $argv[3] ?? null;

if (!in_array($method, ['GET', 'HEAD', 'POST'], true) || $target === '') {
    fwrite(STDERR, "Usage: viewer-request.php GET|HEAD|POST target [cookie]\n");
    exit(2);
}

$parts = parse_url($target);
if ($parts === false) {
    fwrite(STDERR, "Invalid target.\n");
    exit(2);
}
$requestTarget = $parts['path'] ?? '/';
if (isset($parts['query'])) {
    $requestTarget .= '?' . $parts['query'];
}

$connection = stream_socket_client('tcp://127.0.0.1:8082', $errorCode, $errorMessage, 2);
if ($connection === false) {
    fwrite(STDERR, "Viewer connection failed.\n");
    exit(3);
}

$headers = [
    sprintf('%s %s HTTP/1.1', $method, $requestTarget),
    'Host: 127.0.0.1:8082',
    'Connection: close',
];
if (is_string($cookie) && $cookie !== '') {
    $headers[] = 'Cookie: ' . $cookie;
}
if ($method === 'POST') {
    $headers[] = 'Content-Length: 0';
}

fwrite($connection, implode("\r\n", $headers) . "\r\n\r\n");
stream_set_timeout($connection, 3);
$response = stream_get_contents($connection);
fclose($connection);

if (!is_string($response) || $response === '') {
    fwrite(STDERR, "Viewer returned no response.\n");
    exit(4);
}

fwrite(STDOUT, $response);
