<?php

namespace App\Jobs;

use App\Exports\ReportsAllExport;
use App\Services\CrmService;
use App\Services\TicketSlaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Queued job for heavy report exports (Excel). Run in background to avoid blocking.
 * Usage: ExportReportsJob::dispatch();
 */
class ExportReportsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $ticketAgingDays = 7
    ) {}

    public function handle(CrmService $crm, TicketSlaService $sla): void
    {
        $filename = 'reports-all-' . date('Y-m-d-His') . '.xlsx';
        Excel::store(new ReportsAllExport($crm, $sla, $this->ticketAgingDays), 'exports/' . $filename);
    }
}
