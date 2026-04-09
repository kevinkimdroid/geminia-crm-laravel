<?php

namespace App\Services;

use App\Models\MassBroadcastSend;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class BroadcastSendHistoryService
{
    public function tableReady(): bool
    {
        return Schema::hasTable('mass_broadcast_sends');
    }

    public function digestEmail(string $subjectTemplate, string $bodyTemplate): string
    {
        $norm = "email\0" . trim($subjectTemplate) . "\0" . trim($bodyTemplate);

        return hash('sha256', $norm);
    }

    public function digestSms(string $messageTemplate): string
    {
        return hash('sha256', "sms\0" . trim($messageTemplate));
    }

    public function recordSuccessfulEmail(int $contactId, string $subjectTemplate, string $bodyTemplate, ?int $userId): void
    {
        if (! $this->tableReady() || $contactId <= 0) {
            return;
        }

        MassBroadcastSend::create([
            'contact_id' => $contactId,
            'channel' => 'email',
            'content_digest' => $this->digestEmail($subjectTemplate, $bodyTemplate),
            'subject_snapshot' => mb_substr(trim($subjectTemplate), 0, 200) ?: null,
            'user_id' => $userId,
            'sent_at' => now(),
        ]);
    }

    public function recordSuccessfulSms(int $contactId, string $messageTemplate, ?int $userId): void
    {
        if (! $this->tableReady() || $contactId <= 0) {
            return;
        }

        MassBroadcastSend::create([
            'contact_id' => $contactId,
            'channel' => 'sms',
            'content_digest' => $this->digestSms($messageTemplate),
            'subject_snapshot' => null,
            'user_id' => $userId,
            'sent_at' => now(),
        ]);
    }

    /**
     * Contact IDs in $contactIds that already have a successful send on $channel within $days.
     *
     * @param  list<int>  $contactIds
     * @return list<int>
     */
    public function contactIdsWithRecentSend(array $contactIds, string $channel, int $days): array
    {
        if (! $this->tableReady() || $contactIds === [] || $days <= 0) {
            return [];
        }

        $contactIds = array_values(array_unique(array_filter(array_map('intval', $contactIds))));
        if ($contactIds === []) {
            return [];
        }

        $since = now()->subDays($days);

        return MassBroadcastSend::query()
            ->where('channel', $channel)
            ->whereIn('contact_id', $contactIds)
            ->where('sent_at', '>=', $since)
            ->distinct()
            ->pluck('contact_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $contactIds
     * @return array<int, array{email: ?Carbon, sms: ?Carbon}>
     */
    public function lastSuccessfulSendByContact(array $contactIds): array
    {
        $out = [];
        if (! $this->tableReady() || $contactIds === []) {
            return $out;
        }

        $contactIds = array_values(array_unique(array_filter(array_map('intval', $contactIds))));
        foreach ($contactIds as $id) {
            $out[$id] = ['email' => null, 'sms' => null];
        }

        $rows = MassBroadcastSend::query()
            ->selectRaw('contact_id, channel, MAX(sent_at) as last_sent')
            ->whereIn('contact_id', $contactIds)
            ->groupBy('contact_id', 'channel')
            ->get();

        foreach ($rows as $row) {
            $cid = (int) $row->contact_id;
            $ch = (string) $row->channel;
            if (! isset($out[$cid])) {
                continue;
            }
            $dt = $row->last_sent ? Carbon::parse($row->last_sent) : null;
            if ($ch === 'email') {
                $out[$cid]['email'] = $dt;
            } elseif ($ch === 'sms') {
                $out[$cid]['sms'] = $dt;
            }
        }

        return $out;
    }

    /**
     * All contact ids with a successful send on $channel since $days ago (capped for query size).
     *
     * @return list<int>
     */
    public function allContactIdsWithRecentSend(string $channel, int $days, int $maxIds = 50000): array
    {
        if (! $this->tableReady() || $days <= 0) {
            return [];
        }

        return MassBroadcastSend::query()
            ->where('channel', $channel)
            ->where('sent_at', '>=', now()->subDays($days))
            ->distinct()
            ->limit(max(1, $maxIds))
            ->pluck('contact_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
