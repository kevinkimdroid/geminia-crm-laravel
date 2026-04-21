<?php

namespace App\Services;

use App\Models\SmsLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MassBroadcastService
{
    public function __construct(
        protected CrmService $crm,
        protected ErpClientService $erp,
        protected PlainTextMailSender $mailSender,
        protected AdvantaSmsService $sms,
        protected BroadcastSendHistoryService $sendHistory,
    ) {}

    public function personalize(string $template, object $contact, array $contextTokens = []): string
    {
        $first = trim($contact->firstname ?? '');
        $last = trim($contact->lastname ?? '');
        $name = trim($first . ' ' . $last);
        $email = trim($contact->email ?? '');

        // Support CRM email-template style placeholders and common variants.
        $search = [
            '{{first_name}}', '{{firstname}}', '{{FIRST_NAME}}',
            '{{last_name}}', '{{lastname}}', '{{LAST_NAME}}',
            '{{name}}', '{{email}}',
        ];
        $replace = [
            $first, $first, $first,
            $last, $last, $last,
            $name, $email,
        ];

        $out = str_replace($search, $replace, $template);

        if ($contextTokens !== []) {
            $extraSearch = [];
            $extraReplace = [];
            foreach ($contextTokens as $key => $value) {
                $keyNorm = trim((string) $key);
                if ($keyNorm === '') {
                    continue;
                }
                $tokenValue = trim((string) $value);
                $extraSearch[] = '{{' . $keyNorm . '}}';
                $extraReplace[] = $tokenValue;
                $extraSearch[] = '{{' . strtoupper($keyNorm) . '}}';
                $extraReplace[] = $tokenValue;
            }
            if ($extraSearch !== []) {
                $out = str_replace($extraSearch, $extraReplace, $out);
            }
        }

        return $out;
    }

    protected function firstValidEmailCandidate(object $contact): ?string
    {
        $candidates = [
            trim((string) ($contact->email ?? '')),
            trim((string) ($contact->otheremail ?? '')),
            trim((string) ($contact->secondaryemail ?? '')),
        ];
        foreach ($candidates as $email) {
            if ($email === '') {
                continue;
            }
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
            $parts = preg_split('/[;,\\s]+/', $email, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($parts as $part) {
                $candidate = trim((string) $part);
                if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string,mixed>  $row
     */
    protected function extractEmailFromPolicyRow(array $row): ?string
    {
        $keys = [
            'email_adr', 'EMAIL_ADR', 'emailAdr',
            'client_email', 'CLIENT_EMAIL',
            'mem_email', 'MEM_EMAIL',
            'email', 'EMAIL',
        ];
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }
            $raw = trim((string) ($row[$key] ?? ''));
            if ($raw === '') {
                continue;
            }
            if (filter_var($raw, FILTER_VALIDATE_EMAIL)) {
                return $raw;
            }
            $parts = preg_split('/[;,\\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($parts as $part) {
                $candidate = trim((string) $part);
                if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                    return $candidate;
                }
            }
        }
        foreach ($row as $k => $v) {
            if (! is_scalar($v) || ! str_contains(strtolower((string) $k), 'email')) {
                continue;
            }
            $candidate = trim((string) $v);
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array{sent: int, failed: int, skipped_no_email: int, duplicate_emails_skipped: int, skipped_recent: int, failure_summary?: string}
     */
    public function sendMassEmail(
        array $contactIds,
        string $subjectTemplate,
        string $bodyTemplate,
        ?array $attachment = null,
        bool $skipRecentSends = true,
        array $emailOverrides = [],
        array $fileEmailRecipients = [],
        array $contextTokens = [],
    ): array {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $contacts = $this->crm->getContactsByIds($contactIds);
        $policyByContact = $this->crm->getContactPolicyNumbersByIds($contactIds);
        $policyEmailCache = [];
        $policyDetailCacheSeconds = max(60, (int) config('mass_broadcast.erp_policy_detail_cache_seconds', 900));
        $delayMs = max(0, (int) config('mass_broadcast.email_throttle_ms', 120));
        if (count($contactIds) >= 300) {
            // Large campaigns should not be throttled as aggressively.
            $delayMs = min($delayMs, max(0, (int) config('mass_broadcast.email_throttle_ms_bulk', 25)));
        }
        $skipDays = (int) config('mass_broadcast.skip_recent_days', 14);
        $userId = Auth::guard('vtiger')->id();

        $sent = 0;
        $failed = 0;
        $skippedNoEmail = 0;
        $duplicateEmailsSkipped = 0;
        $skippedRecent = 0;
        $failureReasons = [];

        $recentBlock = [];
        if ($skipRecentSends && $skipDays > 0 && $this->sendHistory->tableReady()) {
            $recentBlock = array_flip($this->sendHistory->contactIdsWithRecentSend($contactIds, 'email', $skipDays));
        }

        $byEmail = [];
        foreach ($contacts as $c) {
            $cid = (int) ($c->contactid ?? 0);
            $overrideEmail = trim((string) ($emailOverrides[$cid] ?? ''));
            $raw = $overrideEmail !== '' && filter_var($overrideEmail, FILTER_VALIDATE_EMAIL)
                ? $overrideEmail
                : $this->firstValidEmailCandidate($c);
            if ($raw === null) {
                $policy = trim((string) ($policyByContact[$cid] ?? ''));
                if ($policy !== '') {
                    if (! array_key_exists($policy, $policyEmailCache)) {
                        $email = null;
                        try {
                            $cacheKey = 'broadcast:erp-policy-detail:' . md5(strtoupper($policy));
                            $detail = Cache::remember(
                                $cacheKey,
                                now()->addSeconds($policyDetailCacheSeconds),
                                fn () => $this->erp->getPolicyDetails($policy)
                            );
                            if (is_array($detail)) {
                                $email = $this->extractEmailFromPolicyRow($detail);
                            }
                        } catch (\Throwable $ignored) {
                            $email = null;
                        }
                        if ($email === null && config('erp.clients_view_source') === 'erp_http') {
                            $baseUrl = trim((string) config('erp.clients_http_url', ''));
                            if ($baseUrl !== '') {
                                try {
                                    $debugUrl = preg_replace('#/clients/?$#i', '/clients/debug', $baseUrl) ?: $baseUrl;
                                    $dbg = Http::timeout(8)->get($debugUrl, ['policy' => $policy, 'raw' => 1]);
                                    if ($dbg->successful()) {
                                        $body = $dbg->json();
                                        $raw = is_array($body['raw_non_null'] ?? null) ? $body['raw_non_null'] : [];
                                        $email = $this->extractEmailFromPolicyRow($raw);
                                    }
                                } catch (\Throwable $ignored) {
                                    $email = null;
                                }
                            }
                        }
                        $policyEmailCache[$policy] = $email;
                    }
                    $raw = $policyEmailCache[$policy] ?? null;
                }
            }
            if ($raw === null) {
                $skippedNoEmail++;

                continue;
            }
            $key = strtolower($raw);
            if (! isset($byEmail[$key])) {
                $byEmail[$key] = [
                    'contact' => $c,
                    'email' => $raw,
                ];
            } else {
                $duplicateEmailsSkipped++;
            }
        }

        foreach ($fileEmailRecipients as $recipient) {
            if (! is_array($recipient)) {
                continue;
            }
            $email = trim((string) ($recipient['email'] ?? ''));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skippedNoEmail++;
                continue;
            }
            $firstName = trim((string) ($recipient['first_name'] ?? ''));
            $lastName = trim((string) ($recipient['last_name'] ?? ''));
            $name = trim((string) ($recipient['name'] ?? ''));
            if ($name !== '' && $firstName === '' && $lastName === '') {
                $parts = preg_split('/\s+/', $name, 2, PREG_SPLIT_NO_EMPTY) ?: [];
                $firstName = trim((string) ($parts[0] ?? ''));
                $lastName = trim((string) ($parts[1] ?? ''));
            }

            $contact = (object) [
                'contactid' => 0,
                'firstname' => $firstName,
                'lastname' => $lastName,
                'email' => $email,
            ];
            $key = strtolower($email);
            if (! isset($byEmail[$key])) {
                $byEmail[$key] = [
                    'contact' => $contact,
                    'email' => $email,
                ];
            } else {
                $duplicateEmailsSkipped++;
            }
        }

        foreach ($byEmail as $entry) {
            @set_time_limit(30);
            $contact = $entry['contact'];
            $resolvedTo = (string) ($entry['email'] ?? '');
            $cid = (int) $contact->contactid;
            if (isset($recentBlock[$cid])) {
                $skippedRecent++;

                continue;
            }

            $subject = $this->personalize($subjectTemplate, $contact, $contextTokens);
            $body = $this->personalize($bodyTemplate, $contact, $contextTokens);
            $to = $resolvedTo;
            if ($to === '') {
                // Safety: should not happen since key was built from resolved email.
                $failed++;
                Log::warning('MassBroadcastService: empty resolved recipient email', ['contact_id' => $cid]);
                continue;
            }
            $toName = trim(($contact->firstname ?? '') . ' ' . ($contact->lastname ?? '')) ?: null;

            $attachments = $attachment ? [$attachment] : [];
            if ($this->mailSender->send($to, $toName, $subject, $body, $attachments)) {
                $sent++;
                if ($cid > 0) {
                    $this->sendHistory->recordSuccessfulEmail(
                        $cid,
                        $subjectTemplate,
                        $bodyTemplate,
                        $userId ? (int) $userId : null
                    );
                }
            } else {
                $failed++;
                $reason = trim((string) ($this->mailSender->getLastError() ?? ''));
                if ($reason === '') {
                    $reason = 'Unknown send error';
                }
                $failureReasons[$reason] = ($failureReasons[$reason] ?? 0) + 1;
                Log::warning('MassBroadcastService: email failed', [
                    'contact_id' => $contact->contactid,
                    'to' => $to,
                    'reason' => $reason,
                ]);
            }

            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }

        $failureSummary = null;
        if ($failureReasons !== []) {
            arsort($failureReasons);
            $top = array_slice($failureReasons, 0, 3, true);
            $parts = [];
            foreach ($top as $reason => $count) {
                $parts[] = $reason . ' (' . $count . ')';
            }
            $failureSummary = implode('; ', $parts);
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'skipped_no_email' => $skippedNoEmail,
            'duplicate_emails_skipped' => $duplicateEmailsSkipped,
            'skipped_recent' => $skippedRecent,
            'failure_summary' => $failureSummary,
        ];
    }

    /**
     * @return array{sent: int, failed: int, skipped_no_phone: int, duplicate_phones_skipped: int, skipped_recent: int, not_configured?: bool}
     */
    public function sendMassSms(
        array $contactIds,
        string $message,
        bool $skipRecentSends = true,
    ): array {
        if (! $this->sms->isConfigured()) {
            return [
                'sent' => 0,
                'failed' => 0,
                'skipped_no_phone' => 0,
                'duplicate_phones_skipped' => 0,
                'skipped_recent' => 0,
                'not_configured' => true,
            ];
        }

        $contacts = $this->crm->getContactsByIds($contactIds);
        $userId = Auth::guard('vtiger')->id();
        $skipDays = (int) config('mass_broadcast.skip_recent_days', 14);

        $sent = 0;
        $failed = 0;
        $skippedNoPhone = 0;
        $duplicatePhonesSkipped = 0;
        $skippedRecent = 0;

        $recentBlock = [];
        if ($skipRecentSends && $skipDays > 0 && $this->sendHistory->tableReady()) {
            $recentBlock = array_flip($this->sendHistory->contactIdsWithRecentSend($contactIds, 'sms', $skipDays));
        }

        $seenPhones = [];
        foreach ($contacts as $c) {
            $raw = trim((string) ($c->mobile ?? '') ?: (string) ($c->phone ?? ''));
            if ($raw === '') {
                $skippedNoPhone++;

                continue;
            }
            $normalized = $this->sms->normalizePhone($raw);
            if (isset($seenPhones[$normalized])) {
                $duplicatePhonesSkipped++;

                continue;
            }
            $seenPhones[$normalized] = $c;
        }

        foreach ($seenPhones as $normalized => $contact) {
            $cid = (int) $contact->contactid;
            if (isset($recentBlock[$cid])) {
                $skippedRecent++;

                continue;
            }

            $text = $this->personalize($message, $contact);
            $result = $this->sms->send($normalized, $text);
            $ok = (bool) ($result['success'] ?? false);
            if ($ok) {
                $sent++;
                $this->sendHistory->recordSuccessfulSms($cid, $text, $userId ? (int) $userId : null);
            } else {
                $failed++;
            }

            try {
                SmsLog::create([
                    'contact_id' => $contact->contactid,
                    'phone' => $normalized,
                    'message' => $text,
                    'status' => $ok ? 'sent' : 'failed',
                    'error_message' => $ok ? null : ($result['error'] ?? 'Unknown error'),
                    'user_id' => $userId,
                    'sent_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('MassBroadcastService: SmsLog failed', ['error' => $e->getMessage()]);
            }

            $delayMs = max(0, (int) config('mass_broadcast.sms_throttle_ms', 80));
            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'skipped_no_phone' => $skippedNoPhone,
            'duplicate_phones_skipped' => $duplicatePhonesSkipped,
            'skipped_recent' => $skippedRecent,
            'not_configured' => false,
        ];
    }
}
