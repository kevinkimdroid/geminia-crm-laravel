<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDOException;
use Throwable;

class TestErpConnectionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'erp:test-connection
                            {--view : Also run a test query against LMS_INDIVIDUAL_CRM_VIEW}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the ERP (Oracle) database connection';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Testing ERP (Oracle) database connection...');

        try {
            $connection = DB::connection('erp');

            // Attempt connection and run simple DUAL query (universal Oracle test)
            $result = $connection->selectOne('SELECT 1 AS value FROM DUAL');

            if ($result === null || !isset($result->value)) {
                throw new \RuntimeException('Query returned unexpected result.');
            }

            $this->components->info('Connection successful.');
            $this->line('  DUAL query returned: 1');

            // Optional: test LMS_INDIVIDUAL_CRM_VIEW if requested (use ROWNUM + single column to minimize load)
            if ($this->option('view')) {
                try {
                    $cols = config('erp.clients_list_columns', 'POLICY_NUMBER');
                    $firstCol = trim(explode(',', $cols)[0] ?? 'POLICY_NUMBER') ?: 'POLICY_NUMBER';
                    $table = config('erp.clients_list_table', 'LMS_INDIVIDUAL_CRM_VIEW');
                    $connection->selectOne(
                        "SELECT {$firstCol} FROM {$table} WHERE ROWNUM = 1"
                    );
                    $this->line("  {$table}: accessible");
                } catch (Throwable $e) {
                    $this->components->warn('View query failed: ' . $this->formatError($e));
                }
            }

            return self::SUCCESS;

        } catch (PDOException $e) {
            $this->reportFailure($e, 'PDO/OCI8');
            return self::FAILURE;
        } catch (Throwable $e) {
            $this->reportFailure($e, get_class($e));
            return self::FAILURE;
        }
    }

    /**
     * Report connection failure with detailed error information.
     */
    private function reportFailure(Throwable $e, string $type): void
    {
        $this->components->error('Connection failed.');
        $this->newLine();
        $this->line('  Type: ' . $type);
        $this->line('  Message: ' . $e->getMessage());
        $this->line('  Code: ' . $e->getCode());

        if ($e instanceof PDOException && isset($e->errorInfo) && is_array($e->errorInfo)) {
            $this->line('  PDO errorInfo:');
            foreach ($e->errorInfo as $i => $val) {
                if ($val !== null && $val !== '') {
                    $this->line('    [' . $i . '] ' . $val);
                }
            }
        }

        if ($this->output->isVerbose()) {
            $this->newLine();
            $this->line('  File: ' . $e->getFile() . ':' . $e->getLine());
            $this->line('  Trace:');
            $this->line($e->getTraceAsString());
        }
    }

    /**
     * Format error message for display.
     */
    private function formatError(Throwable $e): string
    {
        $msg = $e->getMessage();
        if ($e->getCode()) {
            $msg .= ' (code: ' . $e->getCode() . ')';
        }
        return $msg;
    }
}
