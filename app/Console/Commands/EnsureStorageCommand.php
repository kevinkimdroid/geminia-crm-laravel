<?php

namespace App\Console\Commands;

use App\Support\StoragePaths;
use Illuminate\Console\Command;

class EnsureStorageCommand extends Command
{
    protected $signature = 'storage:ensure';

    protected $description = 'Create storage and bootstrap/cache directories required by Laravel';

    public function handle(): int
    {
        StoragePaths::ensure();

        $missing = [];
        foreach (StoragePaths::requiredDirectories() as $path) {
            if (! is_dir($path)) {
                $missing[] = $path;
            }
        }

        if ($missing !== []) {
            $this->error('Some directories could not be created (check permissions):');
            foreach ($missing as $path) {
                $this->line('  - ' . $path);
            }

            return self::FAILURE;
        }

        $this->info('Storage directories are ready.');

        return self::SUCCESS;
    }
}
