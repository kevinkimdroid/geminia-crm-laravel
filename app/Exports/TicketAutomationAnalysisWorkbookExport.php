<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TicketAutomationAnalysisWorkbookExport implements WithMultipleSheets
{
    public function __construct(
        protected array $summaryRows,
        protected array $detailRows
    ) {
    }

    public function sheets(): array
    {
        return [
            'Summary Snapshot' => new ManagementUsageExport($this->summaryRows),
            'Detailed Analysis' => new ManagementUsageExport($this->detailRows),
        ];
    }
}
