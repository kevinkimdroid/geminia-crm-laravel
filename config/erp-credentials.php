<?php

/**
 * ERP (Oracle) credentials from JSON file.
 *
 * Set ERP_CREDENTIALS_FILE in .env to the path of your JSON file.
 * Example: ERP_CREDENTIALS_FILE=/secure/path/erp-credentials.json
 *
 * JSON structure (matches common Oracle config formats):
 * {
 *   "user": "TQ_LMS",
 *   "password": "TQ#LMS2019c",
 *   "connectString": "10.1.4.100:18032/PDBTQUEST"
 * }
 *
 * Or use host/port/database explicitly:
 * {
 *   "user": "TQ_LMS",
 *   "password": "TQ#LMS2019c",
 *   "host": "10.1.4.100",
 *   "port": 18032,
 *   "database": "PDBTQUEST"
 * }
 *
 * If ERP_CREDENTIALS_FILE is not set or file is missing, returns [] and
 * database config falls back to ERP_* env vars.
 */
$path = env('ERP_CREDENTIALS_FILE', '');

if (empty($path) || !is_readable($path)) {
    return [];
}

$json = json_decode(file_get_contents($path), true);

if (!is_array($json)) {
    return [];
}

$host = $json['host'] ?? null;
$port = (int) ($json['port'] ?? 1521);
$service = $json['database'] ?? $json['service_name'] ?? $json['serviceName'] ?? null;

// Parse connectString "host:port/service_name" if present
if (!empty($json['connectString']) && preg_match('#^([^:]+):(\d+)/(.+)$#', trim($json['connectString']), $m)) {
    $host = $m[1];
    $port = (int) $m[2];
    $service = $m[3];
}

return [
    'username' => $json['user'] ?? $json['username'] ?? '',
    'password' => $json['password'] ?? '',
    'host' => $host ?? '',
    'port' => $port,
    'service_name' => $service ?? '',
    'database' => $service ?? '',
];
