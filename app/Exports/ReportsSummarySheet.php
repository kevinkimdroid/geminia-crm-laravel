<?php

namespace App\Exports;

use App\Services\CrmService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportsSummarySheet implements FromArray, WithHeadings, WithStyles
{
    public function __construct(protected CrmService $crm)
    {
    }

    public function array(): array
    {
        return [
            ['Won Revenue (KES)', $this->crm->getWonRevenue(), 'Closed deals total'],
            ['Pipeline Value (KES)', $this->crm->getPipelineValue(), 'Active opportunities'],
        ];
    }

    public function headings(): array
    {
        return ['Metric', 'Value', 'Notes'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E8EEF4']]],
            2 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F0F0F0']]],
        ];
    }
}
