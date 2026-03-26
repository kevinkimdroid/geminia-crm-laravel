<?php
declare(strict_types=1);

/**
 * Public entry for crm-client-feedback. Document root must be Laravel public/.
 */
$app = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'crm-client-feedback' . DIRECTORY_SEPARATOR . 'index.php';
if (! is_file($app)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'CRM client feedback app is missing.';
    exit;
}
require $app;
