<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MaturityRenewalTrackingService
{
    public const STATUSES = [
        'pending' => 'Pending',
        'in_progress' => 'In progress',
        'renewed' => 'Renewed',
        'lapsed' => 'Lapsed',
        'not_renewing' => 'Not renewing',
    ];

    /**
     * Same DB as maturities_cache / erp_clients_cache (default Laravel connection).
     */
    protected function connection(): string
    {
        return (string) config('database.default');
    }

    public function tableExists(): bool
    {
        return Schema::connection($this->connection())->hasTable('maturity_renewal_tracking');
    }

    /**
     * @param  array{renewal_status: string, renewal_date?: ?string, notes?: ?string}  $data
     */
    public function upsert(string $policyNumber, string $maturityYmd, array $data, ?int $userId = null): void
    {
        if (! $this->tableExists()) {
            return;
        }

        $policyNumber = trim($policyNumber);
        $status = $data['renewal_status'] ?? 'pending';
        if (! array_key_exists($status, self::STATUSES)) {
            $status = 'pending';
        }

        $renewalDate = isset($data['renewal_date']) && $data['renewal_date'] !== '' && $data['renewal_date'] !== null
            ? \Carbon\Carbon::parse($data['renewal_date'])->format('Y-m-d')
            : null;

        $notes = isset($data['notes']) ? (string) $data['notes'] : null;
        if ($notes !== null && strlen($notes) > 5000) {
            $notes = substr($notes, 0, 5000);
        }

        $now = now();

        $payload = [
            'renewal_status' => $status,
            'renewal_date' => $renewalDate,
            'notes' => $notes,
            'updated_by_user_id' => $userId,
            'updated_at' => $now,
        ];

        $tbl = DB::connection($this->connection())->table('maturity_renewal_tracking');

        if ($tbl->where('policy_number', $policyNumber)->where('maturity', $maturityYmd)->exists()) {
            DB::connection($this->connection())->table('maturity_renewal_tracking')
                ->where('policy_number', $policyNumber)
                ->where('maturity', $maturityYmd)
                ->update($payload);
        } else {
            DB::connection($this->connection())->table('maturity_renewal_tracking')->insert(array_merge($payload, [
                'policy_number' => $policyNumber,
                'maturity' => $maturityYmd,
                'created_at' => $now,
            ]));
        }
    }
}
