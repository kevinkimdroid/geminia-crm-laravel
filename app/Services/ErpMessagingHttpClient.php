<?php

namespace App\Services;

use App\Support\ErpHttpBaseUrl;
use Illuminate\Support\Facades\Http;

/**
 * ERP SMS life messages via erp-clients-api (same host as finance) — avoids ORA-03113
 * from CRM PHP direct Oracle when the API server has a stable DB link.
 */
class ErpMessagingHttpClient
{
    public function isConfigured(): bool
    {
        return $this->baseUrl() !== '';
    }

    public function baseUrl(): string
    {
        $b = rtrim((string) config('erp.messages_http_base', ''), '/');
        if ($b === '') {
            $b = rtrim((string) config('erp.finance_http_base', ''), '/');
        }

        return ErpHttpBaseUrl::normalizeBase($b);
    }

    /**
     * @return \Illuminate\Http\Client\PendingRequest
     */
    private function http()
    {
        $token = trim((string) config('erp.messages_http_token', ''));
        if ($token === '') {
            $token = trim((string) config('erp.finance_http_token'));
        }
        $req = Http::withOptions([
            'connect_timeout' => max(2, (int) config('erp.messages_http_connect_timeout', 5)),
        ])
            ->timeout(max(10, (int) config('erp.messages_http_timeout', 45)))
            ->acceptJson();
        if ($token !== '') {
            $req = $req->withToken($token);
        }

        return $req;
    }

    /**
     * GET messaging routes: same pattern as {@see FinanceErpHttpClient::getFinance} —
     * try path without /api first, then /api/... on 404 (proxies differ).
     */
    private function getMessaging(string $path, array $params = []): \Illuminate\Http\Client\Response
    {
        $base = $this->baseUrl();
        $withoutApi = $base . $path;
        $withApi = $base . '/api' . $path;

        $response = $this->http()->get($withoutApi, $params);
        if ($response->status() === 404) {
            $response = $this->http()->get($withApi, $params);
        }

        return $response;
    }

    /**
     * POST messaging routes: without /api first, then /api/... on 404.
     */
    private function postMessaging(string $path, array $json): \Illuminate\Http\Client\Response
    {
        $base = $this->baseUrl();
        $withoutApi = $base . $path;
        $withApi = $base . '/api' . $path;

        $response = $this->http()->asJson()->post($withoutApi, $json);
        if ($response->status() === 404) {
            $response = $this->http()->asJson()->post($withApi, $json);
        }

        return $response;
    }

    private function errorMessage(\Illuminate\Http\Client\Response $response): string
    {
        $body = $response->json('error');
        if (is_string($body) && $body !== '') {
            return $body;
        }
        $status = $response->status();
        if ($status === 404) {
            return 'ERP API HTTP 404 — no SMS routes on this host. Fix FINANCE_ERP_HTTP_BASE / ERP_MESSAGES_HTTP_BASE '
                . '(include any path prefix before /messages), run php artisan config:clear, deploy and restart erp-clients-api (app.py). '
                . 'With HTTP messaging enabled, CRM does not fall back to Oracle. Diagnostic: php artisan erp:test-messages-api';
        }

        return 'ERP API HTTP ' . $status;
    }

    /**
     * @return array{count: int, error: ?string}
     */
    public function fetchPendingCount(): array
    {
        if (! $this->isConfigured()) {
            return ['count' => 0, 'error' => 'No ERP API base URL. Set ERP_MESSAGES_HTTP_BASE or FINANCE_ERP_HTTP_BASE.'];
        }

        $response = $this->getMessaging('/messages/sms/pending', ['count_only' => 1]);
        if (! $response->successful()) {
            return ['count' => 0, 'error' => $this->errorMessage($response)];
        }

        return ['count' => (int) ($response->json('count') ?? 0), 'error' => null];
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, error: ?string}
     */
    public function fetchPendingSms(int $limit): array
    {
        if (! $this->isConfigured()) {
            return ['rows' => [], 'error' => 'No ERP API base URL. Set ERP_MESSAGES_HTTP_BASE or FINANCE_ERP_HTTP_BASE (or ERP_CLIENTS_HTTP_URL so the host can be derived).'];
        }

        $response = $this->getMessaging('/messages/sms/pending', [
            'limit' => max(1, min(500, $limit)),
        ]);
        if (! $response->successful()) {
            return ['rows' => [], 'error' => $this->errorMessage($response)];
        }

        $data = $response->json('data');
        if (! is_array($data)) {
            return ['rows' => [], 'error' => null];
        }

        return ['rows' => $data, 'error' => null];
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, error: ?string}
     */
    public function fetchSentSms(int $limit): array
    {
        if (! $this->isConfigured()) {
            return ['rows' => [], 'error' => 'No ERP API base URL. Set ERP_MESSAGES_HTTP_BASE or FINANCE_ERP_HTTP_BASE.'];
        }

        $response = $this->getMessaging('/messages/sms/sent', [
            'limit' => max(1, min(500, $limit)),
        ]);
        if (! $response->successful()) {
            return ['rows' => [], 'error' => $this->errorMessage($response)];
        }

        $data = $response->json('data');
        if (! is_array($data)) {
            return ['rows' => [], 'error' => null];
        }

        return ['rows' => $data, 'error' => null];
    }

    /**
     * @return array{ok: bool, updated: int, error: ?string}
     */
    public function markSmsSent(string $smsCode, string $fromStatus, string $toStatus): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'updated' => 0, 'error' => 'No ERP API base URL. Set ERP_MESSAGES_HTTP_BASE or FINANCE_ERP_HTTP_BASE.'];
        }

        $response = $this->postMessaging('/messages/sms/mark-sent', [
            'sms_code' => $smsCode,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
        ]);
        if (! $response->successful()) {
            return ['ok' => false, 'updated' => 0, 'error' => $this->errorMessage($response)];
        }

        $updated = (int) ($response->json('updated') ?? 0);

        return ['ok' => true, 'updated' => $updated, 'error' => null];
    }

    /**
     * @param  array<int, string>  $smsCodes
     * @return array{ok: bool, updated: int, requested: int, error: ?string}
     */
    public function markSmsSentBatch(array $smsCodes, string $fromStatus, string $toStatus): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'updated' => 0, 'requested' => 0, 'error' => 'No ERP API base URL.'];
        }

        $codes = array_values(array_unique(array_filter(array_map(
            fn ($c) => trim((string) $c),
            $smsCodes
        ))));

        if ($codes === []) {
            return ['ok' => true, 'updated' => 0, 'requested' => 0, 'error' => null];
        }

        $response = $this->postMessaging('/messages/sms/mark-sent-batch', [
            'sms_codes' => $codes,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
        ]);
        if ($response->status() === 404) {
            return $this->markSmsSentBatchFallback($codes, $fromStatus, $toStatus);
        }
        if (! $response->successful()) {
            return ['ok' => false, 'updated' => 0, 'requested' => count($codes), 'error' => $this->errorMessage($response)];
        }

        return [
            'ok' => true,
            'updated' => (int) ($response->json('updated') ?? 0),
            'requested' => (int) ($response->json('requested') ?? count($codes)),
            'error' => null,
        ];
    }

    /**
     * Older erp-clients-api without mark-sent-batch.
     *
     * @param  array<int, string>  $codes
     * @return array{ok: bool, updated: int, requested: int, error: ?string}
     */
    private function markSmsSentBatchFallback(array $codes, string $fromStatus, string $toStatus): array
    {
        $updated = 0;
        foreach ($codes as $code) {
            $res = $this->markSmsSent($code, $fromStatus, $toStatus);
            if ($res['error'] !== null) {
                return ['ok' => false, 'updated' => $updated, 'requested' => count($codes), 'error' => $res['error']];
            }
            $updated += (int) $res['updated'];
        }

        return ['ok' => true, 'updated' => $updated, 'requested' => count($codes), 'error' => null];
    }
}
