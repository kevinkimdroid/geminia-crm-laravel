<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateMaturitiesCacheTableCommand extends Command
{
    protected $signature = 'maturities:create-table';

    protected $description = 'Create maturities_cache table via raw SQL (use when migrate fails)';

    public function handle(): int
    {
        $sql = "CREATE TABLE IF NOT EXISTS maturities_cache (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            policy_number VARCHAR(64) NULL,
            life_assured VARCHAR(255) NULL,
            product VARCHAR(255) NULL,
            maturity DATE NULL,
            policy_status VARCHAR(16) NULL,
            synced_at TIMESTAMP NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            INDEX (policy_number),
            INDEX (product),
            INDEX (maturity),
            INDEX (policy_status),
            INDEX (maturity, product)
        )";

        try {
            DB::connection(config('database.default'))->statement($sql);
            $this->addPolicyStatusColumnIfMissing();
            $this->info('maturities_cache table created successfully.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function addPolicyStatusColumnIfMissing(): void
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasColumn('maturities_cache', 'policy_status')) {
                DB::connection(config('database.default'))->statement('ALTER TABLE maturities_cache ADD COLUMN policy_status VARCHAR(16) NULL AFTER maturity');
            }
        } catch (\Throwable $e) {
            // Ignore if column exists
        }
    }
}
