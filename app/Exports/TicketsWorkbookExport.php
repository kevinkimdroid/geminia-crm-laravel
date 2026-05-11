<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TicketsWorkbookExport implements WithMultipleSheets
{
    public function __construct(
        protected array $ticketRows,
        protected array $analysisRows
    ) {
    }

    public function sheets(): array
    {
        return [
            'Tickets' => new TicketsExport($this->ticketRows),
            'Analysis & Automation' => new AnalysisRecommendationsSheet($this->analysisRows),
        ];
    }
}
