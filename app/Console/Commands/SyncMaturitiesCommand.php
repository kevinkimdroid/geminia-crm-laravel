<?php

namespace App\Console\Commands;

use App\Services\ErpClientService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncMaturitiesCommand extends Command
{
    protected $signature = 'maturities:sync
                            {--days=90 : Sync policies maturing within this many days (14-365)}
                            {--oracle : Use direct Oracle connection (skip API)}';

    protected $description = 'Sync maturing policies from ERP API or Oracle into maturities_cache';

    public function handle(ErpClientService $erp): int
    {
        if (! Schema::hasTable('maturities_cache')) {
            $this->error('maturities_cache table does not exist.');
            $this->line('Run: php artisan maturities:create-table');
            return self::FAILURE;
        }

        $days = max(14, min(365, (int) $this->option('days')));
        $from = now()->format('Y-m-d');
        $to = now()->addDays($days)->format('Y-m-d');

        $data = [];

        if ($this->option('oracle')) {
            $this->info("Syncing from Oracle (from {$from} to {$to})...");
            $data = $this->fetchFromOracle($from, $to);
        }

        if (empty($data)) {
            $this->info("Syncing from ERP API (from {$from} to {$to})...");
            $result = $erp->getMaturingPoliciesFromHttpApi($from, $to, null);
            if (! empty($result['error'])) {
                $this->warn('API error: ' . $result['error'] . ' — trying Oracle...');
                $data = $this->fetchFromOracle($from, $to);
            } else {
                $data = $result['data'] ?? [];
            }
        }

        if (empty($data)) {
            $this->warn('No maturing policies found. Try: maturities:sync --oracle (if Laravel has Oracle access)');
            $this->line('Or restart erp-clients-api and run: python app.py');
            DB::table('maturities_cache')->truncate();
            return self::SUCCESS;
        }

        DB::table('maturities_cache')->truncate();
        $syncedAt = now();

        $rows = [];
        foreach ($data as $row) {
            $row = is_array($row) ? (object) $row : $row;
            $policy = trim($row->policy_number ?? '');
            if ($policy === '') {
                continue;
            }
            $maturity = $row->maturity ?? $row->maturity_date ?? null;
            if ($maturity) {
                $maturity = \Carbon\Carbon::parse($maturity)->format('Y-m-d');
            }
            $rows[] = [
                'policy_number' => $policy,
                'life_assured' => $row->life_assured ?? $row->life_assur ?? null,
                'product' => $row->product ?? null,
                'maturity' => $maturity,
                'synced_at' => $syncedAt,
                'created_at' => $syncedAt,
                'updated_at' => $syncedAt,
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('maturities_cache')->insert($chunk);
        }

        $this->info('Synced ' . count($rows) . ' maturing policies into cache.');
        return self::SUCCESS;
    }

    /**
     * Fetch maturing policies directly from Oracle (bypasses API).
     */
    protected function fetchFromOracle(string $from, string $to): array
    {
        if (! config('erp.enabled', true)) {
            return [];
        }
        $view = config('erp.clients_individual_view', config('erp.clients_list_table', 'LMS_INDIVIDUAL_CRM_VIEW'));
        try {
            $rows = DB::connection('erp')
                ->table($view)
                ->selectRaw('POLICY_NUMBER, LIFE_ASSURED, PRODUCT, MATURITY_DATE')
                ->whereNotNull('MATURITY_DATE')
                ->limit(50000)
                ->get();
            $fromTs = strtotime($from . ' 00:00:00');
            $toTs = strtotime($to . ' 23:59:59');
            $rows = $rows->filter(function ($r) use ($fromTs, $toTs) {
                $val = $r->MATURITY_DATE ?? $r->maturity_date ?? null;
                if ($val === null || $val === '') {
                    return false;
                }
                if ($val instanceof \DateTimeInterface) {
                    $ts = $val->getTimestamp();
                } else {
                    $s = (string) $val;
                    $ts = strtotime($s) ?: strtotime(preg_replace('#^(\d{2})/(\d{2})/(\d{4})#', '$3-$2-$1', $s));
                }
                return $ts && $ts >= $fromTs && $ts <= $toTs;
            })->values();
            return $rows->map(fn ($r) => (object) [
                'policy_number' => $r->POLICY_NUMBER ?? $r->policy_number ?? null,
                'life_assured' => $r->LIFE_ASSURED ?? $r->life_assured ?? null,
                'product' => $r->PRODUCT ?? $r->product ?? null,
                'maturity' => $this->parseDate($r->MATURITY_DATE ?? $r->maturity_date ?? null),
            ])->filter(fn ($r) => ! empty(trim($r->policy_number ?? '')))->values()->all();
        } catch (\Throwable $e) {
            $this->warn('Oracle: ' . $e->getMessage());
            return [];
        }
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
