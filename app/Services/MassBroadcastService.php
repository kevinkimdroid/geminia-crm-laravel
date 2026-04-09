<?php

namespace App\Services;

use App\Models\SmsLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MassBroadcastService
{
    public function __construct(
        protected CrmService $crm,
        protected PlainTextMailSender $mailSender,
        protected AdvantaSmsService $sms,
        protected BroadcastSendHistoryService $sendHistory,
    ) {}

    public function personalize(string $template, object $contact): string
    {
        $first = trim($contact->firstname ?? '');
        $last = trim($contact->lastname ?? '');
        $name = trim($first . ' ' . $last);
        $email = trim($contact->email ?? '');

        return str_replace(
            ['{{first_name}}', '{{last_name}}', '{{name}}', '{{email}}'],
            [$first, $last, $name, $email],
            $template
        );
    }

    /**
     * @return array{sent: int, failed: int, skipped_no_email: int, duplicate_emails_skipped: int, skipped_recent: int}
     */
    public function sendMassEmail(
        array $contactIds,
        string $subjectTemplate,
        string $bodyTemplate,
        bool $skipRecentSends = true,
    ): array {
        $contacts = $this->crm->getContactsByIds($contactIds);
        $delayMs = max(0, (int) config('mass_broadcast.email_throttle_ms', 120));
        $skipDays = (int) config('mass_broadcast.skip_recent_days', 14);
        $userId = Auth::guard('vtiger')->id();

        $sent = 0;
        $failed = 0;
        $skippedNoEmail = 0;
        $duplicateEmailsSkipped = 0;
        $skippedRecent = 0;

        $recentBlock = [];
        if ($skipRecentSends && $skipDays > 0 && $this->sendHistory->tableReady()) {
            $recentBlock = array_flip($this->sendHistory->contactIdsWithRecentSend($contactIds, 'email', $skipDays));
        }

        $byEmail = [];
        foreach ($contacts as $c) {
            $raw = trim((string) ($c->email ?? ''));
            if ($raw === '' || ! filter_var($raw, FILTER_VALIDATE_EMAIL)) {
                $skippedNoEmail++;

                continue;
            }
            $key = strtolower($raw);
            if (! isset($byEmail[$key])) {
                $byEmail[$key] = $c;
            } else {
                $duplicateEmailsSkipped++;
            }
        }

        foreach ($byEmail as $contact) {
            $cid = (int) $contact->contactid;
            if (isset($recentBlock[$cid])) {
                $skippedRecent++;

                continue;
            }

            $subject = $this->personalize($subjectTemplate, $contact);
            $body = $this->personalize($bodyTemplate, $contact);
            $to = trim((string) $contact->email);
            $toName = trim(($contact->firstname ?? '') . ' ' . ($contact->lastname ?? '')) ?: null;

            if ($this->mailSender->send($to, $toName, $subject, $body)) {
                $sent++;
                $this->sendHistory->recordSuccessfulEmail(
                    $cid,
                    $subjectTemplate,
                    $bodyTemplate,
                    $userId ? (int) $userId : null
                );
            } else {
                $failed++;
                Log::warning('MassBroadcastService: email failed', ['contact_id' => $contact->contactid, 'to' => $to]);
            }

            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'skipped_no_email' => $skippedNoEmail,
            'duplicate_emails_skipped' => $duplicateEmailsSkipped,
            'skipped_recent' => $skippedRecent,
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

            $result = $this->sms->send($normalized, $message);
            $ok = (bool) ($result['success'] ?? false);
            if ($ok) {
                $sent++;
                $this->sendHistory->recordSuccessfulSms($cid, $message, $userId ? (int) $userId : null);
            } else {
                $failed++;
            }

            try {
                SmsLog::create([
                    'contact_id' => $contact->contactid,
                    'phone' => $normalized,
                    'message' => $message,
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
