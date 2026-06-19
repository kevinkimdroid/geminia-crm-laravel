#!/usr/bin/env php
<?php

/**
 * Standalone script for production servers when artisan commands are not deployed yet.
 * Usage: php scripts/sync-existing-migrations.php [--run]
 */

define('LARAVEL_START', microtime(true));

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$runMigrate = in_array('--run', $argv ?? [], true);

$db = Illuminate\Support\Facades\DB::class;
$schema = Illuminate\Support\Facades\Schema::class;

if (! $schema::hasTable('migrations')) {
    fwrite(STDERR, "migrations table does not exist.\n");
    exit(1);
}

$ran = $db::table('migrations')->pluck('migration')->all();
$files = glob(__DIR__ . '/../database/migrations/*.php');
sort($files);

$batch = (int) $db::table('migrations')->max('batch') + 1;
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
        if (! $schema::hasTable($table)) {
            continue 2;
        }
    }

    $db::table('migrations')->insert([
        'migration' => $migration,
        'batch' => $batch,
    ]);
    echo "Recorded: {$migration}\n";
    $recorded++;
}

echo $recorded === 0
    ? "No pending create-table migrations with existing tables found.\n"
    : "Recorded {$recorded} migration(s).\n";

if ($runMigrate) {
    passthru('php ' . escapeshellarg(__DIR__ . '/../artisan') . ' migrate --force', $exitCode);
    exit($exitCode);
}

echo "Next: php artisan migrate --force\n";
