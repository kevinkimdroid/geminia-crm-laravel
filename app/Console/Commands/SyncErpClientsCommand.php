<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncErpClientsCommand extends Command
{
    protected $signature = 'erp:sync-clients
                            {--batch=150 : Rows per batch}
                            {--replace : Truncate cache before sync (full replace)}
                            {--resume : Resume from current cache count instead of replace}';

    protected $description = 'Sync LMS_INDIVIDUAL_CRM_VIEW from Oracle into erp_clients_cache';

    public function handle(): int
    {
        if (! config('erp.enabled', true)) {
            $this->error('ERP is disabled (ERP_ENABLED=false).');
            return self::FAILURE;
        }

        $replace = $this->option('replace');
        $resume = $this->option('resume');
        $batchSize = (int) $this->option('batch');
        $batchSize = min(max($batchSize, 50), 200);

        $this->info('Syncing ERP clients from Oracle to local cache...');
        if ($replace) {
            DB::table('erp_clients_cache')->truncate();
            $this->line('  Cache truncated.');
        }

        $offset = 0;
        if ($resume && ! $replace) {
            $offset = DB::table('erp_clients_cache')->count();
            if ($offset > 0) {
                $this->line("  Resuming from offset {$offset}.");
            }
        }

        $table = config('erp.clients_list_table', 'LMS_INDIVIDUAL_CRM_VIEW');
        $columns = config('erp.clients_list_columns', 'POLICY_NUMBER,PRODUCT,POL_PREPARED_BY,INTERMEDIARY,STATUS,KRA_PIN');
        $orderCol = config('erp.clients_list_order', 'PRODUCT');
        $syncedAt = now();
        $total = 0;
        $maxRetries = 3;

        while (true) {
            $retries = 0;
            $rows = null;

            while ($retries < $maxRetries) {
                try {
                    $rows = DB::connection('erp')
                        ->table($table)
                        ->selectRaw($columns)
                        ->orderBy($orderCol)
                        ->offset($offset)
                        ->limit($batchSize)
                        ->get();
                    break;
                } catch (\Throwable $e) {
                    $isLost = (strpos($e->getMessage(), 'ORA-03113') !== false) || (strpos($e->getMessage(), 'end-of-file') !== false);
                    $retries++;
                    if ($isLost && $retries < $maxRetries) {
                        DB::purge('erp');
                        $this->line("  Connection dropped, reconnecting... (retry {$retries}/{$maxRetries})");
                        sleep(2);
                    } else {
                        throw $e;
                    }
                }
            }

            if ($rows === null || $rows->isEmpty()) {
                break;
            }

            $hasLifeAssured = \Illuminate\Support\Facades\Schema::hasColumn('erp_clients_cache', 'life_assured');
            $toInsert = [];
            foreach ($rows as $row) {
                $arr = (array) $row;
                $record = [
                    'policy_number' => $arr['POLICY_NUMBER'] ?? $arr['policy_number'] ?? null,
                    'product' => $arr['PRODUCT'] ?? $arr['product'] ?? null,
                    'pol_prepared_by' => $arr['POL_PREPARED_BY'] ?? $arr['pol_prepared_by'] ?? null,
                    'intermediary' => $arr['INTERMEDIARY'] ?? $arr['intermediary'] ?? null,
                    'status' => $arr['STATUS'] ?? $arr['status'] ?? null,
                    'kra_pin' => $arr['KRA_PIN'] ?? $arr['kra_pin'] ?? null,
                    'prp_dob' => $this->parseDate($arr['PRP_DOB'] ?? $arr['prp_dob'] ?? null),
                    'maturity' => $this->parseDate($arr['MATURITY'] ?? $arr['MATURITY_DATE'] ?? $arr['maturity'] ?? null),
                    'paid_mat_amt' => isset($arr['PAID_MAT_AMT']) ? (float) $arr['PAID_MAT_AMT'] : null,
                    'checkoff' => $arr['CHECKOFF'] ?? $arr['checkoff'] ?? null,
                    'effective_date' => $this->parseDate($arr['EFFECTIVE_DATE'] ?? $arr['effective_date'] ?? null),
                    'synced_at' => $syncedAt,
                    'created_at' => $syncedAt,
                    'updated_at' => $syncedAt,
                ];
                if ($hasLifeAssured) {
                    $record['life_assured'] = $arr['LIFE_ASSURED'] ?? $arr['life_assured'] ?? null;
                }
                $toInsert[] = $record;
            }

            DB::table('erp_clients_cache')->insert($toInsert);
            $total += count($toInsert);
            $offset += $batchSize;
            $this->line("  Synced {$total} rows...");

            if (count($rows) < $batchSize) {
                break;
            }

            sleep(1);
        }

        $this->components->info("Sync complete. Total: {$total} clients (cache total: " . DB::table('erp_clients_cache')->count() . ')');

        // Invalidate caches so Clients page shows correct count
        \Illuminate\Support\Facades\Cache::forget('erp_clients_cache_total');
        \Illuminate\Support\Facades\Cache::forget('geminia_clients_count');
        \Illuminate\Support\Facades\Cache::put('clients_list_version', time(), 86400);

        return self::SUCCESS;
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        $str = trim((string) $value);
        foreach (['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y'] as $fmt) {
            try {
                $parsed = \Carbon\Carbon::createFromFormat($fmt, $str);
                if ($parsed instanceof \DateTimeInterface) {
                    return $parsed->format('Y-m-d');
                }
            } catch (\Throwable $e) {
                continue;
            }
        }
        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
