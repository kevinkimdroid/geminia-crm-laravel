<?php

namespace App\Console\Commands;

use App\Support\StoragePaths;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class BootstrapServerCommand extends Command
{
    protected $signature = 'app:bootstrap-server';

    protected $description = 'Prepare production server: storage dirs, cache tables, stuck migrations';

    public function handle(): int
    {
        $this->info('Ensuring storage directories...');
        StoragePaths::ensure();
        foreach (StoragePaths::requiredDirectories() as $path) {
            if (! is_dir($path)) {
                $this->warn('Missing: ' . $path);
            }
        }

        $this->info('Ensuring cache tables (for CACHE_STORE=database)...');
        if (! Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
            $this->line('  Created cache table.');
        } else {
            $this->line('  cache table OK.');
        }

        if (! Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
            $this->line('  Created cache_locks table.');
        } else {
            $this->line('  cache_locks table OK.');
        }

        if (Schema::hasTable('maturities_cache')) {
            $this->recordMigrationIfMissing('2026_03_05_100000_create_maturities_cache_table');
        }

        $this->newLine();
        $this->info('Syncing pending migrations whose tables already exist...');
        $this->call('migrate:sync-existing');

        $store = config('cache.default');
        $this->newLine();
        $this->info('Cache driver: ' . $store);
        if ($store === 'file') {
            $this->warn('Set CACHE_STORE=database in .env, then run: php artisan config:cache');
        } else {
            $this->info('Run: php artisan config:cache (if you changed .env)');
        }

        $this->newLine();
        $this->info('Done. Try: php artisan migrate --force');

        return self::SUCCESS;
    }

    protected function recordMigrationIfMissing(string $migration): void
    {
        if (! DB::table('migrations')->where('migration', $migration)->exists()) {
            DB::table('migrations')->insert([
                'migration' => $migration,
                'batch' => (int) DB::table('migrations')->max('batch') + 1,
            ]);
            $this->line('  Recorded ' . $migration);
        }
    }
}
