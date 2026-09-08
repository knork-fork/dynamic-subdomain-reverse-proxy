<?php
// Internal endpoint hit by nginx's auth_request for every proxied request.
// Re-reads the config file on every call so edits take effect immediately.

$configPath = '/etc/proxy-router/domains.json';

$raw = @file_get_contents($configPath);
if ($raw === false) {
    http_response_code(500);
    exit;
}

$map = json_decode($raw, true);
if (!is_array($map)) {
    http_response_code(500);
    exit;
}

$host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);

if (!array_key_exists($host, $map) || !ctype_digit((string) $map[$host])) {
    // 403 (not 404) so nginx's auth_request treats it as a clean "deny"
    // rather than an upstream error.
    http_response_code(403);
    exit;
}

header('X-Upstream-Port: ' . (int) $map[$host]);
http_response_code(200);
