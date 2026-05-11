<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class WorkTicketsWorkbookExport implements WithMultipleSheets
{
    public function __construct(
        protected array $ticketRows,
        protected array $analysisRows
    ) {
    }

    public function sheets(): array
    {
        return [
            'Work Tickets' => new WorkTicketsExport($this->ticketRows),
            'Analysis & Automation' => new AnalysisRecommendationsSheet($this->analysisRows),
        ];
    }
}
