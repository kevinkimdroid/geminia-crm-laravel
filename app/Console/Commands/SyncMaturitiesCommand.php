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
     *
     * Uses the partial/full maturities source (LMS_POLICY_PRTL_MATURITIES) and pushes the
     * maturity-date window into the SQL WHERE clause so Oracle filters server-side. The
     * PPM_EXPECTED_DATE column is kept "bare" (no TRUNC) so an index can be used, and the
     * half-open range [from, to+1) reproduces an inclusive BETWEEN without breaking the index.
     */
    protected function fetchFromOracle(string $from, string $to): array
    {
        if (! config('erp.enabled', true)) {
            return [];
        }

        // Minimal join set: the cache only needs policy no, client name, product and maturity.
        // The LMS_AGENCIES join (AG.AGN_CODE = ENDR_AGEN_CODE) crashes the Oracle session
        // with ORA-03113 on this DB (datatype mismatch on the agency-code join), so it and the
        // other display-only joins (endorsements/branches/prod_options/checkoff) are omitted.
        // POL_POLICY_NO is wrapped in TO_CHAR to avoid an intermittent OCI8 fetch crash.
        $inner = "SELECT TO_CHAR (POL_POLICY_NO)                    AS POLICY_NUMBER,
                         PRP_OTHER_NAMES || '  ' || PRP_SURNAME     AS LIFE_ASSURED,
                         PROD_DESC                                  AS PRODUCT,
                         TO_CHAR (PPM_EXPECTED_DATE, 'YYYY-MM-DD')  AS MATURITY_DATE
                    FROM LMS_POLICIES,
                         LMS_POLICY_PRTL_MATURITIES,
                         LMS_PRODUCTS,
                         LMS_PROPOSERS
                   WHERE     PPM_POL_CODE = POL_CODE
                         AND POL_PROD_CODE = PROD_CODE
                         AND POL_PRP_CODE = PRP_CODE
                         AND POL_STATUS NOT IN ('M', 'S', 'C', 'J', 'D', 'V')
                         AND PPM_MATURITY_TYPE IN ('P', 'F', 'FM')
                         AND PPM_PAID = 'N'
                         AND PPM_EXPECTED_DATE >= TO_DATE(?, 'YYYY-MM-DD')
                         AND PPM_EXPECTED_DATE <  TO_DATE(?, 'YYYY-MM-DD') + 1
                   ORDER BY PPM_EXPECTED_DATE ASC, POL_POLICY_NO ASC";

        // The OCI8/Instant Client on this server intermittently drops the session
        // (ORA-03113) when transferring more than a handful of these wide rows at once.
        // Small fetches are reliable, so page through the result set in small chunks and
        // retry each page. ROWNUM windowing keeps the ordering deterministic.
        $pageSql = "SELECT * FROM (SELECT a.*, ROWNUM rn FROM ({$inner}) a WHERE ROWNUM <= ?) WHERE rn > ?";

        $pageSize = (int) config('erp.maturities_oracle_page_size', 10);
        $pageSize = max(1, min(100, $pageSize));
        $maxRows = 50000; // safety cap

        $rows = [];
        $lower = 0;
        while ($lower < $maxRows) {
            $upper = $lower + $pageSize;
            $page = null;
            for ($try = 1; $try <= 4; $try++) {
                try {
                    $page = DB::connection('erp')->select($pageSql, [$from, $to, $upper, $lower]);
                    break;
                } catch (\Throwable $e) {
                    DB::purge('erp');
                    if ($try === 4) {
                        $this->warn('Oracle page fetch failed (rows ' . $lower . '+): ' . substr($e->getMessage(), 0, 80));
                    }
                    usleep(300000);
                }
            }

            if ($page === null) {
                break; // give up this page after retries; keep what we have so far
            }

            foreach ($page as $r) {
                $rows[] = (object) [
                    'policy_number' => $r->POLICY_NUMBER ?? $r->policy_number ?? null,
                    'life_assured' => $r->LIFE_ASSURED ?? $r->life_assured ?? null,
                    'product' => $r->PRODUCT ?? $r->product ?? null,
                    'maturity' => $this->parseDate($r->MATURITY_DATE ?? $r->maturity_date ?? null),
                ];
            }

            if (count($page) < $pageSize) {
                break; // last page
            }
            $lower = $upper;
        }

        return collect($rows)
            ->filter(fn ($r) => ! empty(trim($r->policy_number ?? '')))
            ->values()
            ->all();
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
