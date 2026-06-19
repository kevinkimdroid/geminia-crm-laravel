<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class SyncPendingMigrationsCommand extends Command
{
    protected $signature = 'migrate:sync-existing
                            {--run : Run php artisan migrate --force after syncing}';

    protected $description = 'Record pending migrations whose tables already exist in the database';

    public function handle(): int
    {
        if (! Schema::hasTable('migrations')) {
            $this->error('migrations table does not exist. Run php artisan migrate --force first.');

            return self::FAILURE;
        }

        $ran = DB::table('migrations')->pluck('migration')->all();
        $files = collect(File::glob(database_path('migrations/*.php')))->sort()->values();

        $batch = (int) DB::table('migrations')->max('batch') + 1;
        $recorded = 0;

        foreach ($files as $file) {
            $migration = pathinfo($file, PATHINFO_FILENAME);

            if (in_array($migration, $ran, true)) {
                continue;
            }

            $content = file_get_contents($file);
            preg_match_all("/Schema::create\s*\(\s*['\"]([^'\"]+)['\"]/", $content, $matches);
            $tables = $matches[1] ?? [];

            if ($tables === []) {
                continue;
            }

            foreach ($tables as $table) {
                if (! Schema::hasTable($table)) {
                    continue 2;
                }
            }

            DB::table('migrations')->insert([
                'migration' => $migration,
                'batch' => $batch,
            ]);
            $this->line("Recorded: {$migration}");
            $recorded++;
        }

        if ($recorded === 0) {
            $this->info('No pending create-table migrations with existing tables found.');
        } else {
            $this->info("Recorded {$recorded} migration(s).");
        }

        if ($this->option('run')) {
            return $this->call('migrate', ['--force' => true]);
        }

        $this->comment('Next: php artisan migrate --force');

        return self::SUCCESS;
    }
}
