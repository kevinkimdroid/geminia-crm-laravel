<?php

declare(strict_types=1);

header('Content-Type: text/xml; charset=UTF-8');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
$scheme = $isHttps ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
$endpoint = $scheme . '://' . $host . '/webhooks/pbx/incoming';

$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
curl_setopt($ch, CURLOPT_TIMEOUT, 3);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $_REQUEST);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Important: never block PBX call setup on webhook forwarding.
// Even if forwarding fails, return Success so call signaling is not interrupted.
if ($response === false || $httpCode >= 400) {
    error_log('[PBX callback forward warning] ' . ($error ?: ('HTTP ' . $httpCode)));
}

$trimmed = ltrim((string) $response);
if ($trimmed !== '' && str_starts_with($trimmed, '<?xml') && stripos($trimmed, '<status>Success</status>') !== false) {
    echo $response;
    exit;
}

echo '<?xml version="1.0" encoding="UTF-8"?><Response><status>Success</status><message>Accepted</message></Response>';
