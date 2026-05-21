<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

        try {
            $response = Http::withOptions(['connect_timeout' => max(2, (int) config('advanta.connect_timeout', 5))])
                ->timeout(max(5, (int) config('advanta.http_timeout', 15)))
                ->asForm()
                ->post($this->apiUrl . '/', [
                'apikey' => $this->apikey,
                'partnerID' => $this->partnerId,
                'message' => $message,
                'shortcode' => $this->shortcode,
                'mobile' => $mobile,
            ]);

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
            ]);

            $err = $this->extractErrorMessage($body);
            return ['success' => false, 'error' => $err];
        } catch (\Throwable $e) {
            Log::error('AdvantaSmsService::send exception: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
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
