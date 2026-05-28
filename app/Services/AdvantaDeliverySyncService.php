<?php

namespace App\Services;

use App\Models\SmsLog;
use Illuminate\Support\Facades\Log;

class AdvantaDeliverySyncService
{
    public function __construct(protected AdvantaSmsService $advanta) {}

    /**
     * Poll Advanta getdlr for recent sent logs and update CRM delivery fields.
     *
     * @return array{checked:int, updated:int, errors:int}
     */
    public function syncRecent(int $limit = 100, int $hours = 72): array
    {
        $summary = ['checked' => 0, 'updated' => 0, 'errors' => 0];

        if (! $this->advanta->isConfigured()) {
            return $summary;
        }

        $logs = SmsLog::query()
            ->where('status', 'sent')
            ->where('sent_at', '>=', now()->subHours(max(1, $hours)))
            ->orderByDesc('sent_at')
            ->limit(max(1, min(500, $limit)))
            ->get();

        foreach ($logs as $log) {
            $messageId = trim((string) ($log->advanta_message_id ?? ''));
            if ($messageId === '') {
                $messageId = AdvantaSmsService::extractMessageIdFromSendResponse($log->provider_response) ?? '';
            }
            if ($messageId === '') {
                continue;
            }

            $summary['checked']++;
            $dlr = $this->advanta->fetchDeliveryReport($messageId);

            if (! $dlr['ok']) {
                if ($dlr['error'] && ! str_contains((string) $dlr['error'], '1008')) {
                    $summary['errors']++;
                }
                continue;
            }

            $log->advanta_message_id = $messageId;
            $log->advanta_status = $dlr['label'];
            $log->advanta_delivery_tat = is_array($dlr['body'])
                ? (string) ($dlr['body']['delivery-tat'] ?? $dlr['body']['delivery_tat'] ?? '')
                : '';
            $log->delivery_status = $dlr['delivery_status'] ?? $log->delivery_status;
            if ($dlr['delivered_at']) {
                $log->delivered_at = $dlr['delivered_at'];
            }
            if (is_array($dlr['body'])) {
                $existing = is_array($log->provider_response) ? $log->provider_response : [];
                $log->provider_response = array_merge($existing, ['dlr' => $dlr['body']]);
            }
            $log->save();
            $summary['updated']++;
        }

        Log::info('Advanta DLR sync completed', $summary);

        return $summary;
    }
}
