<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InvestmentMaturityService
{
    public function __construct(protected ErpClientService $erpClientService) {}

    /**
     * @return Collection<int, object>
     */
    public function dueWithinDays(int $days = 14): Collection
    {
        $days = max(1, min(90, $days));
        $cacheKey = 'investment_maturities_due_' . $days;

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($days) {
            if ($this->canUseDirectOracle()) {
                return $this->dueWithinDaysOracle($days);
            }

            return $this->dueWithinDaysHttp($days);
        });
    }

    public function notificationsTableExists(): bool
    {
        return Schema::hasTable('investment_maturity_notifications');
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    public function withNotificationStatus(Collection $rows, string $recipientEmail): Collection
    {
        if ($rows->isEmpty() || ! $this->notificationsTableExists()) {
            return $rows->map(function ($row) {
                $row->email_sent = false;
                $row->email_sent_at = null;

                return $row;
            });
        }

        $recipientEmail = strtolower(trim($recipientEmail));

        $policyNos = $rows->map(fn ($r) => trim((string) ($r->pol_policy_no ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $sentMap = DB::table('investment_maturity_notifications')
            ->whereIn('policy_no', $policyNos)
            ->whereRaw('LOWER(recipient_email) = ?', [$recipientEmail])
            ->get()
            ->keyBy(function ($row) {
                return $this->notificationKey((string) $row->policy_no, (string) $row->maturity_date);
            });

        return $rows->map(function ($row) use ($sentMap) {
            $policyNo = trim((string) ($row->pol_policy_no ?? ''));
            $maturityDate = $this->normalizeDate((string) ($row->pol_maturity_date ?? ''));
            $key = $this->notificationKey($policyNo, $maturityDate);
            $sent = $sentMap->get($key);

            $row->email_sent = $sent !== null;
            $row->email_sent_at = $sent?->sent_at;

            return $row;
        });
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    public function unsentRows(Collection $rows, string $recipientEmail): Collection
    {
        $annotated = $this->withNotificationStatus($rows, $recipientEmail);

        return $annotated->filter(fn ($row) => ! (bool) ($row->email_sent ?? false))->values();
    }

    /**
     * @param  Collection<int, object>  $sentRows
     */
    public function markAsSent(Collection $sentRows, string $recipientEmail, ?string $ccEmail): void
    {
        if ($sentRows->isEmpty() || ! $this->notificationsTableExists()) {
            return;
        }

        $now = now();
        $recipientEmail = strtolower(trim($recipientEmail));
        $ccEmail = $ccEmail !== null ? strtolower(trim($ccEmail)) : null;
        $payload = [];

        foreach ($sentRows as $row) {
            $policyNo = trim((string) ($row->pol_policy_no ?? ''));
            $maturityDate = $this->normalizeDate((string) ($row->pol_maturity_date ?? ''));
            if ($policyNo === '' || $maturityDate === '') {
                continue;
            }

            $payload[] = [
                'policy_no' => $policyNo,
                'maturity_date' => $maturityDate,
                'recipient_email' => $recipientEmail,
                'cc_email' => $ccEmail,
                'sent_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($payload === []) {
            return;
        }

        DB::table('investment_maturity_notifications')->upsert(
            $payload,
            ['policy_no', 'maturity_date', 'recipient_email'],
            ['cc_email', 'sent_at', 'updated_at']
        );
    }

    private function normalizeDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return '';
        }
    }

    private function notificationKey(string $policyNo, string $maturityDate): string
    {
        return trim($policyNo) . '|' . trim($maturityDate);
    }

    private function canUseDirectOracle(): bool
    {
        return extension_loaded('oci8') && function_exists('oci_connect');
    }

    /**
     * @return Collection<int, object>
     */
    private function dueWithinDaysOracle(int $days): Collection
    {
        $today = now()->startOfDay()->toDateString();
        $toDate = now()->startOfDay()->addDays($days)->toDateString();

        // NOTE: keep p.pol_maturity_date "bare" (no TRUNC) so Oracle can use an index
        // on the column. Wrapping it in TRUNC() forces a full table scan of lms_policies,
        // which is slow and a common trigger for ORA-03113 (session killed mid-query).
        // The half-open range [today, toDate+1) reproduces the inclusive BETWEEN behaviour.
        $rows = DB::connection('erp')->select(
            "SELECT
                p.pol_policy_no,
                p.pol_maturity_date,
                r.prp_surname || ' ' || r.prp_other_names AS full_name,
                d.prod_desc AS product
             FROM lms_policies p
             JOIN lms_proposers r ON r.prp_code = p.pol_prp_code
             JOIN lms_products d ON d.prod_code = p.pol_prod_code
             WHERE d.prod_code IN (2024608, 2025615, 2025621)
               AND p.pol_maturity_date >= TO_DATE(?, 'YYYY-MM-DD')
               AND p.pol_maturity_date < TO_DATE(?, 'YYYY-MM-DD') + 1
             ORDER BY p.pol_maturity_date ASC, p.pol_policy_no ASC",
            [$today, $toDate]
        );

        return collect($rows);
    }

    /**
     * HTTP fallback when PHP OCI8 is unavailable on the web server.
     *
     * @return Collection<int, object>
     */
    private function dueWithinDaysHttp(int $days): Collection
    {
        $from = now()->startOfDay()->toDateString();
        $to = now()->startOfDay()->addDays($days)->toDateString();

        // Fast path: use dedicated maturities endpoint.
        $result = $this->erpClientService->getMaturingPoliciesFromHttpApi($from, $to, null);
        if (empty($result['error'])) {
            return $this->mapAndFilterRows(collect((array) ($result['data'] ?? [])), $from, $to);
        }

        // Fallback path: pull clients in batches and filter locally.
        // Keep strict caps to protect request latency.
        $all = collect();
        $limit = 100;
        $maxBatches = 20;

        for ($offset = 0; $offset < $maxBatches * $limit; $offset += $limit) {
            $res = $this->erpClientService->getClientsFromHttpApi($limit, $offset, null, 30, false, 'individual');
            if (! empty($res['error'])) {
                break;
            }

            $chunk = $res['data'] instanceof Collection
                ? $res['data']
                : collect((array) ($res['data'] ?? []));

            if ($chunk->isEmpty()) {
                break;
            }

            $all = $all->merge($chunk);

            if ($chunk->count() < $limit) {
                break;
            }
        }

        return $this->mapAndFilterRows($all, $from, $to);
    }

    /**
     * @param  Collection<int, mixed>  $rawRows
     * @return Collection<int, object>
     */
    private function mapAndFilterRows(Collection $rawRows, string $from, string $to): Collection
    {
        /** @var array<int, string> $allowedProducts */
        $allowedProducts = array_values(array_filter(array_map(
            fn ($v) => trim((string) $v),
            (array) config('maturities.investment_notifications.products', [])
        )));
        $allowedMap = [];
        foreach ($allowedProducts as $product) {
            $allowedMap[strtolower($product)] = true;
        }

        $rows = $rawRows->map(function ($row) {
            $r = is_array($row) ? (object) $row : $row;

            return (object) [
                'pol_policy_no' => $r->policy_number ?? $r->policy_no ?? null,
                'pol_maturity_date' => $r->maturity ?? $r->maturity_date ?? null,
                'full_name' => $r->life_assured ?? $r->life_assur ?? null,
                'product' => $r->product ?? null,
            ];
        });

        $rows = $rows->filter(function ($row) use ($from, $to, $allowedMap) {
            $m = trim((string) ($row->pol_maturity_date ?? ''));
            if ($m === '') {
                return false;
            }
            try {
                $maturityDate = \Carbon\Carbon::parse($m)->toDateString();
            } catch (\Throwable) {
                return false;
            }
            if ($maturityDate < $from || $maturityDate > $to) {
                return false;
            }

            if ($allowedMap !== []) {
                $product = strtolower(trim((string) ($row->product ?? '')));

                return $product !== '' && isset($allowedMap[$product]);
            }

            return true;
        })->values();

        return $rows
            ->sortBy([
                ['pol_maturity_date', 'asc'],
                ['pol_policy_no', 'asc'],
            ])
            ->values();
    }
}

