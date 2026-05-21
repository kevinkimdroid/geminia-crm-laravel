<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Resolves erp-clients-api HTTP root for Finance and ERP SMS messaging.
 *
 * ERP_CLIENTS_HTTP_URL often includes a path prefix (e.g. …/erp-api/api/clients).
 * A naive "scheme + host + port" derivation drops that prefix and every route 404s.
 */
final class ErpHttpBaseUrl
{
    /**
     * Strip accidental trailing segments from an explicit base (…/clients, …/api).
     */
    public static function normalizeBase(string $base): string
    {
        $b = rtrim($base, '/');
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach (['/api/clients', '/clients', '/api'] as $suffix) {
                if ($suffix !== '' && str_ends_with($b, $suffix)) {
                    $b = rtrim(substr($b, 0, -strlen($suffix)), '/');
                    $changed = true;

                    break;
                }
            }
        }

        return $b;
    }

    /**
     * When FINANCE_ERP_HTTP_BASE is empty, derive API root from ERP_CLIENTS_HTTP_URL,
     * preserving any path prefix before …/api/clients, …/clients, or trailing …/api.
     */
    public static function deriveFromClientsHttpUrl(string $clientsUrl): string
    {
        $clientsUrl = rtrim(trim($clientsUrl), '/');
        if ($clientsUrl === '') {
            return '';
        }

        $parts = parse_url($clientsUrl);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        $path = isset($parts['path']) ? rtrim((string) $parts['path'], '/') : '';
        if ($path === '') {
            return self::buildOrigin($parts);
        }

        foreach (['/api/clients', '/clients', '/api'] as $suffix) {
            if (str_ends_with($path, $suffix)) {
                $path = rtrim(substr($path, 0, -strlen($suffix)), '/');

                break;
            }
        }

        $origin = self::buildOrigin($parts);
        if ($path === '' || $path === '/') {
            return $origin;
        }

        return self::normalizeBase($origin . $path);
    }

    /**
     * @param  array{scheme: string, host: string, port?: int|string}  $parts
     */
    private static function buildOrigin(array $parts): string
    {
        $url = $parts['scheme'] . '://' . $parts['host'];
        if (! empty($parts['port'])) {
            $url .= ':' . $parts['port'];
        }

        return rtrim($url, '/');
    }
}
