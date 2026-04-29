<?php

namespace App\Services;

use App\Models\PbxCall;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PbxCallService
{
    public function __construct(
        protected PbxConfigService $pbxConfig
    ) {}

    /**
     * Get PBX calls for a contact (matched by phone/mobile).
     *
     * @param  object  $contact  Contact with mobile, phone
     * @param  int  $limit
     * @param  int  $offset
     * @return array{calls: \Illuminate\Support\Collection, total: int, from_vtiger: bool}
     */
    public function getCallsForContact(object $contact, int $limit = 50, int $offset = 0): array
    {
        $this->maybeSyncRecentCdr();

        $phones = $this->getContactPhoneNumbers($contact);
        if (empty($phones)) {
            return [
                'calls' => collect(),
                'total' => 0,
                'from_vtiger' => ! $this->shouldPreferLocalLogs() && $this->pbxConfig->isConfigured(),
            ];
        }

        if ($this->pbxConfig->isConfigured() && ! $this->shouldPreferLocalLogs()) {
            return $this->getCallsFromVtiger($phones, $limit, $offset);
        }

        return $this->getCallsFromLocal($phones, $limit, $offset);
    }

    /**
     * Prefer local logs once they exist, to avoid stale vtiger dependency.
     */
    protected function shouldPreferLocalLogs(): bool
    {
        try {
            return PbxCall::query()->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Fallback sync for contact call history pages when scheduler is not running.
     */
    protected function maybeSyncRecentCdr(): void
    {
        if (! filter_var((string) env('PBX_CDR_SYNC_ENABLED', false), FILTER_VALIDATE_BOOL)) {
            return;
        }

        try {
            $latest = PbxCall::query()->max('start_time');
            if ($latest && now()->diffInSeconds(\Illuminate\Support\Carbon::parse($latest)) < 70) {
                return;
            }
        } catch (\Throwable) {
            // Continue best-effort sync.
        }

        $throttleKey = 'pbx:auto-sync:last-run';
        $lockKey = 'pbx:auto-sync:lock';
        $lock = Cache::lock($lockKey, 25);
        if (! $lock->get()) {
            return;
        }

        try {
            $lastRun = (int) Cache::get($throttleKey, 0);
            if ((time() - $lastRun) < 45) {
                return;
            }

            Artisan::call('pbx:sync-cdr', [
                '--minutes' => 30,
                '--limit' => 250,
            ]);
            Cache::put($throttleKey, time(), now()->addMinutes(10));
        } catch (\Throwable) {
            // Silent fallback: call pages should still load even if sync fails.
        } finally {
            optional($lock)->release();
        }
    }

    protected function getContactPhoneNumbers(object $contact): array
    {
        $mobile = preg_replace('/\D/', '', (string) ($contact->mobile ?? ''));
        $phone = preg_replace('/\D/', '', (string) ($contact->phone ?? ''));

        $phones = array_filter(array_unique([$mobile, $phone]), fn ($p) => strlen($p) >= 6);
        if (empty($phones)) {
            return [];
        }

        // Also add variants: last 9 digits (Kenya) for matching
        $variants = [];
        foreach ($phones as $p) {
            $variants[] = $p;
            if (strlen($p) > 9) {
                $variants[] = substr($p, -9);
            }
        }

        return array_values(array_unique(array_filter($variants, fn ($v) => strlen($v) >= 6)));
    }

    protected function getCallsFromVtiger(array $phonePatterns, int $limit, int $offset): array
    {
        $query = DB::connection('vtiger')
            ->table('vtiger_pbxmanager as p')
            ->leftJoin('vtiger_users as u', 'p.user', '=', 'u.id')
            ->select(
                'p.pbxmanagerid',
                'p.direction',
                'p.callstatus as call_status',
                'p.customernumber as customer_number',
                'p.customer as customer_name',
                'p.recordingurl as recording_url',
                'p.sourceuuid',
                'p.starttime as start_time',
                DB::raw('COALESCE(p.billduration, p.totalduration, 0) as duration_sec'),
                DB::raw("CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) as user_name")
            );

        $query->where(function ($q) use ($phonePatterns) {
            foreach ($phonePatterns as $pattern) {
                $like = '%' . $pattern . '%';
                $q->orWhere('p.customernumber', 'like', $like);
            }
        });

        $query->orderByDesc('p.starttime');
        $total = $query->count();
        $rows = $query->skip($offset)->take($limit)->get();

        $calls = $rows->map(fn ($r) => $this->toCallDto($r, true));

        return [
            'calls' => $calls,
            'total' => $total,
            'from_vtiger' => true,
        ];
    }

    protected function getCallsFromLocal(array $phonePatterns, int $limit, int $offset): array
    {
        $query = PbxCall::query();

        $query->where(function ($q) use ($phonePatterns) {
            foreach ($phonePatterns as $pattern) {
                $like = '%' . $pattern . '%';
                $q->orWhere('customer_number', 'like', $like);
            }
        });

        $total = $query->count();
        $rows = $query->orderByDesc('start_time')->skip($offset)->take($limit)->get();

        $calls = $rows->map(fn ($r) => (object) [
            'id' => $r->id,
            'call_status' => $r->call_status,
            'direction' => $r->direction,
            'customer_number' => $r->customer_number,
            'reason_for_calling' => $r->reason_for_calling,
            'customer_name' => $r->customer_name,
            'user_name' => $r->user_name,
            'recording_url' => $r->recording_url,
            'recording_path' => $r->recording_path,
            'duration_sec' => (int) $r->duration_sec,
            'start_time' => $r->start_time,
            'from_vtiger' => false,
        ]);

        return [
            'calls' => $calls,
            'total' => $total,
            'from_vtiger' => false,
        ];
    }

    protected function toCallDto(object $row, bool $fromVtiger): object
    {
        $recordingUrl = $row->recording_url ?? null;
        if (! $recordingUrl && ! empty($row->sourceuuid ?? null) && $fromVtiger) {
            $recordingUrl = $this->pbxConfig->getRecordingUrl($row->sourceuuid);
        }

        return (object) [
            'id' => $row->pbxmanagerid ?? $row->id ?? null,
            'call_status' => $row->call_status ?? null,
            'direction' => $row->direction ?? null,
            'customer_number' => $row->customer_number ?? null,
            'reason_for_calling' => $row->reason_for_calling ?? null,
            'customer_name' => $row->customer_name ?? null,
            'user_name' => trim($row->user_name ?? '') ?: null,
            'recording_url' => $recordingUrl,
            'recording_path' => $row->recording_path ?? null,
            'duration_sec' => (int) ($row->duration_sec ?? 0),
            'start_time' => $row->start_time ? \Carbon\Carbon::parse($row->start_time) : null,
            'from_vtiger' => $fromVtiger,
        ];
    }
}
