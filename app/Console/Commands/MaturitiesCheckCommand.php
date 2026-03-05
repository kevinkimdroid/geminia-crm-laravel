<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MaturitiesCheckCommand extends Command
{
    protected $signature = 'maturities:check';

    protected $description = 'Check maturities data source and diagnose common issues';

    public function handle(): int
    {
        $this->info('Maturities diagnostic:');
        $this->newLine();

        $hasMaturitiesCache = Schema::hasTable('maturities_cache');
        $this->line('1. maturities_cache table exists: ' . ($hasMaturitiesCache ? 'Yes' : 'No'));

        if ($hasMaturitiesCache) {
            $mcTotal = DB::table('maturities_cache')->count();
            $this->line('2. maturities_cache rows: ' . $mcTotal);
            $from = now()->format('Y-m-d');
            $to = now()->addDays(90)->format('Y-m-d');
            $mcMaturing = DB::table('maturities_cache')
                ->whereNotNull('maturity')
                ->where('maturity', '>=', $from)
                ->where('maturity', '<=', $to)
                ->count();
            $this->line('3. Maturing in next 90 days: ' . $mcMaturing);
            if ($mcMaturing > 0) {
                $sample = DB::table('maturities_cache')
                    ->where('maturity', '>=', $from)
                    ->where('maturity', '<=', $to)
                    ->limit(3)
                    ->get(['policy_number', 'maturity', 'product']);
                $this->line('4. Sample:');
                foreach ($sample as $r) {
                    $this->line('   - ' . ($r->policy_number ?? '?') . ' | ' . ($r->maturity ?? '?') . ' | ' . ($r->product ?? '?'));
                }
                $this->newLine();
                $this->info('Maturities cache has data. Page should display policies.');
                return self::SUCCESS;
            }
            if ($mcTotal === 0) {
                $this->warn('   Run: php artisan maturities:sync (requires ERP API at ' . config('erp.clients_http_url', 'ERP_CLIENTS_HTTP_URL') . ')');
            }
        }

        $hasTable = Schema::hasTable('erp_clients_cache');
        $this->line(($hasMaturitiesCache ? '5' : '2') . '. erp_clients_cache exists: ' . ($hasTable ? 'Yes' : 'No'));
        $total = $hasTable ? DB::table('erp_clients_cache')->count() : 0;
        $this->line(($hasMaturitiesCache ? '6' : '3') . '. erp_clients_cache rows: ' . $total);

        $maturing = 0;
        if ($hasTable) {
            $from = now()->format('Y-m-d');
            $to = now()->addDays(90)->format('Y-m-d');
            $maturing = DB::table('erp_clients_cache')
                ->whereNotNull('maturity')
                ->whereNotNull('policy_number')
                ->where('maturity', '>=', $from)
                ->where('maturity', '<=', $to)
                ->count();
            $this->line(($hasMaturitiesCache ? '7' : '4') . '. Maturing in next 90 days: ' . $maturing);
        }

        $this->newLine();
        $this->info('To pull data: php artisan migrate && php artisan maturities:sync');
        $this->line('Ensure ERP Clients API is running: cd erp-clients-api && python app.py');
        return $maturing > 0 ? self::SUCCESS : self::FAILURE;
    }
}
