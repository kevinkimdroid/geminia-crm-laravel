<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Advanta SMS API integration for Kenya.
 * Docs: https://www.advantasms.com/bulksms-api
 */
class AdvantaSmsService
{
    protected string $apiUrl;
    protected string $apikey;
    protected string $partnerId;
    protected string $shortcode;

    public function __construct()
    {
        $this->apiUrl = rtrim(config('advanta.api_url', 'https://quicksms.advantasms.com/api/services/sendsms/'), '/');
        $this->apikey = config('advanta.apikey', '');
        $this->partnerId = config('advanta.partner_id', '');
        $this->shortcode = config('advanta.shortcode', '');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apikey) && ! empty($this->partnerId) && ! empty($this->shortcode);
    }

    /**
     * Extract a user-friendly error message from API response.
     */
    protected function extractErrorMessage(mixed $body): string
    {
        if (! is_array($body)) {
            return (string) $body;
        }
        $r0 = $body['responses'][0] ?? null;
        if (is_array($r0)) {
            $msg = $r0['response-description'] ?? $r0['response_description'] ?? $r0['description'] ?? null;
            if (is_string($msg) && $msg !== 'Success') {
                return $msg;
            }
        }
        $msg = $body['message'] ?? $body['error'] ?? null;
        if (is_string($msg)) {
            return $msg;
        }
        return 'SMS delivery failed. Check your API credentials and try again.';
    }

    /**
     * Normalize Kenyan phone to 254XXXXXXXXX format.
     */
    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '254' . substr($phone, 1);
        } elseif (str_starts_with($phone, '7') && strlen($phone) === 9) {
            $phone = '254' . $phone;
        } elseif (! str_starts_with($phone, '254') && strlen($phone) === 9) {
            $phone = '254' . $phone;
        }
        return $phone;
    }

    /**
     * Send SMS to a single recipient.
     */
    public function send(string $mobile, string $message): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Advanta SMS is not configured. Set ADVANTA_API_KEY, ADVANTA_PARTNER_ID, ADVANTA_SHORTCODE in .env'];
        }

        $mobile = $this->normalizePhone($mobile);
        if (strlen($mobile) < 12) {
            return ['success' => false, 'error' => 'Invalid phone number: ' . $mobile];
        }

        $payload = [
            'apikey' => $this->apikey,
            'partnerID' => $this->partnerId,
            'message' => $message,
            'shortcode' => $this->shortcode,
            'mobile' => $mobile,
        ];
        $maxAttempts = max(1, (int) config('advanta.send_max_attempts', 3));
        $retryDelayMs = max(100, (int) config('advanta.send_retry_delay_ms', 500));

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = Http::withOptions(['connect_timeout' => max(2, (int) config('advanta.connect_timeout', 5))])
                    ->timeout(max(5, (int) config('advanta.http_timeout', 15)))
                    ->asForm()
                    ->post($this->apiUrl . '/', $payload);

                $body = $response->json() ?? $response->body();

                if ($response->successful()) {
                    $r0 = is_array($body) ? ($body['responses'][0] ?? null) : null;
                    $messageId = is_array($r0)
                        ? trim((string) ($r0['messageid'] ?? $r0['messageId'] ?? $r0['message_id'] ?? ''))
                        : '';
                    $desc = is_array($r0)
                        ? (string) ($r0['response-description'] ?? $r0['response_description'] ?? $r0['status'] ?? '')
                        : '';
                    $code = is_array($r0) ? ($r0['response-code'] ?? $r0['response_code'] ?? null) : null;
                    $success = $messageId !== ''
                        || $desc === 'Success'
                        || $code === 200
                        || (is_array($body) && ($body['status'] ?? '') === 'Success');
                    if ($success && $messageId === '' && is_array($r0)) {
                        Log::warning('AdvantaSmsService::send accepted without messageid', [
                            'mobile' => $mobile,
                            'body' => $body,
                        ]);
                    }

                    return [
                        'success' => $success,
                        'response' => $body,
                        'advanta_message_id' => $messageId !== '' ? $messageId : null,
                        'error' => $success ? null : $this->extractErrorMessage($body),
                    ];
                }

                Log::warning('AdvantaSmsService::send failed', [
                    'status' => $response->status(),
                    'body' => $body,
                    'mobile' => $mobile,
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                ]);

                $status = $response->status();
                $retryable = in_array($status, [408, 429, 500, 502, 503, 504], true);
                if ($retryable && $attempt < $maxAttempts) {
                    usleep($retryDelayMs * 1000);

                    continue;
                }

                $err = $this->extractErrorMessage($body);

                return ['success' => false, 'error' => $err];
            } catch (Throwable $e) {
                Log::error('AdvantaSmsService::send exception', [
                    'message' => $e->getMessage(),
                    'mobile' => $mobile,
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                ]);
                if ($attempt < $maxAttempts) {
                    usleep($retryDelayMs * 1000);

                    continue;
                }

                return ['success' => false, 'error' => $e->getMessage()];
            }
        }

        return ['success' => false, 'error' => 'Advanta SMS send exhausted retries.'];
    }

    /**
     * Fetch delivery report (DLR) for a message — same data as Advanta portal Status column.
     *
     * @return array{ok: bool, body: ?array, error: ?string, label: ?string, delivery_status: ?string, delivered_at: ?\Carbon\Carbon}
     */
    public function fetchDeliveryReport(string $messageId): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'body' => null, 'error' => 'Advanta not configured', 'label' => null, 'delivery_status' => null, 'delivered_at' => null];
        }

        $messageId = trim($messageId);
        if ($messageId === '') {
            return ['ok' => false, 'body' => null, 'error' => 'messageID required', 'label' => null, 'delivery_status' => null, 'delivered_at' => null];
        }

        try {
            $response = Http::withOptions(['connect_timeout' => max(2, (int) config('advanta.connect_timeout', 5))])
                ->timeout(max(5, (int) config('advanta.http_timeout', 15)))
                ->asJson()
                ->post(rtrim((string) config('advanta.dlr_url'), '/') . '/', [
                    'apikey' => $this->apikey,
                    'partnerID' => $this->partnerId,
                    'messageID' => $messageId,
                ]);

            $body = $response->json();
            if (! is_array($body)) {
                return ['ok' => false, 'body' => null, 'error' => 'Invalid DLR response', 'label' => null, 'delivery_status' => null, 'delivered_at' => null];
            }

            $code = (int) ($body['response-code'] ?? $body['response_code'] ?? 0);
            if ($code === 1008) {
                return ['ok' => false, 'body' => $body, 'error' => 'No delivery report for this message (Advanta 1008)', 'label' => null, 'delivery_status' => null, 'delivered_at' => null];
            }

            if (! $response->successful() && $code !== 200) {
                $desc = (string) ($body['response-description'] ?? $body['response_description'] ?? 'DLR HTTP ' . $response->status());

                return ['ok' => false, 'body' => $body, 'error' => $desc, 'label' => null, 'delivery_status' => null, 'delivered_at' => null];
            }

            $mapped = $this->mapDeliveryFromDlr($body);

            return [
                'ok' => true,
                'body' => $body,
                'error' => null,
                'label' => $mapped['label'],
                'delivery_status' => $mapped['delivery_status'],
                'delivered_at' => $mapped['delivered_at'],
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'body' => null, 'error' => $e->getMessage(), 'label' => null, 'delivery_status' => null, 'delivered_at' => null];
        }
    }

    /**
     * Map Advanta getdlr JSON to CRM delivery fields (portal labels like Success, Blacklisted).
     *
     * @return array{label: string, delivery_status: string, delivered_at: ?\Carbon\Carbon}
     */
    public function mapDeliveryFromDlr(array $body): array
    {
        $desc = strtolower(trim((string) (
            $body['delivery-description']
            ?? $body['delivery_description']
            ?? $body['response-description']
            ?? $body['response_description']
            ?? ''
        )));
        $statusCode = (int) ($body['delivery-status'] ?? $body['delivery_status'] ?? 0);
        $timeRaw = $body['delivery-time'] ?? $body['delivery_time'] ?? null;

        $label = 'Unknown';
        $crmStatus = 'submitted';

        if (str_contains($desc, 'blacklist')) {
            $label = 'Blacklisted';
            $crmStatus = 'blacklisted';
        } elseif (str_contains($desc, 'delivered') || $statusCode === 32 || $desc === 'success') {
            $label = 'Success';
            $crmStatus = 'delivered';
        } elseif (str_contains($desc, 'fail') || str_contains($desc, 'reject') || str_contains($desc, 'invalid')) {
            $label = ucfirst($desc) ?: 'Failed';
            $crmStatus = 'failed';
        } elseif ($desc !== '') {
            $label = ucwords(str_replace(['_', '-'], ' ', $desc));
            $crmStatus = 'submitted';
        }

        $deliveredAt = null;
        if ($timeRaw && $crmStatus === 'delivered') {
            try {
                $deliveredAt = \Carbon\Carbon::parse($timeRaw);
            } catch (\Throwable) {
                $deliveredAt = null;
            }
        }

        return [
            'label' => $label,
            'delivery_status' => $crmStatus,
            'delivered_at' => $deliveredAt,
        ];
    }

    public static function extractMessageIdFromSendResponse(mixed $providerResponse): ?string
    {
        if (! is_array($providerResponse)) {
            return null;
        }
        $r0 = $providerResponse['responses'][0] ?? null;
        if (! is_array($r0)) {
            return null;
        }
        $id = trim((string) ($r0['messageid'] ?? $r0['messageId'] ?? ''));

        return $id !== '' ? $id : null;
    }

    /**
     * Send SMS to multiple recipients (one API call per number; Advanta limits bulk to 20).
     */
    public function sendBulk(array $mobiles, string $message): array
    {
        $results = [];
        foreach ($mobiles as $mobile) {
            $mobile = trim($mobile);
            if ($mobile === '') {
                continue;
            }
            $results[] = array_merge($this->send($mobile, $message), ['mobile' => $mobile]);
        }
        return $results;
    }
}
