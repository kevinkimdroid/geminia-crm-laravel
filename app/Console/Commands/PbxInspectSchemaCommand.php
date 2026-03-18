<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PbxInspectSchemaCommand extends Command
{
    protected $signature = 'pbx:inspect-schema
                            {--sample : Show sample duration values from 5 recent calls}';

    protected $description = 'Inspect vtiger_pbxmanager table structure and duration columns';

    public function handle(): int
    {
        try {
            $columns = DB::connection('vtiger')
                ->select('DESCRIBE vtiger_pbxmanager');
        } catch (\Throwable $e) {
            $this->error('Could not connect to vtiger DB: ' . $e->getMessage());
            return 1;
        }

        $this->info('vtiger_pbxmanager columns:');
        $this->table(
            ['Field', 'Type', 'Null', 'Key', 'Default', 'Extra'],
            collect($columns)->map(fn ($c) => [
                $c->Field,
                $c->Type,
                $c->Null,
                $c->Key ?? '',
                $c->Default ?? '',
                $c->Extra ?? '',
            ])->toArray()
        );

        $durationLike = collect($columns)->filter(fn ($c) => preg_match('/duration|bill|sec|time|length/i', $c->Field));
        if ($durationLike->isNotEmpty()) {
            $this->newLine();
            $this->info('Duration-related columns:');
            foreach ($durationLike as $c) {
                $this->line("  - {$c->Field} ({$c->Type})");
            }
        }

        if ($this->option('sample')) {
            $this->newLine();
            $cols = collect($columns)->pluck('Field')->toArray();
            $durationCols = array_filter($cols, fn ($c) => preg_match('/duration|bill|sec|total/i', strtolower($c)));
            $selectCols = array_merge(
                ['pbxmanagerid', 'starttime', 'callstatus'],
                array_values($durationCols)
            );
            $select = implode(', ', array_map(fn ($c) => "`{$c}`", $selectCols));

            try {
                $rows = DB::connection('vtiger')
                    ->select("SELECT {$select} FROM vtiger_pbxmanager ORDER BY starttime DESC LIMIT 5");
            } catch (\Throwable $e) {
                $this->warn('Could not fetch sample: ' . $e->getMessage());
                return 0;
            }

            $this->info('Sample rows (5 most recent):');
            if (empty($rows)) {
                $this->warn('No rows in vtiger_pbxmanager.');
            } else {
                $this->table(array_keys((array) $rows[0]), collect($rows)->map(fn ($r) => (array) $r)->toArray());
            }
        }

        $this->newLine();
        $this->comment('If duration is always 0, check:');
        $this->comment('  1. Vtiger PBX/Asterisk connector populates billduration/totalduration');
        $this->comment('  2. Set PBX_DURATION_COLUMNS in .env if your table uses different column names');
        $this->comment('  3. Run: php artisan pbx:inspect-schema --sample');

        return 0;
    }
}
