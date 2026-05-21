<?php

namespace App\Jobs;

use App\Services\ErpSmsMessageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendErpSmsMessagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Avoid queue-level retries because this job sends real SMS messages.
     */
    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public int $limit = 50,
        public bool $dryRun = false,
        public ?int $userId = null
    ) {}

    public function handle(ErpSmsMessageService $messages): void
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $summary = $messages->sendPending(
            max(1, min(500, $this->limit)),
            $this->dryRun,
            $this->userId
        );

        Log::info('ERP SMS messages job completed', [
            'processed' => $summary['processed'],
            'sent' => $summary['sent'],
            'failed' => $summary['failed'],
            'skipped' => $summary['skipped'],
            'dry_run' => $this->dryRun,
        ]);
    }
}
